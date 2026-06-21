# Visitor Management — common dev loops.
# Everything runs inside the Docker containers so contributors need only Docker.
#
# Usage: `make up`, `make migrate`, `make seed`, `make test`, `make tinker`.

DC := docker compose
# Run one-off artisan/commands in the app container.
EXEC := $(DC) exec app
RUN  := $(DC) run --rm app

# Published host port for the app. docker compose reads the root .env
# automatically; make does not — so read it here to keep messages accurate.
APP_PORT := $(shell sed -n 's/^APP_PORT=//p' .env 2>/dev/null)
APP_PORT := $(if $(APP_PORT),$(APP_PORT),8080)

.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

.PHONY: build
build: ## Build the PHP image
	$(DC) build

.PHONY: up
up: ## Start the full stack (app, nginx, postgres, redis, queue, reverb)
	$(DC) up -d
	@echo "App should be at http://localhost:$(APP_PORT)"

.PHONY: down
down: ## Stop the stack
	$(DC) down

.PHONY: install
install: ## Install composer deps + generate app key (first run)
	$(RUN) composer install
	$(RUN) cp -n .env.example .env || true
	$(RUN) php artisan key:generate

.PHONY: migrate
migrate: ## Run database migrations
	$(EXEC) php artisan migrate

.PHONY: fresh
fresh: ## Drop everything and re-migrate + seed
	$(EXEC) php artisan migrate:fresh --seed

.PHONY: seed
seed: ## Seed the database (1 building, 2 tenants, 1 guard)
	$(EXEC) php artisan db:seed

.PHONY: test
test: ## Run the Pest test suite
	$(RUN) ./vendor/bin/pest

.PHONY: tinker
tinker: ## Open a Tinker REPL
	$(EXEC) php artisan tinker

.PHONY: ussd
ussd: ## Drive the USSD flow locally (see `make ussd ARGS="--help"`)
	$(EXEC) php artisan ussd:simulate $(ARGS)

# --- Native (no-Docker) loop -------------------------------------------------
# Run the PHP app on the host for fast edits, reusing the Dockerised Postgres
# (host port 55432). Uses src/.env.native (APP_ENV=native). Needs Postgres up:
# `make up` (or just `docker compose up -d postgres`). Run each target in its
# own terminal.
NATIVE := cd src && APP_ENV=native

.PHONY: serve
serve: ## [native] Serve the app on http://localhost:8000
	$(NATIVE) php artisan serve --port=8000

.PHONY: worker
worker: ## [native] Run the off-session queue worker (database driver)
	$(NATIVE) php artisan queue:work --tries=3 --backoff=5

.PHONY: realtime
realtime: ## [native] Run the Reverb websocket server on :8080
	$(NATIVE) php artisan reverb:start --host=0.0.0.0 --port=8080

.PHONY: native-migrate
native-migrate: ## [native] Migrate + seed against the configured DB
	$(NATIVE) php artisan migrate --seed

.PHONY: logs
logs: ## Tail container logs
	$(DC) logs -f
