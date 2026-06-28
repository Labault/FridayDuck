#!/usr/bin/env bash
#
# Le Canard du Vendredi — déploiement applicatif (push-to-deploy, §31.5).
#
# Déclenché par `git push main` : le webhook fait `git reset --hard origin/main`
# puis exécute ce script. Ordre IMPÉRATIF (rollback prêt AVANT toute bascule) :
#
#   0. préserver l'image en cours sous :rollback  (filet, AVANT le build)
#   1. dump PostgreSQL pré-migration              (filet base)
#   2. build de l'image prod                       (assets + .env.local.php)
#   3. base up + migrations Doctrine (gate)        (--all-or-nothing)
#   4. app + worker + relay up
#   5. HEALTHCHECK HTTP BLOQUANT                    (rouge → rollback auto + exit 1)
#
# Idempotent et sûr à rejouer. NE bascule JAMAIS le trafic avant un /health vert.
set -euo pipefail

cd "$(dirname "$0")"

# ── Configuration (surchargeables pour la répétition locale) ──────────────────
ENV_FILE="${ENV_FILE:-.env.prod.local}"
COMPOSE_FILE="${COMPOSE_FILE:-compose.prod.yaml}"
IMAGE="${IMAGE:-friday-duck/app}"
HEALTH_TIMEOUT="${HEALTH_TIMEOUT:-150}"   # secondes avant d'abandonner le boot
BACKUP_DIR="${BACKUP_DIR:-backups}"

log()  { printf '\033[1;36m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✓ %s\033[0m\n' "$*"; }
err()  { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; }

compose() { docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"; }

if [ ! -f "$ENV_FILE" ]; then
  err "Fichier de secrets '$ENV_FILE' absent. Sur le VPS : cp .env.prod.local.dist $ENV_FILE puis renseigner les secrets."
  exit 1
fi

APP_VERSION="$(git describe --tags --always 2>/dev/null || git rev-parse --short HEAD)"
export APP_VERSION
log "Déploiement de la version ${APP_VERSION}"

# ── 0. Rollback prêt AVANT : préserver l'image actuellement déployée ──────────
ROLLBACK_AVAILABLE=0
if docker image inspect "${IMAGE}:latest" >/dev/null 2>&1; then
  docker tag "${IMAGE}:latest" "${IMAGE}:rollback"
  ROLLBACK_AVAILABLE=1
  ok "Image courante préservée sous ${IMAGE}:rollback"
else
  log "Pas d'image précédente (premier déploiement) : aucun rollback d'image disponible."
fi

rollback() {
  err "Déploiement ÉCHOUÉ — rollback en cours…"
  if [ "$ROLLBACK_AVAILABLE" = 1 ]; then
    docker tag "${IMAGE}:rollback" "${IMAGE}:latest"
    compose up -d --no-build app worker relay
    err "Rollback effectué : image précédente redéployée. Inspecter les logs (compose logs app)."
  else
    err "Aucune image de rollback (premier déploiement) — stack laissée arrêtée. Corriger puis redéployer."
    compose down || true
  fi
}

# ── 1. Filet base : dump PostgreSQL avant migrations ──────────────────────────
# La base doit tourner pour être dumpée ; on la démarre d'abord.
log "Démarrage de la base de données…"
compose up -d database
# Attendre que PostgreSQL accepte les connexions (healthcheck du service).
db_cid="$(compose ps -q database)"
for _ in $(seq 1 30); do
  [ "$(docker inspect -f '{{.State.Health.Status}}' "$db_cid" 2>/dev/null || echo starting)" = healthy ] && break
  sleep 2
done

mkdir -p "$BACKUP_DIR"
DUMP_FILE="${BACKUP_DIR}/pre-migrate-$(date +%Y%m%d-%H%M%S).sql"
# shellcheck disable=SC2016  # $POSTGRES_* doivent s'expandre DANS le conteneur, pas ici.
if compose exec -T database sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' > "$DUMP_FILE" 2>/dev/null && [ -s "$DUMP_FILE" ]; then
  ok "Dump pré-migration : ${DUMP_FILE} ($(wc -c < "$DUMP_FILE") octets)"
else
  rm -f "$DUMP_FILE"
  log "Pas de dump (base vide ou inexistante — premier déploiement)."
fi

# ── 2. Build de l'image prod (assets compilés + config env figée) ─────────────
log "Build de l'image de production…"
compose build --build-arg APP_VERSION="$APP_VERSION" app

# ── 3. Migrations Doctrine — GATE explicite, AVANT la mise en service ─────────
# Conteneur jetable, --no-deps (la base tourne déjà), --all-or-nothing :
# l'échec d'une migration STOPPE le déploiement (aucune bascule).
log "Migrations Doctrine…"
if ! compose run --rm --no-deps app php bin/console doctrine:migrations:migrate \
      --no-interaction --all-or-nothing --allow-no-migration; then
  err "Migrations en échec — restauration possible depuis ${DUMP_FILE:-<aucun dump>}."
  rollback
  exit 1
fi
ok "Migrations appliquées."

# ── 4. Mise en service : app (web + hub Mercure) + worker + relais outbox ─────
log "Démarrage de l'application, du worker et du relais…"
compose up -d app worker relay

# ── 5. HEALTHCHECK HTTP BLOQUANT (/health 3 couches, base incluse) ────────────
log "Attente du healthcheck (/health) — timeout ${HEALTH_TIMEOUT}s…"
app_cid="$(compose ps -q app)"
elapsed=0
healthy=0
while [ "$elapsed" -lt "$HEALTH_TIMEOUT" ]; do
  status="$(docker inspect -f '{{.State.Health.Status}}' "$app_cid" 2>/dev/null || echo starting)"
  if [ "$status" = healthy ]; then healthy=1; break; fi
  sleep 3; elapsed=$((elapsed + 3))
done

if [ "$healthy" != 1 ]; then
  err "Healthcheck ROUGE après ${HEALTH_TIMEOUT}s (statut: ${status:-inconnu})."
  compose logs --tail=50 app >&2 || true
  rollback
  exit 1
fi

# Confirmation lisible du corps de /health (version + base) via le vhost interne.
# shellcheck disable=SC2016  # code PHP : ne pas laisser le shell hôte interpréter $r.
compose exec -T app php -r '$r=@file_get_contents("http://app/health"); echo $r ? $r.PHP_EOL : "no body".PHP_EOL;' || true
ok "Healthcheck VERT — version ${APP_VERSION} en ligne."
log "Smoke test : ./scripts/smoke-test.sh   (valide le comportement réel sur le domaine public)"
