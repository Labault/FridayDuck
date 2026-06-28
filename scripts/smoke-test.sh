#!/usr/bin/env bash
#
# Le Canard du Vendredi — smoke test POST-déploiement (§31.5).
#
# DISTINCT du healthcheck : le healthcheck *gate* le déploiement (réachabilité +
# base), ce smoke test valide le COMPORTEMENT RÉEL sur le domaine public, après
# mise en ligne — TLS, routage, horloge réelle, hub Mercure joignable en HTTPS.
#
# Usage :  ./scripts/smoke-test.sh [BASE_URL]
#          SMOKE_INSECURE=1 ./scripts/smoke-test.sh https://canard.localhost   (répétition locale, CA interne Caddy)
set -euo pipefail

BASE_URL="${1:-https://tibec.labault.dev}"
# SMOKE_INSECURE=1 → accepte un certificat non vérifié (CA interne Caddy, répétition locale).
INSECURE=()
[ "${SMOKE_INSECURE:-0}" = 1 ] && INSECURE=(-k)
CURL=(curl -fsS --max-time 10 "${INSECURE[@]}")

pass() { printf '\033[1;32m✓ %s\033[0m\n' "$*"; }
fail() { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

printf '\033[1;36m▶ Smoke test sur %s\033[0m\n' "$BASE_URL"

# ── 1. /health/ready : 200, status ok, base up ────────────────────────────────
ready="$("${CURL[@]}" "$BASE_URL/health/ready")" || fail "/health/ready injoignable"
echo "$ready" | grep -q '"status":"ok"'  || fail "/health/ready status ≠ ok : $ready"
echo "$ready" | grep -q '"db":"up"'      || fail "/health/ready db ≠ up : $ready"
version="$(printf '%s' "$ready" | sed -n 's/.*"version":"\([^"]*\)".*/\1/p')"
pass "/health/ready OK (version=${version:-?}, base up)"

# ── 2. /api/friday/current : cohérent avec l'horloge RÉELLE (Europe/Paris) ─────
# Prouve qu'APP_FAKE_NOW n'est PAS figé : `active` ne doit être vrai QUE le vendredi.
current="$("${CURL[@]}" "$BASE_URL/api/friday/current")" || fail "/api/friday/current injoignable"
real_dow="$(TZ=Europe/Paris date +%u)"   # 1=lundi … 5=vendredi … 7=dimanche
if [ "$real_dow" = 5 ]; then expected=true; else expected=false; fi
if echo "$current" | grep -q "\"active\":${expected}"; then
  pass "/api/friday/current cohérent : active=${expected} (jour réel Europe/Paris = ${real_dow})"
else
  fail "/api/friday/current INCOHÉRENT avec l'horloge réelle (attendu active=${expected}) → APP_FAKE_NOW figé ? : $current"
fi

# ── 3. Mercure : hub public joignable en HTTPS (abonnement anonyme) ───────────
# Un abonné SSE doit obtenir 200 + text/event-stream. Prouve que l'EventSource
# du navigateur peut bien joindre le hub public (le piège exact de l'e2e).
# -w DOIT finir par \n (sinon `read` voit EOF sans newline → échec sous set -e).
# Pas de -f ici : un flux SSE de 4 s coupé par --max-time renvoie un code ≠ 0 ;
# on lit le code HTTP via -w, pas via le statut de curl.
read -r code ctype < <(
  curl -sS -N "${INSECURE[@]}" -o /dev/null -w '%{http_code} %{content_type}\n' --max-time 4 \
    "$BASE_URL/.well-known/mercure?topic=https://tibec.labault.dev/smoke" 2>/dev/null || true
)
case "${code:-000}-${ctype:-}" in
  200-text/event-stream*) pass "Mercure HTTPS joignable (200, text/event-stream)" ;;
  *) fail "Hub Mercure injoignable / non-anonyme (code=${code:-?}, content-type=${ctype:-?})" ;;
esac

printf '\033[1;32m✓ Smoke test PASSÉ\033[0m\n'
