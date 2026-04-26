# MPC e-Tender — local dev workflow.
#
# Targets are thin wrappers around `docker compose` so they stay
# debuggable: each target prints what it's about to run, then runs
# it. If `make` isn't installed on Windows, install via Chocolatey:
#     choco install make
# (See docs/DEVELOPMENT.md for the full setup walkthrough.)
#
# Compose service names: app, app-horizon, mysql, valkey, minio,
# minio-init, mailpit, meilisearch (search profile only).

.DEFAULT_GOAL := help
SHELL := /bin/bash
DC    := docker compose

# Detect first-run: copy .env.docker → .env if .env doesn't exist.
# Used by `up` and `fresh` so a clean checkout works without manual
# `cp` (the kind of friction that makes new contributors stall).
define ensure_env
	@if [ ! -f .env ]; then \
		echo "→ .env missing — copying from .env.docker"; \
		cp .env.docker .env; \
	fi
endef

.PHONY: help
help:  ## Show this help.
	@echo "MPC e-Tender — make targets:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

.PHONY: build
build:  ## Build the app image (Sail's PHP 8.4 runtime + our php.ini/entrypoint).
	$(DC) build app

.PHONY: up
up:  ## Bring the stack up in the background.
	$(call ensure_env)
	$(DC) up -d
	@echo ""
	@echo "→ App:        http://localhost:8000"
	@echo "→ MinIO UI:   http://localhost:8900  (sail / password)"
	@echo "→ Mailpit:    http://localhost:8025"
	@echo "→ Vite HMR:   http://localhost:5173  (start with 'docker compose exec app npm run dev')"

.PHONY: down
down:  ## Stop the stack (volumes preserved).
	$(DC) down

.PHONY: fresh
fresh:  ## Nuke volumes, rebuild, migrate, seed core. Does NOT run seed-dev.
	$(call ensure_env)
	$(DC) down -v
	$(DC) up -d
	@echo "→ waiting 8s for mysql + minio-init to settle…"
	@sleep 8
	$(DC) exec app php artisan key:generate --force
	$(DC) exec -e RUN_MIGRATIONS=true app php artisan migrate:fresh --force --seed

.PHONY: migrate
migrate:  ## Run pending migrations against the live containers.
	$(DC) exec app php artisan migrate --force

.PHONY: seed
seed:  ## Run DatabaseSeeder (roles, permissions, categories, admin).
	$(DC) exec app php artisan db:seed --force

.PHONY: seed-dev
seed-dev:  ## Run DevDataSeeder (Ahmed, Fatima, 7 test tenders, sample PDF).
	$(DC) exec app php artisan db:seed --class=DevDataSeeder --force

.PHONY: test
test:  ## Run Pest in the container (sqlite :memory: — fast).
	$(DC) exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: app ./vendor/bin/pest

.PHONY: test-parallel
test-parallel:  ## Run Pest in parallel (faster on multi-core).
	$(DC) exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: app ./vendor/bin/pest --parallel

# WHY the explicit -e overrides above: phpunit.xml's <env> blocks lack
# force="true", so they don't override values set in .env. Inside the
# container, .env points DB_CONNECTION at mysql, which silently
# replaces phpunit.xml's sqlite intent — tests then run against the
# live mysql container and fail on schema strict-mode differences.
# Setting env at the docker exec layer pins them ahead of phpunit's
# config load. Filed as TECH-DEBT-04 (see BUGS.md).

.PHONY: shell
shell:  ## Drop into a bash shell inside the app container.
	$(DC) exec app bash

.PHONY: tinker
tinker:  ## artisan tinker against the containerized MySQL.
	$(DC) exec app php artisan tinker

.PHONY: logs
logs:  ## Tail logs from all services.
	$(DC) logs -f --tail=100

.PHONY: logs-app
logs-app:  ## Tail logs from the app service only.
	$(DC) logs -f --tail=200 app

.PHONY: logs-horizon
logs-horizon:  ## Tail logs from the horizon worker.
	$(DC) logs -f --tail=200 app-horizon

.PHONY: mc-mb
mc-mb:  ## Force re-creation of the MinIO bucket (idempotent).
	$(DC) up minio-init

.PHONY: ps
ps:  ## Show container status.
	$(DC) ps

.PHONY: pint
pint:  ## Run Laravel Pint code style fix in the container.
	$(DC) exec app ./vendor/bin/pint

.PHONY: npm-dev
npm-dev:  ## Start Vite dev server inside the app container (foreground).
	$(DC) exec app npm run dev

.PHONY: npm-build
npm-build:  ## Build the production frontend assets.
	$(DC) exec app npm run build
