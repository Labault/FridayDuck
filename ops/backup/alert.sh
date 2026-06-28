#!/usr/bin/env bash
#
# Le Canard du Vendredi — notificateur d'échec, déclenché par OnFailure= systemd.
#
# Receiver PLUGGABLE (le premier configuré gagne) :
#   1. ALERT_WEBHOOK        — POST JSON générique (Slack/Discord/ntfy/…)
#   2. HEALTHCHECK_PING_URL — ping /fail healthchecks.io (défaut recommandé)
#
# Reçoit en argument le nom de l'unité défaillante (%i depuis le template systemd).
set -euo pipefail

unit="${1:-unité inconnue}"
host="$(hostname 2>/dev/null || echo '?')"
msg="[canard-backup] ÉCHEC de ${unit} sur ${host}"

if [ -n "${ALERT_WEBHOOK:-}" ]; then
  curl -fsS -m 15 --retry 3 \
    -H 'Content-Type: application/json' \
    -d "{\"text\":\"${msg}\"}" \
    "$ALERT_WEBHOOK" >/dev/null 2>&1 || true
elif [ -n "${HEALTHCHECK_PING_URL:-}" ]; then
  curl -fsS -m 15 --retry 3 --data-raw "$msg" \
    "${HEALTHCHECK_PING_URL}/fail" >/dev/null 2>&1 || true
fi

# Toujours tracé dans le journal (journalctl -u canard-backup-alert@…).
printf '%s\n' "$msg" >&2
