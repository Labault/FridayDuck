# Runbook

> Couvre le cycle proactif (6a) et l'outbox temps réel (6b). Dashboards/alertes et
> sauvegardes restent à compléter (Phase 7/9). Référence faisant foi :
> [cahier des charges](cdc_friday_duck.md) (§25.4, §26.7, §37.4).

## Philosophie

Le Scheduler déclenche des commandes mais **ne constitue pas l'unique preuve de
l'état** : une requête le vendredi peut créer ou réparer l'édition, un vote après
14 h est refusé par la règle métier même si la clôture a pris du retard, et un
rattrapage est toujours possible (§25.2).

## Incidents fréquents

### « Vendredi mais canard DORMANT » (critique)

1. Vérifier l'horloge serveur et le fuseau `Europe/Paris`.
2. Vérifier que `APP_FAKE_NOW` n'est pas positionné en production (§7.4).
3. Déclencher/rejouer la commande de préparation de l'édition.

### Vote toujours ouvert après 14 h

1. La règle métier doit déjà refuser les votes. Vérifier la commande de clôture.
2. Rejouer `accessory-close:<date>` (idempotent, §25.3).

### Mémoire worker en hausse

1. Vérifier l'absence d'état partagé entre requêtes (services *stateless*).
2. Recycler les workers FrankenPHP.

## Cycle proactif (Phase 6a, §25.1)

```bash
# Worker : génère les messages récurrents du cycle + traitement async.
bin/console messenger:consume scheduler_default async -vv
# Préparer les tables de transport Doctrine (une fois).
bin/console messenger:setup-transports
```

Étapes (heure murale Europe/Paris) : jeudi 23:55 préparer ; vendredi 00:00 ouvrir ;
14:00 clore le vote ; 14:01 publier le gagnant ; 23:55 préparer le bilan ;
samedi 00:00 fermer ; 00:05 générer le bilan.

## Rattrapage après un Scheduler indisponible (§25.2, invariant D)

```bash
bin/console app:friday:repair 2026-07-03
```

Amène l'édition à l'état correct selon l'horloge (prépare ; clôt le vote si après
14:00 ; ferme si après samedi minuit) et émet les annonces MANQUANTES une fois.
Sûr à rejouer — il invoque le MÊME aiguilleur de cycle que le Scheduler.

## File d'échec Messenger (§25.4)

- Inspecter : `bin/console messenger:failed:show` puis `… <id> -vv` (cause).
- Rejouer : `bin/console messenger:failed:retry` — écarter : `messenger:failed:remove <id>`.
- **Éviter un doublon au rejeu** : les annonces de cycle sont dédupliquées par clé
  (`processed_message` : `friday-open:<date>`, `accessory-winner:<date>`,
  `friday-close:<date>`) et le bilan par `UNIQUE(iso_week)`. Un message rejoué ne
  ré-applique donc rien.

## Outbox transactionnel (Phase 6b, §20.6)

Tout événement temps réel (énergie, vote, conseil, annonces de cycle) est écrit
dans la table `outbox` DANS la transaction métier (atomique). Un **relais** le
publie ensuite sur Mercure, en ordre, race-safe, et marque `published_at`
(at-least-once : un doublon éventuel est absorbé par la barrière de version et les
clés d'action côté front).

```bash
# Relais en boucle (basse latence) — à défaut d'un worker dédié.
bin/console app:outbox:relay            # un passage ; code retour ≠ 0 si un échec
# Worker temps réel recommandé en prod (le Scheduler ne sert que de filet/minute) :
bin/console messenger:consume scheduler_default async -vv
```

### Inspecter le backlog (lignes non publiées)

```sql
SELECT id, friday_date, type, attempts, created_at
FROM outbox WHERE published_at IS NULL ORDER BY id;          -- backlog en ordre
SELECT count(*) FROM outbox WHERE published_at IS NULL;       -- profondeur
SELECT id, attempts FROM outbox WHERE published_at IS NULL AND attempts > 0;  -- en souffrance
```

### Rejouer / diagnostiquer un relais bloqué

1. **Hub Mercure down** : les lignes restent non publiées (`attempts` croît), le
   message `RelayOutbox` part en file d'échec après retries (§25.4). Rétablir le
   hub puis `app:outbox:relay` (ou laisser le worker reprendre) : rien n'est perdu.
2. **Relais figé** : vérifier qu'aucune transaction ne détient un verrou long
   (`SELECT * FROM pg_locks`…) ; le relais utilise `FOR UPDATE SKIP LOCKED`, donc
   plusieurs relais coexistent sans double publication.
3. **Rejeu manuel d'une ligne** : repasser `published_at` à NULL la republiera au
   prochain passage (at-least-once, sûr — le front déduplique).

### Purger les lignes publiées anciennes

```sql
DELETE FROM outbox WHERE published_at IS NOT NULL AND published_at < now() - interval '7 days';
```

> Métriques (`mercure.publish.count` / `.failure`, profondeur du backlog) : points
> d'instrumentation posés (`RelayMetrics`), export branché en Phase 7.

## Alertes (§26.7, Phase 7c)

Stack : `docker compose -f compose.observability.yaml up -d`. Règles dans
`observability/prometheus-rules.yml` (routées via Alertmanager). Dashboards :
**Grafana** <http://localhost:3000> → « Le Canard du Vendredi — Métier / Technique ».
Alertes en cours : **Prometheus** <http://localhost:9090/alerts>.

> **Tester une alerte** (à faire une fois + après chaque modif) : simuler la
> condition, attendre le `for:`, vérifier le passage `Pending → Firing` dans
> Prometheus. Ex. : `OutboxBacklogStuck` → arrêter le worker relais et offrir des
> cafés ; `AppFakeNowInProduction` → poser `APP_FAKE_NOW` avec `APP_ENV=prod` ;
> `TelemetryPipelineSilent` → arrêter le Collector ; `EnergyOutOfRange` →
> injecter une valeur hors borne en base.

### Vendredi mais canard DORMANT

Dashboard Métier → « Vendredi actif ». L'horloge dit AWAKE mais l'édition n'est
pas ouverte. Vérifier l'horloge serveur + `Europe/Paris`, que `APP_FAKE_NOW` n'est
pas posé en prod, puis `bin/console app:friday:repair <date>` (rattrapage §25.2).

### Energie hors intervalle

Dashboard Métier → « Énergie ». `duck_energy` < 0 ou > 100 = bug de calcul.
Inspecter `friday_edition.energy` et les derniers `coffee_contribution` ; ne pas
« corriger » à la main sans comprendre la cause (le verrou sérialise normalement).

### Vote ouvert apres cloture

Vendredi après 14:00 sans gagnant figé. `bin/console app:friday:repair <date>`
(clôt le vote + publie le gagnant, idempotent). Vérifier le Scheduler `CloseVote`.

### Divergence horloge EditionStatus

**SIGNAL, jamais correction** (inv. B 6a) : le statut persisté contredit l'horloge.
Le statut n'est PAS autoritaire — aucune décision runtime n'en dépend. Investiguer
pourquoi le Scheduler/rattrapage n'a pas fait progresser le statut ; lancer
`app:friday:repair <date>`. Ne JAMAIS écrire le statut « à la main » pour faire
taire l'alerte.

### Backlog outbox

Dashboard Technique → « Backlog outbox ». Le relais n'écoule plus la file. Voir
[Rejouer / diagnostiquer un relais bloqué](#rejouer--diagnostiquer-un-relais-bloqué)
ci-dessus (hub down, verrou long, worker arrêté).

### File dechec Messenger

Voir [File d'échec Messenger](#file-déchec-messenger-254) : `messenger:failed:show`,
diagnostiquer la cause, `messenger:failed:retry` (dédup garanti).

### Echecs publication Mercure

`mercure_publish_failure` croît : hub Mercure injoignable. Vérifier le hub
(co-localisé Caddy/FrankenPHP), les JWT, le réseau. L'outbox conserve les
événements → rien n'est perdu, ils partent au rétablissement.

### App fake now en prod

**Critique sécurité** (§7.4) : `APP_FAKE_NOW` rend l'horloge falsifiable. Retirer
la variable de l'environnement de prod IMMÉDIATEMENT et redéployer. Ne doit jamais
arriver (garde-fou `FakeClockProductionGuard`).

### Erreurs HTTP

Dashboard Technique → « Erreurs HTTP 5xx » + « Latence p95 ». Corréler avec les
traces Tempo (spans en erreur) et les logs Loki par `trace_id`.

### Memoire worker

Dashboard Technique → « Mémoire worker ». Croissance durable = fuite d'état entre
requêtes (§22.2). Vérifier l'absence d'état partagé ; recycler les workers FrankenPHP.

### Echecs animation front / Reconnexions Mercure front

Santé front (§26.5). Échecs Theatre.js ou canal temps réel instable. Vérifier le
bundle (Studio jamais livré en prod, §15.4), le hub Mercure, la console navigateur.

### Pipeline telemetrie muet

**Dead-man switch (danger Y)** : plus aucune jauge de diagnostic reçue → Collector
mort, worker arrêté, ou pipeline qui jette les signaux. **Tant que ceci dure, les
autres alertes « valeur > seuil » sont AVEUGLES** (faux calme). Vérifier le
Collector (`docker compose -f compose.observability.yaml ps`/`logs`), le worker
Scheduler (`messenger:consume`), et `OTEL_EXPORTER_OTLP_ENDPOINT`.

## Déploiement & rollback prod (Phase 9, §31.5, §37.4)

Mise en ligne par **push-to-deploy** : `git push main` → `git reset --hard` →
`deploy.sh` (build → dump pré-migration → migrations gate → up → **healthcheck
bloquant**). Détail complet : [deployment.md](deployment.md). Topologie :
`compose.prod.yaml` (app web+hub Mercure, `database`, **worker** `messenger:consume`).

### Le déploiement a échoué (healthcheck rouge)

`deploy.sh` a **déjà roll-back** vers l'image précédente (`:rollback`) et rendu un
code ≠ 0. Diagnostiquer AVANT de relancer :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml logs --tail=100 app
docker compose --env-file .env.prod.local -f compose.prod.yaml ps
```

### Rollback manuel (< 1 min, sans rebuild)

```bash
docker tag friday-duck/app:rollback friday-duck/app:latest
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d --no-build app worker
```

### Annuler une migration

Restaurer le dump pré-migration (`backups/pre-migrate-*.sql`) PUIS redéployer
l'image précédente (ci-dessus) :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml exec -T database \
  sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"' < backups/pre-migrate-<ts>.sql
```

### Smoke test post-déploiement

```bash
./scripts/smoke-test.sh https://tibec.labault.dev
```

`/health` vert, `/api/friday/current` cohérent avec l'horloge réelle (preuve
qu'`APP_FAKE_NOW` n'est pas figé), hub Mercure joignable en HTTPS.

### Mercure muet en prod (café non propagé)

1. Le **worker** tourne-t-il ? `compose ps worker` ; sinon `compose up -d worker`.
2. `MERCURE_PUBLIC_URL` est-il bien l'URL **HTTPS publique** (pas un nom interne) ?
3. Hub joignable de l'extérieur : `curl -N https://tibec.labault.dev/.well-known/mercure?topic=x`
   doit rendre `200 text/event-stream`. Sinon : certificat Caddy, JWT, réseau.
4. L'outbox conserve tout : au rétablissement, le relais republie (rien n'est perdu).

## Tests e2e Playwright (Phase 8a)

Valident la chaîne RÉELLE clic→serveur→outbox→**relais**→Mercure→navigateur contre
la stack applicative (`compose.e2e.yaml` : app+ext PECL, Postgres isolé `app_e2e`,
hub Mercure, **worker de relais actif** — sans lui, un café cross-onglets n'arrive
jamais et le test échoue pour une raison d'infra).

```bash
npm ci
npm run e2e:up      # build vite PROD + monte la stack (app x3 horloges + PG + relais)
npm run e2e         # Playwright contre localhost:8081/8082/8083
npm run e2e:down    # arrêt + purge volumes
```

Déterminisme temporel : 3 instances à `APP_FAKE_NOW` fixe (vendredi avant 14:00 →
:8081 ; après 14:00 → :8082 ; non-vendredi → :8083), sélectionnées par « project »
Playwright. `APP_ENV=preprod` : l'horloge configurable honore `APP_FAKE_NOW` (en
`prod` elle serait neutralisée, §7.4) tout en servant le build front PROD (Studio
absent, §15.4). En CI : `.github/workflows/e2e.yml` (artefacts Playwright à l'échec).

## À compléter

- [x] Procédure de rollback documentée et répétée (§37.4) — voir ci-dessus.
- [ ] Récepteur Alertmanager réel (Slack/e-mail) — déploiement obs. sur le VPS.
- [ ] Sauvegardes PostgreSQL automatisées (au-delà du dump pré-migration de `deploy.sh`).
