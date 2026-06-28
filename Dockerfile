#syntax=docker/dockerfile:1

# ─────────────────────────────────────────────────────────────────────────────
# Le Canard du Vendredi — image FrankenPHP multi-stage (§31.4).
#
# La version PHP de l'image est ALIGNÉE sur la version de développement (8.5)
# pour éviter le drift dev/prod (cf. CLAUDE.md « invariants »). Les extensions
# requises par Symfony + pdo_pgsql / intl / opcache sont fournies par
# install-php-extensions (docker-php-extension-installer, embarqué dans l'image).
#
# Stages :
#   frankenphp_base          — socle commun (extensions, Caddyfile, entrypoint)
#   frankenphp_dev           — outillage dev (Xdebug, watch), code monté
#   frankenphp_prod          — sans outils de dev, version exposée, HEALTHCHECK
# ─────────────────────────────────────────────────────────────────────────────

# ─── Build des assets front (Vite) ───────────────────────────────────────────
# Le front (public/build) est gitignoré ET assets/ est hors contexte en dev :
# l'image prod DOIT le compiler elle-même (§31.4 « assets compilés »), sinon un
# `git reset --hard` sur le VPS laisse l'image sans front. `@theatre/studio` est
# tree-shaké du build prod (§15.4) — vérifié par la CI front.
FROM node:22-slim AS asset_builder

WORKDIR /app

# Manifeste d'abord (couche npm cachée tant que les deps ne bougent pas).
COPY --link package.json package-lock.json ./
RUN npm ci
# Puis les sources strictement nécessaires au build.
COPY --link assets ./assets
COPY --link vite.config.ts tsconfig.json ./
RUN npm run build

FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream

# ─── Base ────────────────────────────────────────────────────────────────────
FROM frankenphp_upstream AS frankenphp_base

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

WORKDIR /app

# Dépendances système persistantes + extensions PHP.
# hadolint ignore=DL3008
RUN <<-EOF
	apt-get update
	apt-get install -y --no-install-recommends \
		file \
		git
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		opentelemetry \
		pdo_pgsql \
		zip
	rm -rf /var/lib/apt/lists/*
EOF

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

# Health check d'infrastructure : l'endpoint d'admin Caddy (port 2019) répond
# dès que le serveur applicatif est debout. La sonde métier /health (app + base)
# est servie séparément par l'application (§31.4, Presentation/Http).
HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# ─── Dev ─────────────────────────────────────────────────────────────────────
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
# Pas de worker en dev : FRANKENPHP_CONFIG reste VIDE → FrankenPHP sert en mode
# classique, le kernel (routeur compris) est reconstruit à chaque requête. Aucun
# état en RAM = aucune route périmée après une modif (§22.2). Le worker n'est
# activé qu'en prod (stage frankenphp_prod).

RUN <<-EOF
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
	install-php-extensions xdebug
	git config --system --add safe.directory /app
EOF

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

# CMD hérité de la base (`frankenphp run`, sans worker ni --watch).

# ─── Prod ────────────────────────────────────────────────────────────────────
# Image de production (§31.4) : sans outils de dev, version exposée, health check,
# assets compilés (stage asset_builder), config env compilée (.env.local.php).
# NOTE : le durcissement DÉFENSIF restant (non-root, base minimale, surface
# réduite) = phase suivante.
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod
# Worker FrankenPHP ACTIVÉ en prod (kernel en RAM, perf). Mécanisme standard
# symfony-docker : FRANKENPHP_CONFIG alimente le bloc `frankenphp {}` du Caddyfile.
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Installer les vendors d'abord (cache des couches inchangées) puis le code.
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . ./
# Assets compilés depuis le stage Node (entrypoints.json lu par pentatrion/vite-bundle).
COPY --link --from=asset_builder /app/public/build ./public/build

# Compile l'autoload, la config env (.env.local.php) et le cache Symfony.
#
# `composer dump-env prod` fige UNIQUEMENT APP_ENV=prod dans `.env.local.php`
# (source = .env vide) : Symfony le lit en priorité et `bootEnv()` retourne sans
# exiger de `.env` physique au runtime. Les variables réelles injectées sur le
# VPS (DATABASE_URL, APP_SECRET, MERCURE_*, …) PRIMENT sur ce fichier (populate
# ne réécrit pas une variable déjà présente). AUCUN secret, AUCUN APP_FAKE_NOW
# n'est gravé — un garde-fou de build le vérifie et casse la construction sinon.
RUN <<-EOF
	mkdir -p var/cache var/log var/share
	composer dump-autoload --classmap-authoritative --no-dev
	# `.env` vide = placeholder de premier chargement Symfony (le projet n'en
	# committe pas). En prod, `.env.local.php` court-circuite et il n'est jamais
	# lu ; il évite seulement que `bootEnv()` lève une exception si l'image est
	# réutilisée hors `prod` (ex. e2e en `preprod`). dump-env le prend pour source.
	touch .env
	composer dump-env prod
	# Garde-fou : rien de sensible ni de falsifiable ne doit être figé dans l'image.
	if grep -qiE 'APP_FAKE_NOW|APP_SECRET|DATABASE_URL|MERCURE_JWT|MERCURE_PUBLISHER|MERCURE_SUBSCRIBER|POSTGRES_PASSWORD' .env.local.php; then
		echo 'SECURITY: a secret or APP_FAKE_NOW leaked into .env.local.php — aborting build.' >&2
		cat .env.local.php >&2
		exit 1
	fi
	# Cache prod chaud au build. Les warmers Symfony exigent que les variables
	# requises soient DÉFINIES (pas résolues) : on fournit des valeurs FACTICES
	# scopées à cette seule commande (jamais des ENV de couche, jamais connectées).
	# Le conteneur compilé ne stocke que des placeholders %env()% résolus au
	# RUNTIME — aucune de ces valeurs n'est gravée dans l'image (recette officielle
	# symfony-docker). Le garde-fou ci-dessus a déjà vérifié `.env.local.php`.
	APP_SECRET=__build__ \
	DATABASE_URL='postgresql://build:build@127.0.0.1:5432/build?serverVersion=17&charset=utf8' \
	MERCURE_URL='http://localhost/.well-known/mercure' \
	MERCURE_PUBLIC_URL='http://localhost/.well-known/mercure' \
	MERCURE_JWT_SECRET=__build__ \
	DEFAULT_URI='http://localhost' \
	MESSENGER_TRANSPORT_DSN='doctrine://default?auto_setup=0' \
	php bin/console cache:clear --no-debug
	chmod +x bin/console
	chmod -R g=u var
	sync
EOF

# Version exposée (/health, Grafana). PLACÉE EN DERNIER À DESSEIN : elle change à
# chaque commit ; la garder ici évite d'invalider les couches coûteuses (vendors,
# code, cache) à chaque déploiement. Lue au RUNTIME, non requise par le build.
ARG APP_VERSION=0.0.0-dev
ENV APP_VERSION=${APP_VERSION}
