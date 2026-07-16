# Demo aggregate — Symfony 8 (Breadcrumb Kit Bundle).

DEMOS := symfony8

.PHONY: help clean test-all test-coverage-all verify-all release-check release-verify
.PHONY: up down update-bundle up-symfony8 down-symfony8 update-bundle-symfony8

help:
	@echo "Breadcrumb Kit Bundle — Demos: $(DEMOS)"
	@echo "  make up DEMO=symfony8   (port 8021)"
	@echo "  make up-symfony8"
	@echo "  make down DEMO=symfony8 | make update-bundle DEMO=symfony8"
	@echo "  make update-bundle-all | release-check"

up-symfony8:
	@$(MAKE) -C symfony8 up

down-symfony8:
	@$(MAKE) -C symfony8 down

update-bundle-symfony8:
	@$(MAKE) -C symfony8 update-bundle

up:
	@if [ -n "$(DEMO)" ]; then \
		$(MAKE) up-$(DEMO); \
	else \
		$(MAKE) up-symfony8; \
	fi

down:
	@[ -n "$(DEMO)" ] || { echo "Usage: make down DEMO=symfony8"; exit 1; }
	@$(MAKE) down-$(DEMO)

update-bundle:
	@[ -n "$(DEMO)" ] || { echo "Usage: make update-bundle DEMO=symfony8"; exit 1; }
	@$(MAKE) update-bundle-$(DEMO)

update-bundle-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d update-bundle || exit 1; done

test-coverage-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d test-coverage || exit 1; done
	@echo "test-coverage-all done."

release-check: update-bundle-all test-coverage-all release-verify
	@echo "All demos passed release-check."

release-verify:
	@for d in $(DEMOS); do $(MAKE) -C $$d verify || true; done

clean:
	@rm -rf $(addsuffix /vendor,$(DEMOS)) $(addsuffix /var,$(DEMOS)) $(addsuffix /.phpunit.cache,$(DEMOS))
	@echo "Cleaned demos."


# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
