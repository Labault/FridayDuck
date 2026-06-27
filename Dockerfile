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
# Worker en mode "watch" : le kernel persiste entre requêtes mais redémarre dès
# qu'un fichier change — confort de dev sans sacrifier le mode worker (§22.2).
ENV FRANKENPHP_WORKER_CONFIG=watch

RUN <<-EOF
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
	install-php-extensions xdebug
	git config --system --add safe.directory /app
EOF

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch"]

# ─── Prod ────────────────────────────────────────────────────────────────────
# NOTE : durcissement complet de l'image prod (non-root, base minimale, compile
# des assets, smoke test) = Phase 9 (§31.5, hors périmètre de cette session).
# Ce stage est buildable, sans outils de dev, expose la version et un health
# check — strict nécessaire de §31.4.
FROM frankenphp_base AS frankenphp_prod

ARG APP_VERSION=0.0.0-dev
ENV APP_ENV=prod
ENV APP_VERSION=${APP_VERSION}

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Installer les vendors d'abord (cache des couches inchangées) puis le code.
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . ./

# NOTE Phase 9 (§31.5) : `composer dump-env prod` (secrets), `cache:clear` +
# `cache:warmup` et compilation des assets seront ajoutés au durcissement de
# l'image prod. Ici on se limite à un autoload optimisé : le cache se construit
# à la première requête. L'image reste buildable sans `.env` (secret non livré).
RUN <<-EOF
	mkdir -p var/cache var/log var/share
	composer dump-autoload --classmap-authoritative --no-dev
	chmod +x bin/console
	chmod -R g=u var
	sync
EOF
