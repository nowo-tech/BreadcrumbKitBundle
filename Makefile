SHELL := /bin/bash
COMPOSE ?= docker compose
SERVICE_PHP ?= php
export COMPOSER_ALLOW_SUPERUSER ?= 1

.PHONY: help ensure-up up down down-dev build shell install assets test test-coverage \
	check-no-cursor-coauthor strip-cursor-coauthor-from-history setup-hooks \
	cs-check cs-fix rector rector-dry phpstan qa release-check composer-sync \
	clean update validate

help:
	@echo "Usage: make <target>"
	@echo ""
	@echo "Container: up, down, down-dev, build, shell"
	@echo "Dependencies: install, update, update-deps, validate, validate-translations"
	@echo "Assets: assets"
	@echo "Tests: test, test-coverage, coverage-check"
	@echo "Quality: cs-check, cs-fix, rector, rector-dry, phpstan, qa"
	@echo "Release: release-check, composer-sync"
	@echo "Demos: make -C demo/symfony8 up (8021) — see docs/DEMO-FRANKENPHP.md"
	@echo "Cleanup: clean"

ensure-up:
	@echo "Ensuring Docker environment is up..."
	@$(COMPOSE) up -d --build
	@sleep 10
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction --prefer-dist

up:
	@$(COMPOSE) up -d --build

down:
	@$(COMPOSE) down --remove-orphans

down-dev: down
	@echo "Dev container stopped."

build:
	@$(COMPOSE) build --no-cache

shell:
	@$(COMPOSE) exec $(SERVICE_PHP) sh

install: ensure-up

assets:
	@echo "No frontend assets in this bundle."

test: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer test

test-coverage: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer test-coverage 2>&1 | tee coverage-php.txt
	@sh ./.scripts/php-coverage-percent.sh coverage-php.txt

coverage-check: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer coverage-check

cs-check: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-fix

rector: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer rector

rector-dry: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

phpstan: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

qa: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer qa

composer-sync: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction --prefer-dist

release-check: check-no-cursor-coauthor
	@$(MAKE) ensure-up
	@$(MAKE) composer-sync
	@$(MAKE) cs-fix
	@$(MAKE) cs-check
	@$(MAKE) rector-dry
	@$(MAKE) phpstan
	@$(MAKE) coverage-check
	@if [ -d demo ]; then $(MAKE) -C demo release-check; fi

clean:
	rm -rf vendor coverage .phpunit.cache coverage-php.txt coverage.xml

update: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer update

validate: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict

validate-translations: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'for f in src/Resources/translations/*.yaml; do php -r "yaml_parse_file(\$argv[1]);" "$$f" || exit 1; done'
	@echo "Translation YAML files OK."



setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — includes commit-msg for REQ-GIT-001)."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main
