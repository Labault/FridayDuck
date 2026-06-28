# Déploiement

> Référence faisant foi : [cahier des charges](cdc_friday_duck.md) (§31, §37.4).
> Mise en ligne applicative (Phase 9). Le durcissement défensif (non-root, base
> minimale) et le déploiement de l'observabilité sur le VPS suivent.

## CI/CD (§31)

- **Backend :** `composer validate`, install, formatage, PHPStan, tests
  unitaires/intégration, audit, migrations de test.
- **Front :** install, TypeScript, lint, tests, vérification import état
  Theatre.js, optimisation SVG, validation des IDs obligatoires, build,
  **contrôle de l'absence de `@theatre/studio`** dans le bundle public.
- **End-to-end :** Docker Compose, horloge simulée, Playwright, captures.

GitHub Actions : voir `.github/workflows/`.

## Image de production (§31.4)

`Dockerfile`, stage `frankenphp_prod` — FrankenPHP en mode worker, sans outils de
dev, **version exposée**, **health check**, et :

- **Assets compilés DANS l'image.** Le stage `asset_builder` (Node) fait
  `npm ci && npm run build` → `public/build/` est copié dans l'image. `public/build`
  est gitignoré : sans ce stage, un `git reset --hard` sur le VPS laisserait l'image
  sans front. `@theatre/studio` est tree-shaké du build prod (§15.4).
- **Config env compilée.** `composer dump-env prod` génère `.env.local.php` qui
  fige **uniquement `APP_ENV=prod`** : `bootEnv()` le lit en priorité et **n'a pas
  besoin d'un `.env` peuplé** au runtime (un `.env` vide subsiste comme simple
  placeholder de premier chargement, jamais lu en `prod`). **Aucun secret** n'est
  gravé ; un garde-fou de build casse la construction si un secret ou `APP_FAKE_NOW`
  fuit dans le fichier.
- **`APP_FAKE_NOW` neutralisé en production** (§7.4) : absent de l'image, absent de
  l'environnement, et de toute façon ignoré par l'horloge en `APP_ENV=prod`.

## Variables d'environnement runtime

Toutes les variables attendues — et lesquelles sont des **SECRETS** (injectés sur
le VPS, jamais committés, jamais dans l'image) vs **PUBLIQUES** — sont documentées
dans [`.env.prod.local.dist`](../.env.prod.local.dist). Sur le VPS, une seule fois :

```bash
cp .env.prod.local.dist .env.prod.local   # renseigner les secrets (APP_SECRET, DATABASE_URL,
chmod 600 .env.prod.local                  #   MERCURE_JWT_SECRET, POSTGRES_PASSWORD…)
```

`.env.prod.local` est l'**env-file d'interpolation de Compose** (distinct du `.env`
de Symfony — aucune collision) ; `deploy.sh` le passe via `--env-file`. Le **domaine
public** se renseigne via une variable UNIQUE `APP_DOMAIN` ; `compose.prod.yaml` en
dérive `DEFAULT_URI` et `MERCURE_PUBLIC_URL`.

## Reverse-proxy, TLS et Mercure

**Le TLS est terminé par un Caddy GLOBAL EXTERNE** (≈ `~/proxy-global` sur le VPS),
partagé par toutes les apps du VPS — aligné sur Red Flag Bingo. L'app **ne gère pas
de certificat** : FrankenPHP écoute en **HTTP plain sur `:80`**, joignable uniquement
par le Caddy global via le réseau Docker partagé **`web`** (aucun port publié sur
l'hôte). Le hub Mercure reste **co-localisé** dans le process Caddy/FrankenPHP de
l'app (§21) — pas de conteneur dédié.

En prod (`compose.prod.yaml`, service `app`) :

- `SERVER_NAME=":80"` → Caddy écoute en HTTP sur toutes les interfaces, **sans
  ACME/Let's Encrypt**. `:80` matche tous les hosts : `canard_app:80` (via le Caddy
  global) comme `http://app/...` (publication Mercure interne, healthcheck).
- **Pas de volume `caddy_data`/`caddy_config`** : sans TLS, le hub n'a aucun
  certificat à persister.
- `MERCURE_PUBLIC_URL=https://$APP_DOMAIN/.well-known/mercure` — **l'URL HTTPS
  publique RÉELLE** (servie par le Caddy global) jointe par l'EventSource du
  navigateur. ⚠️ Jamais un nom de service interne (le piège exact rencontré en
  e2e). `MERCURE_URL=http://app/.well-known/mercure` reste l'URL interne de
  publication (app + worker + relais) — pas de hairpin par le proxy.

### Bloc à coller dans le Caddyfile global du VPS

Dans `~/proxy-global/Caddyfile` (calqué sur le bloc `redflagbingo.fun`) :

```caddy
tibec.labault.dev {
  import security_headers
  reverse_proxy canard_app:80 {
    header_up Host {host}
    header_up X-Real-IP {remote_host}
    header_up X-Forwarded-Proto {scheme}
  }
}
```

Puis `docker exec <conteneur-caddy-global> caddy reload --config /etc/caddy/Caddyfile`
(ou la commande de reload du proxy global). Le Caddy global doit être joint au réseau
`web` pour résoudre `canard_app`.

Prérequis externes **avant** le premier déploiement : (1) le réseau Docker partagé
existe (`docker network create web`, une fois pour le VPS) ; (2) le DNS
`tibec.labault.dev` pointe sur l'IP du VPS (le Caddy global provisionne le
certificat à la première requête).

## Flux de déploiement (`deploy.sh`, push-to-deploy)

`git push main` → webhook signé → `git reset --hard origin/main` → `deploy.sh`.
Ordre **impératif** (rollback prêt AVANT toute bascule de trafic) :

1. **Préserver** l'image en cours sous `friday-duck/app:rollback` (filet, avant build).
2. **Dump PostgreSQL** pré-migration dans `backups/` (filet base).
3. **Build** de l'image prod (assets + `.env.local.php`).
4. **Base up + migrations** Doctrine `--no-interaction --all-or-nothing` (**gate** :
   un échec stoppe le déploiement).
5. **app + worker + relay up**.
6. **Healthcheck HTTP BLOQUANT** sur `/health` (3 couches, base incluse) : tant
   qu'il n'est pas **vert**, aucun succès déclaré. **Rouge → rollback automatique,
   sortie en erreur.**

### Topologie Messenger (qui consomme quoi, et pourquoi)

Deux processus distincts, en plus de l'`app` :

- **`worker`** — `messenger:consume scheduler_default async`. Consomme **deux**
  transports :
  - `scheduler_default` (transport du Symfony Scheduler) : **déclenche** le cycle.
    Les étapes de cycle sont émises en `RedispatchMessage(RunCycleStep, 'async')`
    (correction B4, §25.4) → re-routées vers `async` pour bénéficier des retries et
    de la file `failed`. Le scheduler porte aussi le **filet** récurrent 1 min
    (`RelayOutbox` + `EmitDiagnostics`), traités **en ligne** sur ce transport.
  - `async` (table Doctrine) : traite `RunCycleStep` avec retries → `failed`.

  ⚠️ Ne PAS calquer la commande de Red Flag Bingo (qui ne consomme que
  `scheduler_default`) : sans `async`, les étapes de cycle redispatchées du Canard
  ne seraient jamais traitées.

- **`relay`** — boucle `app:outbox:relay` (sleep 1s). Relais **bas-latence** de
  l'outbox vers Mercure : c'est lui qui donne le **temps réel sub-seconde** du
  café/du vote. Le filet 1 min du `worker` n'est qu'un **rattrapage** si le relais
  tombe. **Sans worker ni relais, un café est committé mais jamais diffusé**
  (§20.6) — le piège exact vu en e2e (8b câblait déjà ces deux processus).

## Smoke test (§31.5)

Après mise en ligne, **distinct** du healthcheck (qui *gate*) :

```bash
./scripts/smoke-test.sh https://tibec.labault.dev
```

Vérifie le comportement RÉEL : `/health` (200, base up, version), `/api/friday/current`
**cohérent avec l'horloge réelle** (Europe/Paris — prouve qu'`APP_FAKE_NOW` n'est pas
figé), et le **hub Mercure joignable en HTTPS** (abonnement SSE anonyme → 200).

## Rollback (< 1 min)

`deploy.sh` **roll-back automatiquement** si le healthcheck est rouge. Rollback
**manuel** (revenir à l'image précédente sans rebuild) :

```bash
docker tag friday-duck/app:rollback friday-duck/app:latest
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d --no-build app worker relay
```

Pour annuler une **migration** : restaurer le dump pré-migration le plus récent
(`backups/pre-migrate-*.sql`) puis redéployer l'image précédente :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml exec -T database \
  sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"' < backups/pre-migrate-AAAAMMJJ-HHMMSS.sql
```

Le code est aussi réversible par `git revert` + `git push` (push-to-deploy
redéploie), mais le retag d'image est le chemin le plus court.

## Reste à faire (phases suivantes)

- [ ] Durcissement défensif de l'image (non-root, base minimale, surface réduite).
- [ ] Stack observabilité sur le VPS (Collector/Tempo/Grafana) + Alertmanager réel.
- [ ] Sauvegardes PostgreSQL automatisées et restauration testée (§37.4 — ici, seul
      le dump pré-migration de `deploy.sh` fait office de filet).
