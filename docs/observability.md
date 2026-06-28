# Observabilité

> Instrumentation (7a) + stack local & vérification bout-en-bout (7b). Dashboards
> (§26.6) et alertes (§26.7) = 7c. Référence : [cahier](cdc_friday_duck.md) (§21, §26).

## Objectif

Diagnostiquer : énergie incohérente, café refusé, vote non clôturé, accessoire non
appliqué, fuite mémoire worker, latence, événement Mercure non publié, séquence
visuelle non initialisée (§26.1).

## Pile

L'app émet en **OTLP** (manuel : spans/métriques/logs ; auto : Doctrine/Messenger
via l'extension PECL `opentelemetry`) vers un **OTel Collector** qui ventile :
traces → **Tempo**, métriques → **Prometheus** (scrape de l'exporter Prometheus du
Collector), logs → **Loki**. **Grafana** lit les trois, corrélation trace↔logs
provisionnée (§21). Tout reste **non bloquant** et **sans accumulation worker**
(garanties 7a, préservées par l'auto-instrumentation : mêmes pipelines globaux).

## Stack local (`compose.observability.yaml`)

```bash
# 1. Démarrer le stack d'observabilité (réseau friday-duck-observability).
docker compose -f compose.observability.yaml up -d

# 2. Démarrer l'app en pointant son OTLP sur le Collector (port publié sur l'hôte).
OTEL_EXPORTER_OTLP_ENDPOINT=http://host.docker.internal:4318 make up

# UIs :  Grafana http://localhost:3000 · Prometheus http://localhost:9090
```

Config versionnée sous `observability/` (collector, tempo, prometheus, loki,
datasources Grafana).

### Hôte sans ext

En prod, l'image embarque l'extension PECL `opentelemetry` (Dockerfile) et
l'auto-instrumentation est active. Sur l'hôte de dev et en CI, l'ext est ABSENTE :
les paquets `open-telemetry/opentelemetry-auto-*` émettent alors un
`E_USER_WARNING` **à l'autoload Composer** — donc trop tôt pour être neutralisé via
`.env*` (chargés par Symfony après l'autoload). On le fait taire avec une VRAIE
variable d'environnement, `OTEL_PHP_DISABLED_INSTRUMENTATIONS=all`, posée :

- dans le **Makefile** (`export`, couvre `make test|qa|stan|…`) ;
- dans le **job CI** (`.github/workflows/php.yml`, `env:` au niveau du job).

Les conteneurs Docker n'héritent pas de cette variable (elle reste sur l'hôte) :
ils gardent l'instrumentation active. En invocation directe hors `make`, exporter
la variable dans le shell reproduit le silence.

## Vérification bout-en-bout

1. **Trace d'un café (Tempo).** Offrir un café puis, dans Grafana → Explore →
   Tempo, chercher `service.name=friday-duck` : une trace `HTTP POST` couvrant
   `coffee.contribution.validate` → `energy.recalculate` → `coffee.contribution.persist`,
   `db.client.operation.duration` (auto Doctrine). Le **relais** produit
   `mercure.update.publish`, **enfant de la trace requête** via le `traceparent`
   porté par la ligne outbox (frontière async franchie, visible).
2. **Métriques (Prometheus).** `http://localhost:9090` → requêter `duck_energy`,
   `duck_coffee_total`, `db_client_operation_duration_*`, `messenger_message_processed_*`,
   `worker_memory_bytes`, `mercure_publish_count_total`.
3. **Logs corrélés (Loki).** Depuis un span Tempo, « Logs for this span » ouvre Loki
   filtré sur le `trace_id` ; les logs portent `trace_id`/`span_id` (bridge Monolog).
4. **Front (santé).** Les 5 métriques `duck.animation.*` / `duck.mercure.*` /
   `duck.svg.missing_target` arrivent via `POST /api/telemetry` → Prometheus.

## Spans métier (§26.2)

`friday.current.resolve`, `coffee.contribution.validate`,
`coffee.contribution.persist`, `energy.recalculate`, `accessory.vote.close`,
`mercure.update.publish`, …

## Métriques (§26.3–§26.5)

- **Techniques :** durée/nombre de requêtes HTTP, erreurs, durée DB, Messenger
  (traités/échoués), mémoire worker, publications Mercure.
- **Métier :** `duck.energy`, `duck.coffee.total`, `duck.overcaffeination.total`,
  `duck.accessory.winner`, `duck.friday.unique_visitors`, …
- **Front (minimal) :** init Theatre.js, état connexion Mercure, cibles SVG
  manquantes — sans donnée personnelle inutile.

## Alertes (§26.7)

Critique : « Nous sommes vendredi, mais le canard est DORMANT. » Énergie hors
`0–100`. Vote ouvert après l'heure de clôture. Taux d'échec Mercure / Theatre.js.
Mémoire worker en hausse durable.

## Dashboards & alertes (7c)

Tout provisionné sous `observability/` :

- **Dashboards** (§26.6) : « Métier » (vendredi actif, énergie, état, cafés,
  vitesse, visiteurs, vote, conseil/réactions) et « Technique » (erreurs/latence
  HTTP, mémoire worker, Mercure, backlog/file d'échec, divergence, APP_FAKE_NOW,
  santé front, **café tracé Tempo**). Grafana <http://localhost:3000>.
- **Alertes** (§26.7) : règles Prometheus (`prometheus-rules.yml`) routées par
  **Alertmanager**, chacune avec seuil + `for:` (anti-fatigue), severity et lien
  runbook. **Dead-man switch** (`TelemetryPipelineSilent`, `CollectorScrapeDown`) :
  un pipeline mort est lui-même alerté, jamais confondu avec « tout va bien ».
- **Diagnostic** : `DiagnosticsMetricsEmitter` (tick/minute) expose les jauges
  rendant ces alertes possibles (divergence horloge/statut, backlog, file d'échec,
  APP_FAKE_NOW) — la divergence est un **signal**, jamais une correction (inv. B).
- **Procédure de test des alertes** + marche à suivre par alerte : `runbook.md`.

## e2e observabilité (8b) — exécuté pour de vrai

La 7 a posé l'instrumentation ; la 7b/7c étaient validées STATIQUEMENT. La 8b a
monté la chaîne COMPLÈTE et prouvé, en exécution réelle, que les signaux
atterrissent et qu'une alerte se déclenche. Stack dédiée :
`compose.observability-e2e.yaml` (app + VRAI worker Messenger + relais + bases
isolées, image AVEC l'ext PECL, OTLP → Collector du réseau d'observabilité).

```bash
docker compose -f compose.observability.yaml up -d                 # backends
docker compose -f compose.observability-e2e.yaml up -d --build --wait
bash observability/verify-e2e.sh                                   # assertions
# Alerte AppFakeNowInProduction (noyau PROD + APP_FAKE_NOW présent) :
docker compose -f compose.observability-e2e.yaml --profile fakenow up -d --wait
```

**Prouvé en exécution réelle :**

- **Café tracé bout-en-bout (Tempo).** Une seule trace `HTTP POST` couvre
  `visitor.resolve` → `friday.current.resolve` → `coffee.contribution.validate` →
  `energy.recalculate` → `coffee.contribution.persist` → `INSERT outbox` →
  `Doctrine::commit` (+ auto-instrumentation Doctrine). Le span du relais
  `mercure.update.publish`, émis par un PROCESS CLI séparé (`app:outbox:relay`),
  s'y rattache en ENFANT via le `traceparent` porté par la ligne outbox — frontière
  async franchie et visible (vérif jamais exécutée en 7).
- **Métriques (Prometheus).** Métier `duck_coffee_total`/`duck_energy`,
  auto-instrumentée `http_server_request_duration_*` (auto Symfony), jauges de
  diagnostic `worker_memory_bytes`, `mercure_outbox_backlog`,
  `duck_clock_friday_active`, `mercure_publish_count`.
- **Logs corrélés (Loki).** Les logs portent `trace_id`/`span_id` (pont Monolog),
  requêtables par trace.
- **Alertes Firing (réelles, pas juste valides).** `AppFakeNowInProduction`
  (Pending→Firing dès la jauge `duck_app_fake_now_active{environment="prod"}=1`) et
  `CollectorScrapeDown` (dead-man switch, Firing après `for:5m` collector arrêté).

**Corrections de dette livrées (8b) :**

- `symfony/doctrine-messenger` était MANQUANT → aucun worker Messenger réel ne
  pouvait tourner (la 8a relayait via `app:outbox:relay`, jamais via un worker).
  Installé : le transport `async`/`failed` et le Scheduler sont enfin consommables.
- Flush télémétrie sur `ConsoleEvents::TERMINATE` (`TelemetryWorkerSubscriber`) :
  sans lui, une commande COURTE (`app:outbox:relay`) créait le span du relais puis
  le PERDAIT à la sortie du process. C'est ce qui masquait `mercure.update.publish`.
- Monolog `when@preprod` : preprod n'avait AUCUN handler → aucun log OTLP. Ajout du
  pont `otel` pour que l'env pré-prod (e2e) émette les logs corrélés comme la prod.

**Routage retries → `failed` (B4) : RÉSOLU et PROUVÉ (§25.4).** Les messages de
cycle sont désormais REDISPATCHÉS sur `async` (`Schedule` enveloppe `RunCycleStep`
dans un `RedispatchMessage('async')`), au lieu d'être traités en ligne sur
`scheduler_default`. La marque de dédup ET l'écriture outbox de l'annonce sont
rendues ATOMIQUES (une `transactional()` dans `OpenFriday`/`PublishWinner`/
`CloseFriday`), pour que le rejeu reste exactement-une-fois. Prouvé avec un VRAI
worker (déclenchement via `app:cycle:dispatch PublishFridayOpened`, écriture outbox
bloquée par un trigger SQL) :

- le `RunCycleStep` échoue, est REJOUÉ (#1 1s, #2 2s, #3 4s), puis — retries épuisés
  — « Removing from transport after **3 retries** » et « **Rejected … sent to the
  failure transport** » → ligne en `queue_name='failed'`, visible via
  `messenger:failed:show` (PLUS « 0 retries ») ;
- exactement-une-fois préservé : pendant l'échec `processed_message` reste VIDE
  (marque rollback atomique) ; après `messenger:failed:retry`, l'annonce sort UNE
  fois (1 ligne outbox `FRIDAY_OPENED`, 1 marque, 0 en échec).

Le transport `async` (DSN `auto_setup=0` en prod) est désormais possédé par la
migration `Version20260628130000` (`messenger_messages`). Le filet `app:friday:repair`
(sync, idempotent) couvre toujours « worker async down ».

**Limites honnêtes restantes (assumé, NON prouvé vert) :**

- Métriques auto-instrumentées : `db.client.operation.duration` /
  `messenger.message.processed` ne sont PAS émises par les versions d'auto-instr
  installées (elles produisent des SPANS, pas ces métriques). La métrique
  auto-instrumentée réellement émise est `http_server_request_duration` (auto Symfony).
- `OutboxBacklogStuck` (`min_over_time[15m] > 20`) non déclenché : fenêtre de 15 min
  incompatible avec un e2e court ; déclenchement local long-run uniquement.

**CI :** non branché par défaut (empreinte ~7 conteneurs + 2 bases + 6 backends) →
lancement LOCAL documenté ci-dessus, conformément au périmètre 8b.

## À compléter

- [x] ~~Router les messages de cycle par `async` pour exercer retries → `failed`~~ — fait (8b/B4)
- [ ] Récepteur Alertmanager réel (Slack/e-mail) — Phase 9
- [ ] Déploiement opérationnel du stack (hors local) — Phase 9
