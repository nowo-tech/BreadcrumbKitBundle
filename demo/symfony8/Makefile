SHELL := /bin/bash
COMPOSE ?= docker compose
SERVICE_PHP ?= php

.PHONY: help build up down restart shell install ensure-up update-bundle verify cache-clear demo-started-msg test test-coverage

help:
	@echo "Breadcrumb Kit — Symfony 8 demo (Symfony 8.1, FrankenPHP, PHP 8.4)"
	@echo "  up, down, restart, build, shell, install, ensure-up, update-bundle, verify, test, test-coverage"

build:
	$(COMPOSE) build --no-cache

cache-clear: ensure-up
	$(COMPOSE) exec -T php sh -c "rm -rf var/cache/*"
	$(COMPOSE) exec -T php php bin/console cache:clear --no-interaction --no-warmup

up:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	@rm -f .env.dev
	$(COMPOSE) down 2>/dev/null || true
	$(COMPOSE) build
	$(COMPOSE) up -d mysql
	@echo "Waiting for MySQL to be ready..."
	@sleep 8
	@echo "Installing dependencies..."
	$(COMPOSE) run --rm --entrypoint '' php composer install --no-interaction --prefer-dist
	@rm -f .env.dev
	@echo "Publishing Web Profiler / bundle assets to public/bundles/..."
	$(COMPOSE) run --rm --entrypoint '' php php bin/console assets:install public --symlink --no-interaction 2>/dev/null || $(COMPOSE) run --rm --entrypoint '' php php bin/console assets:install public --no-interaction
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	$(MAKE) ensure-up
	@echo "Waiting for PHP..."
	@for i in 1 2 3 4 5 6 7 8 9 10; do $(COMPOSE) exec -T php true 2>/dev/null && break || sleep 2; done
	@sleep 2
	$(COMPOSE) exec -T php sh -c "rm -rf var/cache/*"
	$(COMPOSE) exec -T php php bin/console cache:clear --no-interaction --no-warmup
	@echo "Recreating database and schema..."
	$(COMPOSE) exec -T php php bin/console doctrine:database:drop --if-exists --force --no-interaction
	$(COMPOSE) exec -T php php bin/console doctrine:database:create --no-interaction
	$(COMPOSE) exec -T php php bin/console doctrine:schema:update --force --no-interaction
	@echo "Loading fixtures..."
	$(COMPOSE) exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "Clearing cache..."
	$(COMPOSE) exec -T php sh -c "rm -rf var/cache/*"
	$(COMPOSE) exec -T php php bin/console cache:clear --no-interaction --no-warmup
	$(COMPOSE) exec -T php php bin/console assets:install public --symlink --no-interaction 2>/dev/null || $(COMPOSE) exec -T php php bin/console assets:install public --no-interaction
	@$(MAKE) demo-started-msg

demo-started-msg:
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	$(COMPOSE) down

restart: ensure-up
	$(COMPOSE) restart

shell:
	$(COMPOSE) exec php sh

ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Starting containers..."; \
		[ ! -f .env ] && cp .env.example .env || true; \
		$(COMPOSE) up -d mysql 2>/dev/null || true; \
		sleep 5; \
		$(COMPOSE) up -d; \
		sleep 5; \
		$(COMPOSE) exec -T php sh -c "composer install --no-interaction || composer update --no-interaction"; \
		$(COMPOSE) exec -T php sh -c "php bin/console assets:install public --symlink --no-interaction 2>/dev/null || php bin/console assets:install public --no-interaction 2>/dev/null || true"; \
	fi

install: ensure-up
	$(COMPOSE) exec -T php composer install --no-interaction
	$(COMPOSE) exec -T php sh -c "php bin/console assets:install public --symlink --no-interaction 2>/dev/null || php bin/console assets:install public --no-interaction"

update-bundle: ensure-up
	$(COMPOSE) exec -T php sh -c "composer update nowo-tech/breadcrumb-kit-bundle --no-interaction"
	$(COMPOSE) exec -T php sh -c "rm -rf var/cache/*"
	$(COMPOSE) exec -T php php bin/console cache:clear --env=dev --no-interaction --no-warmup || true

test: ensure-up
	$(COMPOSE) exec -e APP_ENV=test -T php php /var/breadcrumb-kit-bundle/vendor/bin/phpunit -c /var/breadcrumb-kit-bundle/phpunit.xml.dist

test-coverage: ensure-up
	$(COMPOSE) exec -e APP_ENV=test -T php php /var/breadcrumb-kit-bundle/vendor/bin/phpunit -c /var/breadcrumb-kit-bundle/phpunit.xml.dist --coverage-text

verify: ensure-up
	@$(COMPOSE) exec -T php php bin/console lint:container
	@$(COMPOSE) exec -T php php -r '$$h=@file_get_contents("http://127.0.0.1:80/en/") ?: ""; echo (str_contains($$h, "breadcrumb-app-override") && str_contains($$h, "sf-toolbar") ? "OK home (toolbar)\n" : "WARN home\n");'


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
