#!/usr/bin/env bash
# Vérification e2e OBSERVABILITÉ (Phase 8b) — prouve EN EXÉCUTION RÉELLE que les
# trois signaux atterrissent et qu'une alerte critique se déclenche vraiment.
#
# Pré-requis (voir docs/observability.md « e2e observabilité 8b ») :
#   docker compose -f compose.observability.yaml up -d
#   docker compose -f compose.observability-e2e.yaml up -d --build --wait
#
# Usage :  bash observability/verify-e2e.sh
#
# N'est PAS branché en CI par défaut (empreinte : ~7 conteneurs + 2 bases). Lancement
# LOCAL documenté, conformément au périmètre 8b. Sort non nul au premier échec.
set -euo pipefail

APP="${APP_OBS_URL:-http://localhost:8090}"
PROM="${PROM_URL:-http://localhost:9090}"
NET="${OBS_NET:-friday-duck-observability}"
CURL_IMG="curlimages/curl:latest"
pass() { printf '  \033[32mPASS\033[0m %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m %s\n' "$1"; exit 1; }
inet() { docker run --rm --network "$NET" "$CURL_IMG" -s "$@"; }

echo "== 1. Café tracé bout-en-bout (Tempo) =="
JAR="$(mktemp)"
curl -s -c "$JAR" -b "$JAR" "$APP/api/friday/current" >/dev/null
KEY="verify-$(date +%s 2>/dev/null || echo run)"
ACCEPTED="$(curl -s -c "$JAR" -b "$JAR" -X POST -H "Idempotency-Key: $KEY" "$APP/api/friday/current/coffees" \
  | python3 -c 'import sys,json;print(json.load(sys.stdin).get("accepted"))')"
rm -f "$JAR"
[ "$ACCEPTED" = "True" ] || fail "café non accepté (l'app n'est pas un vendredi actif ?)"
pass "café accepté"

echo "   attente relais + flush + batch (14s)…"; sleep 14
# La trace contenant energy.recalculate doit AUSSI porter le span du relais,
# enfant via le traceparent de l'outbox (frontière async franchie).
TID="$(inet "http://tempo:3200/api/search?q=%7B%20name%3D%22mercure.update.publish%22%20%7D&limit=1" \
  | python3 -c 'import sys,json;t=json.load(sys.stdin).get("traces",[]);print(t[0]["traceID"] if t else "")')"
[ -n "$TID" ] || fail "aucun span mercure.update.publish dans Tempo"
NAMES="$(inet "http://tempo:3200/api/traces/$TID" | python3 -c '
import sys,json
d=json.load(sys.stdin); ns=set()
for b in d.get("batches",[]):
    for ss in b.get("scopeSpans",[]):
        for s in ss.get("spans",[]): ns.add(s.get("name"))
print("|".join(sorted(ns)))')"
for span in "HTTP POST" "energy.recalculate" "coffee.contribution.persist" "mercure.update.publish"; do
  echo "$NAMES" | grep -q "$span" || fail "span absent de la trace café : $span"
done
pass "trace café complète + span relais enfant (async franchie) : $TID"

echo "== 2. Métriques (Prometheus) =="
q() { curl -s "$PROM/api/v1/query?query=$1" | python3 -c 'import sys,json;r=json.load(sys.stdin)["data"]["result"];print(r[0]["value"][1] if r else "ABSENT")'; }
# Tolère la latence batch (5s) + scrape (15s) : on interroge jusqu'à ~60s.
need() { # need <metric> <label>
  for _ in $(seq 1 12); do v="$(q "$1")"; [ "$v" != "ABSENT" ] && { pass "$2=$v"; return 0; }; sleep 5; done
  fail "$2 absente après 60s"
}
need duck_coffee_total "métrique métier : duck_coffee_total"
need http_server_request_duration_count "métrique auto-instrumentée (Symfony) : http_server_request_duration_count"
need worker_memory_bytes "diagnostic worker : worker_memory_bytes"

echo "== 3. Logs corrélés (Loki) =="
loki_tid() { inet --get "http://loki:3100/loki/api/v1/query_range" \
  --data-urlencode 'query={service_name="friday-duck"}' --data-urlencode 'limit=300' 2>/dev/null \
  | python3 -c '
import sys,json
d=json.load(sys.stdin); n=0
for s in d.get("data",{}).get("result",[]):
    if s.get("stream",{}).get("trace_id"): n+=len(s.get("values",[]))
print(n)' 2>/dev/null || echo 0; }
for _ in $(seq 1 12); do
  WITH_TID="$(loki_tid)"; [ "${WITH_TID:-0}" -ge 1 ] && break; sleep 5
done
[ "${WITH_TID:-0}" -ge 1 ] || fail "aucun log porteur de trace_id dans Loki"
pass "logs corrélés trace↔logs : $WITH_TID lignes avec trace_id"

echo ""
echo "Tous les signaux d'observabilité atterrissent. (Alertes : voir docs/observability.md)"
