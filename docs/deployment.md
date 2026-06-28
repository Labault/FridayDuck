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
dans [`.env.prod.dist`](../.env.prod.dist). Sur le VPS, une seule fois :

```bash
cp .env.prod.dist .env.prod.local      # renseigner les secrets (APP_SECRET, DATABASE_URL,
chmod 600 .env.prod.local         #   MERCURE_JWT_SECRET, POSTGRES_PASSWORD…)
```

`.env.prod.local` est l'**env-file d'interpolation de Compose** (distinct du `.env`
de Symfony — aucune collision) ; `deploy.sh` le passe via `--env-file`.

## Reverse-proxy, TLS et Mercure

Le hub Mercure est **co-localisé** dans le process Caddy/FrankenPHP (§21) — pas de
conteneur dédié. En prod (`compose.prod.yaml`, service `app`) :

- `SERVER_NAME="canard.labault.dev, app:80"` → Caddy provisionne **automatiquement**
  le certificat **Let's Encrypt** pour le domaine public, **plus** un vhost interne
  `app:80` (HTTP) pour la publication Mercure et le healthcheck.
- Les certificats persistent dans le volume **`caddy_data`** (sinon ré-émission à
  chaque déploiement → risque de rate-limit Let's Encrypt).
- `MERCURE_PUBLIC_URL=https://canard.labault.dev/.well-known/mercure` — **l'URL
  HTTPS publique RÉELLE** jointe par l'EventSource du navigateur. ⚠️ Jamais un nom
  de service interne (le piège exact rencontré en e2e). `MERCURE_URL=http://app/...`
  reste l'URL interne de publication (app + worker).

Prérequis externe : l'enregistrement DNS `canard.labault.dev` doit pointer sur l'IP
du VPS **avant** le premier déploiement (Let's Encrypt valide via HTTP-01).

## Flux de déploiement (`deploy.sh`, push-to-deploy)

`git push main` → webhook signé → `git reset --hard origin/main` → `deploy.sh`.
Ordre **impératif** (rollback prêt AVANT toute bascule de trafic) :

1. **Préserver** l'image en cours sous `friday-duck/app:rollback` (filet, avant build).
2. **Dump PostgreSQL** pré-migration dans `backups/` (filet base).
3. **Build** de l'image prod (assets + `.env.local.php`).
4. **Base up + migrations** Doctrine `--no-interaction --all-or-nothing` (**gate** :
   un échec stoppe le déploiement).
5. **app + worker up**.
6. **Healthcheck HTTP BLOQUANT** sur `/health` (3 couches, base incluse) : tant
   qu'il n'est pas **vert**, aucun succès déclaré. **Rouge → rollback automatique,
   sortie en erreur.**

Le **worker** (`messenger:consume scheduler_default async`) est indispensable : il
relaie l'outbox vers Mercure et joue les étapes de cycle. Sans lui, un café est
committé mais jamais diffusé (§20.6).

## Smoke test (§31.5)

Après mise en ligne, **distinct** du healthcheck (qui *gate*) :

```bash
./scripts/smoke-test.sh https://canard.labault.dev
```

Vérifie le comportement RÉEL : `/health` (200, base up, version), `/api/friday/current`
**cohérent avec l'horloge réelle** (Europe/Paris — prouve qu'`APP_FAKE_NOW` n'est pas
figé), et le **hub Mercure joignable en HTTPS** (abonnement SSE anonyme → 200).

## Rollback (< 1 min)

`deploy.sh` **roll-back automatiquement** si le healthcheck est rouge. Rollback
**manuel** (revenir à l'image précédente sans rebuild) :

```bash
docker tag friday-duck/app:rollback friday-duck/app:latest
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d --no-build app worker
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
