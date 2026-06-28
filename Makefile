# Managed by bootstrap (symfony profile) — les RECETTES qualité ci-dessous sont
# celles de l'upstream (commandes inchangées). La section « PROJET » (stacks
# Docker, DB, E2E, observabilité, prod) est spécifique au Canard du Vendredi et
# à reporter après un `bootstrap reconcile`.
#
# Deux mondes :
#   • QUALITÉ  → outils de la MACHINE (vendor/bin, pre-commit), hors conteneur.
#   • STACKS   → Docker Compose. La cible nomme l'environnement (dev par défaut,
#                puis e2e-, obs-, prod-).
#
# `make` seul (ou `make help`) liste tout, groupé par section.
.DEFAULT_GOAL := help

.PHONY: help \
	qa lint fix hooks cs cs-fix stan rector rector-fix test \
	up down build rebuild restart start reset sh logs ps \
	console cc migrate migration db worker relay \
	e2e-up e2e-down e2e e2e-all \
	obs-up obs-down \
	deploy smoke prod-up prod-down prod-ps prod-logs prod-sh prod-config \
	backup-install backup-now backup-restore-test

# ── Binaire Compose & fichiers d'environnement ───────────────────────────────
# Surcharge : `make up DC="docker-compose"`.
DC ?= docker compose

COMPOSE_E2E  ?= compose.e2e.yaml
COMPOSE_OBS  ?= compose.observability.yaml
COMPOSE_PROD ?= compose.prod.yaml
PROD_ENV     ?= .env.prod.local

# Raccourcis Compose par stack (dev = compose.yaml, auto-chargé, donc $(DC) nu).
DCE = $(DC) -f $(COMPOSE_E2E)
DCO = $(DC) -f $(COMPOSE_OBS)
DCP = $(DC) --env-file $(PROD_ENV) -f $(COMPOSE_PROD)

# Silence le E_USER_WARNING d'auto-instrumentation OpenTelemetry quand l'extension
# PECL `opentelemetry` est ABSENTE (hôte de dev + CI ; le warning sort à l'autoload
# Composer, trop tôt pour .env*). Les images Docker installent l'ext et N'héritent
# PAS de cette variable. Voir docs/observability.md (« Hôte sans ext »).
OTEL_PHP_DISABLED_INSTRUMENTATIONS ?= all
export OTEL_PHP_DISABLED_INSTRUMENTATIONS

help: ## Affiche cette aide
	@awk 'BEGIN {FS = ":.*##"} \
		/^##@/ {printf "\n\033[1m%s\033[0m\n", substr($$0, 5); next} \
		/^[a-zA-Z0-9_%-]+:.*##/ {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}' \
		$(MAKEFILE_LIST)

##@ Qualité (sur la machine, hors conteneur)

qa: lint stan test ## Tout vérifier : lint + stan + test (combo)

lint: ## Joue tous les hooks pre-commit sur l'ensemble des fichiers
	pre-commit run --all-files

cs: ## Vérifie le style (PHP-CS-Fixer, dry-run)
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Corrige le style (PHP-CS-Fixer)
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix

stan: ## Analyse statique (PHPStan niveau 9)
	vendor/bin/phpstan analyse --no-progress

rector: ## Montre les refactorings (Rector, dry-run)
	vendor/bin/rector process --dry-run

rector-fix: ## Applique les refactorings (Rector)
	vendor/bin/rector process

test: ## Lance la suite de tests (Pest ou PHPUnit)
	@if [ -f vendor/bin/pest ]; then vendor/bin/pest; else vendor/bin/phpunit; fi

fix: cs-fix rector-fix ## Auto-fix style + refactorings (combo)

hooks: ## Installe les hooks git (pre-commit + commit-msg)
	pre-commit install
	pre-commit install --hook-type commit-msg

##@ Dev — stack Docker (compose.yaml : app FrankenPHP + PostgreSQL)

up: ## Build + démarre la stack dev en arrière-plan (attend le healthcheck)
	$(DC) up -d --build --wait

down: ## Arrête la stack dev et supprime les conteneurs
	$(DC) down --remove-orphans

build: ## Construit les images sans démarrer
	$(DC) build

rebuild: ## Reconstruit sans cache puis redémarre (après changement Dockerfile)
	$(DC) build --no-cache
	$(DC) up -d --wait

restart: down up ## Recycle la stack (down + up) — relit l'env au boot (combo)

start: up migrate ## Démarre la stack ET applique les migrations : prêt à coder (combo)

reset: ## ⚠ Remet à neuf : DÉTRUIT les volumes (BASE INCLUSE) + up + migrations (combo)
	$(DC) down -v --remove-orphans
	$(DC) up -d --build --wait
	$(MAKE) migrate

sh: ## Ouvre un shell dans le conteneur app
	$(DC) exec app sh

logs: ## Suit les logs du conteneur app
	$(DC) logs -f app

ps: ## Liste l'état des conteneurs de la stack dev
	$(DC) ps

##@ Dev — base de données & console (stack dev démarrée)

console: ## Lance une commande console : make console c="debug:router"
	$(DC) exec app php bin/console $(c)

cc: ## Vide le cache Symfony dans le conteneur
	$(DC) exec app php bin/console cache:clear

migrate: ## Applique les migrations Doctrine sur la base dev
	$(DC) exec app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

migration: ## Génère une migration depuis le diff des entités
	$(DC) exec app php bin/console doctrine:migrations:diff

db: ## Ouvre un shell psql sur la base dev
	$(DC) exec database sh -c 'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'

worker: ## Lance le worker Messenger (premier plan ; pas de conteneur worker en dev)
	$(DC) exec app php bin/console messenger:consume async -vv

relay: ## Lance le relais outbox→Mercure (premier plan ; temps réel en dev)
	$(DC) exec app php bin/console app:outbox:relay -vv

##@ E2E — stack Playwright (compose.e2e.yaml, pilotée par npm)

e2e-up: ## Build front + démarre la stack E2E (friday/afternoon/dormant + relay)
	npm run e2e:up

e2e-down: ## Arrête la stack E2E et supprime ses volumes
	npm run e2e:down

e2e: ## Joue les tests Playwright contre la stack E2E démarrée
	npm run e2e

e2e-all: e2e-up ## Build + run Playwright + teardown, statut propagé (combo)
	npm run e2e; status=$$?; npm run e2e:down; exit $$status

##@ Observabilité — stack locale (compose.observability.yaml : Grafana…)

obs-up: ## Démarre le stack d'observabilité (Collector + Grafana + Prometheus…)
	$(DCO) up -d --wait

obs-down: ## Arrête le stack d'observabilité
	$(DCO) down --remove-orphans

##@ Production (compose.prod.yaml + .env.prod.local — sur le VPS / répétition)

deploy: ## Déploiement complet orchestré (build→migrations→up→healthcheck bloquant)
	./deploy.sh

smoke: ## Smoke test du comportement réel sur le domaine public
	./scripts/smoke-test.sh

prod-up: ## Démarre la stack prod (app + worker + relay + database)
	$(DCP) up -d --wait

prod-down: ## Arrête la stack prod
	$(DCP) down --remove-orphans

prod-ps: ## État des conteneurs prod
	$(DCP) ps

prod-logs: ## Suit les logs prod (app + worker + relay)
	$(DCP) logs -f app worker relay

prod-sh: ## Ouvre un shell dans le conteneur app prod
	$(DCP) exec app sh

prod-config: ## Affiche la config prod résolue (vérifie l'interpolation des secrets)
	$(DCP) config

##@ Sauvegardes PostgreSQL (sur le VPS — restic → Storage Box, §37.4)

backup-install: ## Installe les units/scripts de sauvegarde sur l'hôte (root, VPS)
	sudo ops/backup/install.sh

backup-now: ## Lance une sauvegarde immédiate (via le service systemd)
	sudo systemctl start canard-backup.service
	sudo journalctl -u canard-backup.service -n 30 --no-pager

backup-restore-test: ## Teste la restauration vers une cible JETABLE (jamais la prod)
	sudo systemctl start canard-restore-test.service
	sudo journalctl -u canard-restore-test.service -n 30 --no-pager
