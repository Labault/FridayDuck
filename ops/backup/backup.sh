#!/usr/bin/env bash
#
# Le Canard du Vendredi — sauvegarde PostgreSQL chiffrée off-site (§37.4).
#
# DISTINCT du dump pré-migration de deploy.sh (filet de rollback local). Ici :
# pg_dump logique → restic --stdin → Storage Box Hetzner (SFTP), chiffré, avec
# rétention. Tourne au niveau HÔTE (timer systemd), INDÉPENDANT des redéploiements
# qui recréent le conteneur app : on attaque la base par son nom de conteneur
# stable (duck_db), sans passer par Compose ni connaître le chemin du projet.
#
# Les credentials Postgres ne quittent JAMAIS le conteneur : pg_dump lit
# $POSTGRES_USER / $POSTGRES_DB depuis l'environnement de duck_db (socket local,
# trust). L'hôte ne détient que les secrets restic (cf. /etc/canard-backup/backup.env).
#
# GARDE-FOU CRITIQUE (bug n°1 des backups) : `pg_dump | restic --stdin` peut
# stocker un flux VIDE et sortir 0. On s'en prémunit en DEUX temps :
#   1. `set -o pipefail` → la mort de pg_dump fait échouer tout le pipeline
#      (aucun `forget`, l'alerte part, le dead-man's-switch n'est PAS pingé).
#   2. on capture le résumé JSON de restic et on REFUSE un snapshot dont la
#      taille traitée est sous un plancher → le snapshot bidon est supprimé.
# Le snapshot n'est validé QUE si le dump a une taille plausible.
#
# Lancé par canard-backup.service (oneshot). Env fourni par EnvironmentFile.
# Test manuel :  set -a; . /etc/canard-backup/backup.env; set +a; ./backup.sh
set -euo pipefail

log()  { printf '\033[1;36m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✓ %s\033[0m\n' "$*"; }
err()  { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; }

# ── Configuration (via EnvironmentFile ; défauts sûrs) ────────────────────────
DB_CONTAINER="${DB_CONTAINER:-duck_db}"
STDIN_FILENAME="${STDIN_FILENAME:-canard-db.sql}"   # nom CONSTANT : la date vit
                                                    # dans l'horodatage du snapshot.
MIN_DUMP_BYTES="${MIN_DUMP_BYTES:-2000}"            # plancher anti-dump-vide.
KEEP_DAILY="${RESTIC_KEEP_DAILY:-7}"
KEEP_WEEKLY="${RESTIC_KEEP_WEEKLY:-4}"
RESTIC="${RESTIC:-restic}"                          # surchargeable pour les tests.

# restic exige RESTIC_REPOSITORY + RESTIC_PASSWORD (ou _FILE) dans l'environnement.
: "${RESTIC_REPOSITORY:?RESTIC_REPOSITORY manquant (voir backup.env)}"
if [ -z "${RESTIC_PASSWORD:-}" ] && [ -z "${RESTIC_PASSWORD_FILE:-}" ]; then
  err "RESTIC_PASSWORD (ou RESTIC_PASSWORD_FILE) manquant — voir backup.env."
  exit 1
fi

# ── Dead-man's-switch / alerte (healthchecks.io ou équivalent, optionnel) ─────
# /start au début, URL nue en succès, /fail en échec. Si l'URL est absente, no-op.
hc() {
  [ -n "${HEALTHCHECK_PING_URL:-}" ] || return 0
  curl -fsS -m 10 --retry 3 "${HEALTHCHECK_PING_URL}${1}" >/dev/null 2>&1 || true
}
# Trap EXIT (et NON ERR) : couvre AUSSI les `exit 1` explicites du garde-fou, qui
# ne déclenchent pas ERR. Tant que SUCCESS≠1, on pingue /fail. Le résumé JSON de
# restic (métadonnées, ~1 Ko — PAS le dump) transite par un fichier temporaire
# supprimé ici : le dump, lui, ne touche jamais le disque (stream direct → restic).
SUCCESS=0
SUMMARY_FILE="$(mktemp)"
cleanup() { rm -f "$SUMMARY_FILE" 2>/dev/null || true; [ "$SUCCESS" = 1 ] || hc /fail; }
trap cleanup EXIT
hc /start

# ── Pré-vol : le conteneur de base doit tourner ───────────────────────────────
if [ "$(docker inspect -f '{{.State.Running}}' "$DB_CONTAINER" 2>/dev/null || echo false)" != true ]; then
  err "Conteneur '$DB_CONTAINER' introuvable ou arrêté — base injoignable."
  exit 1
fi

# ── Init guardée : crée le dépôt restic au tout premier run ────────────────────
if ! $RESTIC snapshots >/dev/null 2>&1; then
  log "Dépôt restic absent — initialisation…"
  $RESTIC init
  ok "Dépôt restic initialisé."
fi

# ── Sauvegarde : pg_dump → restic --stdin (un seul flux, aucun fichier) ────────
# Pipeline RÉEL (pas $(...)) pour récupérer le statut de CHAQUE étage via PIPESTATUS.
# Crucial : si pg_dump meurt en plein vol, restic peut TOUT DE MÊME committer un
# snapshot du flux tronqué (vérifié en test). On le détecte (dump_rc≠0) et on le
# SUPPRIME, sinon `restic dump latest` restaurerait un jour ce flux corrompu.
log "Dump PostgreSQL → restic (${RESTIC_REPOSITORY})…"
set +e
# shellcheck disable=SC2016  # $POSTGRES_* doivent s'expandre DANS le conteneur.
docker exec "$DB_CONTAINER" sh -c \
    'pg_dump --no-owner --no-privileges --clean --if-exists -U "$POSTGRES_USER" -d "$POSTGRES_DB"' \
  | $RESTIC backup --stdin --stdin-filename "$STDIN_FILENAME" --tag canard-db --json > "$SUMMARY_FILE"
# Copier PIPESTATUS en UN SEUL coup : toute commande (même une affectation) le
# réinitialise, donc le lire en deux temps perdrait le statut du 2ᵉ étage.
pstat=( "${PIPESTATUS[@]}" )
dump_rc="${pstat[0]}"; restic_rc="${pstat[1]}"
set -e

summary="$(cat "$SUMMARY_FILE")"
bytes="$(printf '%s' "$summary" | grep -o '"total_bytes_processed":[0-9]*' | head -n1 | grep -o '[0-9]*$' || true)"
snapid="$(printf '%s' "$summary" | grep -o '"snapshot_id":"[a-f0-9]*"' | head -n1 | sed 's/.*"\([a-f0-9]*\)"/\1/' || true)"

# ── Garde-fou : valide UNIQUEMENT si pg_dump ET restic ont réussi ET taille OK ─
valid=1
[ "$dump_rc" -ne 0 ]   && { err "pg_dump a échoué (rc=${dump_rc})."; valid=0; }
[ "$restic_rc" -ne 0 ] && { err "restic a échoué (rc=${restic_rc})."; valid=0; }
if [ -z "${bytes:-}" ] || [ "$bytes" -lt "$MIN_DUMP_BYTES" ]; then
  err "Dump SUSPECT : ${bytes:-0} octets < plancher ${MIN_DUMP_BYTES}."
  valid=0
fi

if [ "$valid" -ne 1 ]; then
  # Supprimer le snapshot éventuellement créé : `latest` ne doit JAMAIS pointer
  # sur un flux partiel/vide. C'est le cœur du garde-fou « bug n°1 ».
  if [ -n "${snapid:-}" ]; then
    log "Suppression du snapshot invalide ${snapid}…"
    $RESTIC forget "$snapid" --prune || true
  fi
  err "Snapshot rejeté — aucun backup validé ce run."
  exit 1
fi
ok "Dump validé : ${bytes} octets (snapshot ${snapid:-?})."

# ── Rétention : 7 quotidiens + 4 hebdos, sur les seuls snapshots canard-db ─────
log "Application de la rétention (keep-daily ${KEEP_DAILY}, keep-weekly ${KEEP_WEEKLY})…"
$RESTIC forget --tag canard-db --keep-daily "$KEEP_DAILY" --keep-weekly "$KEEP_WEEKLY" --prune
ok "Rétention appliquée."

# ── Succès : ping du dead-man's-switch (le trap EXIT ne pingue plus /fail) ─────
SUCCESS=1
hc ""
ok "Sauvegarde terminée."
