#!/usr/bin/env bash
#
# Le Canard du Vendredi — test de restauration vers une cible JETABLE (§37.4).
#
# Restaure le dump le plus récent depuis restic vers un PostgreSQL THROWAWAY
# (conteneur éphémère, supprimé en sortie) — JAMAIS la base de prod — puis
# vérifie que la restauration a produit des données (comptage). C'est ce qui
# transforme « j'ai des backups » en « j'ai des backups RESTAURABLES ».
#
# Utilisé par le runbook (manuel) ET par le timer mensuel canard-restore-test.
# La cible est toujours un conteneur neuf : aucune commande ne touche la prod.
#
# Test manuel :  set -a; . /etc/canard-backup/backup.env; set +a; ./restore-test.sh
set -euo pipefail

log()  { printf '\033[1;36m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✓ %s\033[0m\n' "$*"; }
err()  { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; }

RESTIC="${RESTIC:-restic}"
STDIN_FILENAME="${STDIN_FILENAME:-canard-db.sql}"
RESTORE_TEST_IMAGE="${RESTORE_TEST_IMAGE:-postgres:17-alpine}"
# Vérification : par défaut, au moins une table dans le schéma public. Surchargeable
# pour cibler une table métier connue (ex. VERIFY_QUERY="SELECT count(*) FROM coffee").
VERIFY_QUERY="${VERIFY_QUERY:-SELECT count(*) FROM information_schema.tables WHERE table_schema='public'}"
VERIFY_MIN="${VERIFY_MIN:-1}"
: "${RESTIC_REPOSITORY:?RESTIC_REPOSITORY manquant (voir backup.env)}"

hc() {
  [ -n "${HEALTHCHECK_RESTORE_PING_URL:-}" ] || return 0
  curl -fsS -m 10 --retry 3 "${HEALTHCHECK_RESTORE_PING_URL}${1}" >/dev/null 2>&1 || true
}
# Trap EXIT unique : détruit la cible jetable ET pingue /fail tant que SUCCESS≠1
# (couvre les `exit 1` explicites de la vérif, qu'un trap ERR raterait).
SUCCESS=0
cid=""
cleanup() {
  [ -n "$cid" ] && docker rm -f "$cid" >/dev/null 2>&1 || true
  [ "$SUCCESS" = 1 ] || hc /fail
}
trap cleanup EXIT
hc /start

# ── Cible jetable : PostgreSQL éphémère, détruit quoi qu'il arrive ─────────────
log "Démarrage d'un PostgreSQL throwaway (${RESTORE_TEST_IMAGE})…"
cid="$(docker run -d --rm \
  -e POSTGRES_USER=scratch -e POSTGRES_PASSWORD=scratch -e POSTGRES_DB=scratch \
  "$RESTORE_TEST_IMAGE")"

# Attendre que la cible soit RÉELLEMENT interrogeable. NB : pg_isready peut
# répondre « prêt » pendant la phase d'init de l'image (serveur temporaire, base
# pas encore créée) → on sonde par une vraie requête sur la base cible.
ready=0
for _ in $(seq 1 60); do
  if docker exec "$cid" psql -U scratch -d scratch -tAc 'SELECT 1' >/dev/null 2>&1; then
    ready=1; break
  fi
  sleep 1
done
[ "$ready" = 1 ] || { err "Cible jetable jamais prête après 60 s."; exit 1; }

# ── Restauration : restic dump latest → psql (ON_ERROR_STOP) ───────────────────
log "Restauration du dump le plus récent (restic dump latest ${STDIN_FILENAME})…"
$RESTIC dump latest "$STDIN_FILENAME" \
  | docker exec -i "$cid" psql -v ON_ERROR_STOP=1 -U scratch -d scratch >/dev/null
ok "Dump restauré dans la cible jetable."

# ── Vérification : la restauration a-t-elle produit des données ? ──────────────
count="$(docker exec "$cid" psql -tA -U scratch -d scratch -c "$VERIFY_QUERY" | tr -d '[:space:]')"
if [ -z "${count:-}" ] || [ "$count" -lt "$VERIFY_MIN" ]; then
  err "Vérification ÉCHOUÉE : '$VERIFY_QUERY' = ${count:-∅} (< ${VERIFY_MIN})."
  exit 1
fi
ok "Vérification OK : '$VERIFY_QUERY' = ${count} (≥ ${VERIFY_MIN})."

SUCCESS=1   # le trap EXIT nettoie la cible sans pinguer /fail
hc ""
ok "Test de restauration RÉUSSI."
