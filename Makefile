# Managed by bootstrap (symfony profile). Standard quality targets.
# Tools come from the project (vendor/bin) or your machine, never from here.
.DEFAULT_GOAL := help

.PHONY: help qa lint fix hooks cs cs-fix stan rector rector-fix test \
	up down build sh logs

# Docker Compose binary (override with `make up DC="docker-compose"` if needed).
DC ?= docker compose

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

qa: lint stan test ## Run all quality checks (lint + stan + test)

lint: ## Run every pre-commit hook on all files
	pre-commit run --all-files

cs: ## Check coding style (PHP-CS-Fixer, dry-run)
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix coding style (PHP-CS-Fixer)
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix

stan: ## Static analysis (PHPStan)
	vendor/bin/phpstan analyse --no-progress

rector: ## Show refactorings (Rector, dry-run)
	vendor/bin/rector process --dry-run

rector-fix: ## Apply refactorings (Rector)
	vendor/bin/rector process

test: ## Run the test suite (Pest or PHPUnit)
	@if [ -f vendor/bin/pest ]; then vendor/bin/pest; else vendor/bin/phpunit; fi

fix: cs-fix rector-fix ## Auto-fix coding style and refactorings

hooks: ## Install git hooks (pre-commit + commit-msg)
	pre-commit install
	pre-commit install --hook-type commit-msg

# ── Docker (stack de développement, cf. compose.yaml) ────────────────────────

up: ## Build & start the dev stack (app + database) in the background
	$(DC) up -d --build --wait

down: ## Stop the dev stack and remove containers
	$(DC) down --remove-orphans

build: ## Build the images (without starting)
	$(DC) build

sh: ## Open a shell inside the running app container
	$(DC) exec app sh

logs: ## Follow the app container logs
	$(DC) logs -f app
