#!/usr/bin/env bash
#
# Le Canard du Vendredi — vérification d'intégrité restic (§37.4).
#
# `restic check --read-data` RELIT l'intégralité des données off-site et vérifie
# qu'elles se déchiffrent et se décompressent. C'est la VRAIE garantie qu'un
# backup est LISIBLE, pas juste présent (un snapshot listé peut être corrompu).
# Abordable vu la taille de la base ; lancé en hebdo par canard-backup-check.service.
#
# Test manuel :  set -a; . /etc/canard-backup/backup.env; set +a; ./check.sh
set -euo pipefail

log()  { printf '\033[1;36m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✓ %s\033[0m\n' "$*"; }

RESTIC="${RESTIC:-restic}"
: "${RESTIC_REPOSITORY:?RESTIC_REPOSITORY manquant (voir backup.env)}"

# Dead-man's-switch dédié à la vérif (optionnel, indépendant du backup).
hc() {
  [ -n "${HEALTHCHECK_CHECK_PING_URL:-}" ] || return 0
  curl -fsS -m 10 --retry 3 "${HEALTHCHECK_CHECK_PING_URL}${1}" >/dev/null 2>&1 || true
}
# Trap EXIT (couvre set -e ET exit explicites) : /fail tant que SUCCESS≠1.
SUCCESS=0
trap '[ "$SUCCESS" = 1 ] || hc /fail' EXIT
hc /start

log "Vérification d'intégrité (restic check --read-data)…"
$RESTIC check --read-data
ok "Intégrité vérifiée : toutes les données off-site sont relisibles."

SUCCESS=1
hc ""
