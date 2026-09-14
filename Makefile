.DEFAULT_GOAL := help

COMPOSE       := docker compose
COMPOSE_PROD  := docker compose -f docker-compose.yml
COMPOSE_DEV   := docker compose -f docker-compose.yml -f docker-compose.override.yml

export DOCKER_UID := $(shell id -u)
export DOCKER_GID := $(shell id -g)

.PHONY: help env-file certs up up-dev down restart logs shell-php shell-ws migrate seed fresh test test-php test-fe test-ws lint install ps queue-work

help: ## Show all available commands
	@awk 'BEGIN {FS = ":.*?## "} /^[a-z0-9-]+:.*?## / {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

env-file:
	@test -f .env || cp .env.example .env

certs:
	@mkdir -p docker/nginx/certs
	@if [ ! -f docker/nginx/certs/localhost.crt ]; then \
		docker run --rm -v "$(CURDIR)/docker/nginx/certs:/certs" alpine:3.20 \
			sh -c 'apk add --no-cache openssl >/dev/null && openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
				-keyout /certs/localhost.key -out /certs/localhost.crt -subj "/CN=localhost"'; \
	fi

up: env-file certs ## Start all containers (production mode)
	$(COMPOSE_PROD) up -d --build

up-dev: env-file certs ## Start all containers + frontend dev server
	$(COMPOSE_DEV) up -d --build

down: ## Stop all containers
	$(COMPOSE_DEV) down --remove-orphans

restart: ## Restart all containers
	$(MAKE) down
	$(MAKE) up

logs: ## Follow logs from all containers
	$(COMPOSE_DEV) logs -f

shell-php: ## Open bash in php container
	$(COMPOSE_PROD) exec php bash

shell-ws: ## Open bash in ws-server container
	$(COMPOSE_PROD) exec ws-server bash

migrate: ## Run Laravel migrations
	$(COMPOSE_PROD) exec php php artisan migrate

seed: ## Seed demo user admin@test.com / password
	$(COMPOSE_PROD) exec php php artisan db:seed

fresh: ## Fresh migrate + seed
	$(COMPOSE_PROD) exec php php artisan migrate:fresh --seed

test: test-php test-fe test-ws ## Run PHP tests (Pest) + frontend tests (Vitest) + ws-server (Vitest)

test-php: ## Run only PHP tests
	$(COMPOSE_PROD) exec php ./vendor/bin/pest --coverage --coverage-text

test-fe: ## Run only frontend tests
	$(COMPOSE_DEV) exec frontend npm test -- --run

test-ws: ## Run ws-server Vitest + TypeScript build
	$(COMPOSE_DEV) run --rm --no-deps ws-server sh -c "npm install && npm test && npm run build"

lint: ## Run PHPStan (level 8) + Pint + ESLint + Vue TSC
	$(COMPOSE_PROD) exec php ./vendor/bin/phpstan analyse --memory-limit=512M
	$(COMPOSE_PROD) exec php ./vendor/bin/pint --test
	$(COMPOSE_DEV) run --rm --no-deps frontend npm run lint
	$(COMPOSE_DEV) run --rm --no-deps frontend npm run typecheck

install: ## Install all dependencies (composer + npm)
	$(COMPOSE_PROD) exec php composer install --no-interaction --prefer-dist
	$(COMPOSE_DEV) run --rm --no-deps frontend npm install
	$(COMPOSE_PROD) run --rm --no-deps ws-server npm install

ps: ## Show running containers
	$(COMPOSE_DEV) ps

queue-work: ## Start queue worker manually
	$(COMPOSE_PROD) exec php php artisan queue:work --tries=3 --backoff=60
