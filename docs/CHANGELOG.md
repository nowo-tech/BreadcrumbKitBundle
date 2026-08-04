# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## Table of contents

- [[Unreleased]](#unreleased)
- [[2.1.3] - 2026-08-04](#213---2026-08-04)
  - [Added](#added)
  - [Documentation](#documentation)
- [[2.1.2] - 2026-08-04](#212---2026-08-04)
  - [Documentation](#documentation)
- [[2.1.1] - 2026-08-04](#211---2026-08-04)
  - [Fixed](#fixed)
  - [Changed](#changed)
- [[2.1.0] - 2026-08-04](#210---2026-08-04)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[2.0.14] - 2026-08-04](#2014---2026-08-04)
  - [Added](#added)
  - [Fixed](#fixed)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[2.0.13] - 2026-08-04](#2013---2026-08-04)
  - [Changed](#changed)
  - [Fixed](#fixed)
  - [Documentation](#documentation)
- [[2.0.12] - 2026-08-01](#2012---2026-08-01)
  - [Added](#added)
  - [Documentation](#documentation)
- [[2.0.11] - 2026-07-30](#2011---2026-07-30)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[2.0.10] - 2026-07-30](#2010---2026-07-30)
  - [Changed](#changed)
- [[2.0.9] - 2026-07-29](#209---2026-07-29)
  - [Fixed](#fixed)
  - [Changed](#changed)
- [[2.0.8] - 2026-07-28](#208---2026-07-28)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[2.0.7] - 2026-07-27](#207---2026-07-27)
  - [Added](#added)
  - [Documentation](#documentation)
- [[2.0.6] - 2026-07-27](#206---2026-07-27)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[2.0.5] - 2026-07-23](#205---2026-07-23)
  - [Changed](#changed)
  - [Added](#added)
  - [Documentation](#documentation)
- [[2.0.4] - 2026-07-23](#204---2026-07-23)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[2.0.3] - 2026-07-22](#203---2026-07-22)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[2.0.2] - 2026-07-16](#202---2026-07-16)
  - [Changed](#changed)
  - [Added](#added)
  - [Documentation](#documentation)
- [[2.0.1] - 2026-07-05](#201---2026-07-05)
  - [Changed](#changed)
- [[2.0.0] - 2026-07-05](#200---2026-07-05)
  - [Changed](#changed)
  - [Added](#added)
  - [Migration](#migration)
- [[1.2.1] - 2026-07-03](#121---2026-07-03)
  - [Fixed](#fixed)
  - [Documentation](#documentation)
- [[1.2.0] - 2026-07-03](#120---2026-07-03)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[1.1.0] - 2026-07-03](#110---2026-07-03)
  - [Changed](#changed)
  - [Fixed](#fixed)
  - [Documentation](#documentation)
- [[1.0.0] - 2026-07-03](#100---2026-07-03)
  - [Added](#added)
  - [Fixed](#fixed)
  - [Documentation](#documentation)

## [Unreleased]

## [2.1.3] - 2026-08-04

### Added

- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Documentation

- [INSTALLATION.md](INSTALLATION.md) / [UPGRADING.md](UPGRADING.md): host Twig Extra Bundle contract; maintainer Twig-CS-Fixer scripts.

## [2.1.2] - 2026-08-04

### Documentation

- [UPGRADING.md](UPGRADING.md) / [INSTALLATION.md](INSTALLATION.md): clarify 2.1.0 schema (`path_pattern`, `match_attributes`) — `Unknown column … path_pattern` means the host DB was not migrated; prefer `nowo_breadcrumb_kit:generate-migration --update`, or in demos/dev `doctrine:schema:update --force`.

## [2.1.1] - 2026-08-04

### Fixed

- **Dashboard item form (full page):** render all FormKit fields in type order. Explicit `form_row` lists skipped `pathPattern` / `matchAttributes`, so those fields appeared after `parent` via `form_rest`.

### Changed

- Dashboard collection/item/import Twig (partials + full-page forms) iterate unrendered children (`{% for child in form %}` / `not child.rendered`) instead of listing each field + `form_rest`.

## [2.1.0] - 2026-08-04

### Added

- **CLI:** `nowo_breadcrumb_kit:generate-migration`, `:export`, `:import`, `:preview`.
- **Events:** `BreadcrumbTrailBuiltEvent` for trail enrichers after load.
- **Matching:** item `pathPattern` (PCRE) + `matchAttributes` JSON; route name `*` when constrained by path/attributes.
- **Preview service:** `BreadcrumbTrailPreview` for synthetic requests.
- Spec Kit feature [`002-enhancements`](../specs/002-enhancements/spec.md); baseline inventory **80/80**.

### Changed

- Dashboard form partials rely on FormKit field classes (no duplicate `ui.input()` overrides).
- `DashboardGetSearchType` uses FormKit; Flex recipe registers UiKit + FormKit and ships `nowo_form_kit.yaml` stub.
- Composer requires `symfony/console`, `symfony/event-dispatcher`, and `symfony/filesystem` explicitly (CLI / events / migration writer).

### Documentation

- Spec Kit baseline (`specs/001-baseline/`): SC-001 **80/80**; FormKit/UiKit as `FR-FORM-003` / `FR-UI-001` / `FR-DEP-001`; inventory external-kit table; constitution principle VI; SPEC-DRIVEN `REQ-UI-001-kit` / `REQ-FORM-001-kit`.
- FR-DEP-002: UiKit/FormKit remain hard requires in 2.x (optional packaging deferred).
- [UPGRADING.md](UPGRADING.md): 2.0.14 → 2.1.0; USAGE / INSTALLATION cover CLI, events, and matching.

## [2.0.14] - 2026-08-04

### Added

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Dashboard form types (`BreadcrumbItemType`, `BreadcrumbCollectionType`, `ImportBreadcrumbType`) use `FormOptionsTrait` + profile `breadcrumb_kit` (`#[FormKitConfig]`). Extension prepends that profile (and maps `dashboard.css_framework` → FormKit `css_framework`) without changing the host `default_profile`. Form types are registered as `form.type` services so `FormOptionsMerger` is injected.

### Fixed

- **Dashboard modals:** `base.html.twig` no longer redefines nested `nowo_ui_styles` / `nowo_ui_scripts` when calling `parent()`, which had replaced the layout Bootstrap CDN and left `data-bs-toggle="modal"` without Bootstrap JS. CSS guard `.modal.nowo-ui-modal.show { display: block; }` keeps UiKit’s `.nowo-ui-modal { display: none }` from hiding open Bootstrap modals.
- **Dashboard modals (DOM):** stop redefining nested `nowo_ui_content` inside `nowo_breadcrumb_kit_content` — Twig replaced the layout wrapper and dropped `_dashboard_modals.html.twig` from the page (Bootstrap then threw `Cannot read properties of undefined (reading 'backdrop')` for missing `#modal-bk-*` targets).
- **Dashboard forms:** apply UiKit `ui.input()` / `ui.btn()` / `ui.modal_dismiss_attrs()` on collection/item/import partials (UiKit is not a Symfony form theme — widgets need explicit classes). Modal forms force full-width inputs (override `.nowo-ui-input` max-width).

### Changed

- Symfony package constraints raised to **`^7.4 || ^8.0`** (aligned with FormKitBundle).

### Documentation

- INSTALLATION / CONFIGURATION / UPGRADING updated for FormKit composition and Symfony 7.4+.
- [UPGRADING.md](UPGRADING.md): 2.0.13 → 2.0.14.

## [2.0.13] - 2026-08-04

### Changed

- **REQ-UI-001-kit:** dashboard UI composes [UiKitBundle](https://github.com/nowo-tech/UiKitBundle) (`nowo-tech/ui-kit-bundle` ^1.4) instead of a private `_ui_macros.html.twig` / vendored `nowo-ui.css`.
  - Twig imports use `@NowoUiKitBundle/macros/ui.html.twig`.
  - Stylesheet loads via asset package `nowo_ui_kit` (`css/nowo-ui.css`); `js/dashboard.js` remains on `nowo_breadcrumb_kit`.
  - When the host has not configured `nowo_ui_kit`, the extension prepends `css_framework` / `icon_set` from `dashboard.*` (defaults `bootstrap5` / `bootstrap-icons`) so `ui.btn()` resolves without a framework argument. Explicit host `nowo_ui_kit` keys are never overridden.
  - Host apps that still reference `asset('css/nowo-ui.css', 'nowo_breadcrumb_kit')` must switch to package `nowo_ui_kit` and re-run `assets:install`.

### Fixed

- **Demo:** `GET /favicon.ico` and Chrome DevTools well-known probe return **204** (no locale prefix), avoiding noisy `NotFoundHttpException` in FrankenPHP logs.

### Documentation

- CONFIGURATION / USAGE / UPGRADING / INSTALLATION updated for UiKit composition.
- Spec Kit baseline inventory remapped to **74/74** `src/` files (UiKit owns macros/`nowo-ui.css`).
- [UPGRADING.md](UPGRADING.md): 2.0.12 → 2.0.13.

## [2.0.12] - 2026-08-01

### Added

- **`css_framework: custom` / `none` support without Bootstrap** (REQ-UI-001).
  - `dashboard.js` now reads `window.__breadcrumbKitDashboard.cssFramework` at init time. For non-bootstrap stacks it registers lightweight `[data-nowo-modal-open]` / `[data-nowo-modal-close]` handlers and dispatches a synthetic `show.bs.modal` event (with `relatedTarget`) so all existing modal listeners (form-load, confirm-delete, import) keep working without Bootstrap JS.
  - `nowo-ui.css` gains a self-contained modal overlay: `.nowo-ui-modal` (hidden by default), `.nowo-ui-modal.nowo-ui-modal-open` (visible), `body.nowo-modal-open` (scroll-lock), plus Bootstrap-shaped `.modal-dialog` / `.modal-content` / `.modal-header` / `.modal-body` / `.btn-close` styles scoped under `.nowo-ui-modal` so custom-framework hosts get correct modal chrome without any extra CSS.
  - Loading and error states inside modals use `nowo-ui-muted` / `nowo-ui-flash nowo-ui-flash--error` classes (retaining `alert alert-danger` as a dual class for bootstrap stacks).

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — new **"Using `css_framework: custom` without Bootstrap"** section with minimal host config, theming guide, and macro attribute notes.

## [2.0.11] - 2026-07-30

### Changed

- **`nowo-ui.css`**: colours use `--nowo-ui-*` custom properties (defaults unchanged: slate/blue). Hosts remapped under `.kit-admin` without forking templates.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — theming via `--nowo-ui-*` tokens
- [UPGRADING.md](UPGRADING.md) — From 2.0.10 to 2.0.11

## [2.0.10] - 2026-07-30

### Changed

- Collections and items index: list filter row matches admin list UX (search input + Filter + always-visible Clear filters), separate from action toolbars.
- Added `dashboard.filter` and `dashboard.clear_filters` translation keys (kept `dashboard.search` / `dashboard.clear` for BC).
- Semantic CSS: `.nowo-ui-search` / `.nowo-ui-search__input`; Twig macros `search_form()` / `search_input()`.

## [2.0.9] - 2026-07-29

### Fixed

- **REQ-TEST-011:** `make demo-smoke` follows HTTP redirects (`curl -L`) so a `302` from `/` to `/en/` still expects a final `200`.
- Demo `config/reference.php` comments for `css_framework` / `icon_set` / pagination aligned with enums and items list.

### Changed

- **REQ-MAKE-009 / REQ-MAKE-010:** Makefiles detect Compose V2 vs `docker-compose`, and soft-include optional monorepo `update-deps` helpers so standalone CI checkouts do not fail.

## [2.0.8] - 2026-07-28

### Added

- **REQ-PHP-001:** backed enums `CssFramework`, `IconSet`, `ModalSize` for dashboard config closed sets.
- **REQ-PERF-001:** dashboard **items** list uses the same `dashboard.pagination` settings as collections.
- **REQ-ASSETS-004:** named Symfony asset package `nowo_breadcrumb_kit` (`base_path` `/bundles/nowobreadcrumbkit`).
- **REQ-TEST-011:** `make demo-smoke` + `.github/workflows/demo-smoke.yml`.

### Changed

- Twig dashboard assets load via `asset('…', 'nowo_breadcrumb_kit')` instead of hard-coded `/bundles/…` paths.
- `.github/SECURITY.md` Supported Versions updated to **2.x**.
- Spec Kit baseline inventory remapped to **76/76** `src/` files (Security, enums, CSS, UI macros).

### Documentation

- README Documentation order (REQ-DOCS-002), Symfony/stars badges (REQ-DOCS-004), TOC fixes (REQ-DOCS-005).
- `docs/SECURITY.md`: REQ-SEC-004 **Pass (conditional)** / Medium (2026-07-28).
- [UPGRADING.md](UPGRADING.md): 2.0.7 → 2.0.8 (named asset package; items pagination).

## [2.0.7] - 2026-07-27

### Added

- **REQ-UI-001:** dashboard `_ui_macros.html.twig` (bootstrap5 / bootstrap4 / tailwind / foundation / custom), semantic `nowo-ui-*` CSS (`public/css/nowo-ui.css`), demo layout CDN switching by `css_framework`, and dual-class markup on list/table/toolbar/modals.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md): CSS framework table, host `parent()` stacking, overridable Twig paths.
- [UPGRADING.md](UPGRADING.md): 2.0.6 → 2.0.7 (`assets:install` after upgrade).

## [2.0.6] - 2026-07-27

### Added

- **REQ-UI-002:** `security.access_roles` / `security.access_checker` / `security.allow_unauthenticated`, `BreadcrumbKitAccessCheckerInterface`, `ConfigurableBreadcrumbKitAccessChecker`, `DashboardAccessSubscriber`, and `DashboardSecurityPass` (compilation fails if the dashboard is enabled without SecurityBundle unless `allow_unauthenticated`).
- **REQ-UI-001 (config):** `dashboard.css_framework` and `dashboard.icon_set` with Twig globals (`nowo_breadcrumb_kit_css_framework`, `nowo_breadcrumb_kit_icon_set`).
- **REQ-MAKE-002 / REQ-REL-003:** `.scripts/check-open-prs.sh` and `make check-open-prs` wired into `release-check`.
- **REQ-CI-002:** root `.scrutinizer.yml`.
- **REQ-SF-005:** `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` in `phpunit.xml.dist` and CI.
- **REQ-DOCS-005:** table of contents on long Markdown docs; **REQ-DOCS-018:** GitHub About Description / Website / Topics.

### Changed

- **Demo:** no committed `.env.dev`; `.gitignore` adds `/.pnpm-store`; demo YAML sets `security.allow_unauthenticated: true` with empty `access_roles` (demo only — never copy to production).

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) / [SECURITY.md](SECURITY.md) / [INSTALLATION.md](INSTALLATION.md): dashboard private-access contract and host `access_control` example.
- [UPGRADING.md](UPGRADING.md): 2.0.5 → 2.0.6 (SecurityBundle required when dashboard enabled).

## [2.0.5] - 2026-07-23

### Changed

- **Demo (`demo/symfony8`):** FrankenPHP base image **PHP 8.5** (`dunglas/frankenphp:1-php8.5-alpine`); `.env.example` / `.env.test` commented per variable; demo `.gitignore` grouped by category (REQ-DEMO-003 / REQ-ENV-001 / REQ-DEMO-010).

### Added

- **REQ-DOCS-017:** `docs/images/frankenphp-friendly.png` + README FrankenPHP worker-friendly banner (after REQ-CS-005).

### Documentation

- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md): troubleshooting in English (REQ-DOCS-016); PHP 8.5 policy note.
- [CONTRIBUTING.md](CONTRIBUTING.md): note that PHPStan includes `nowo-tech/phpstan-frankenphp` (require-dev).
- [UPGRADING.md](UPGRADING.md): 2.0.4 → 2.0.5 (no API or schema changes).

## [2.0.4] - 2026-07-23

### Changed

- **Static analysis:** clear PHPStan level 8 findings (typing, form `@extends`, DataTransformer generics, redundant guards). Empty [`phpstan-baseline.neon`](../phpstan-baseline.neon) kept in the tree for future suppressions.
- **Dev tooling:** require-dev [`nowo-tech/phpstan-frankenphp`](https://github.com/nowo-tech/PhpStanFrankenPhp) (`^1.0`); `phpstan.neon.dist` includes classic + worker rulesets. PHP CS Fixer enables `fully_qualified_strict_types.import_symbols`.
- **`composer.lock` / demo lock:** dependency sync (incl. `nowo-tech/phpstan-frankenphp` v1.0.1). No runtime `require` changes.

### Documentation

- [UPGRADING.md](UPGRADING.md): 2.0.3 → 2.0.4 (no API or schema changes).

## [2.0.3] - 2026-07-22

### Changed

- **Demo (`demo/symfony8`):** FrankenPHP classic vs worker is selected with **`FRANKENPHP_MODE`** (`.env` / Compose; default `worker`), independent of `APP_ENV`. Entrypoint extracted to `docker/entrypoint.sh`.

### Documentation

- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md): document `FRANKENPHP_MODE` and align overview / dev-vs-prod with the entrypoint.
- [UPGRADING.md](UPGRADING.md): 2.0.2 → 2.0.3 (no API or schema changes).

## [2.0.2] - 2026-07-16

### Changed

- **Demo:** removed `demo/symfony7/` (port 8020). The maintained FrankenPHP demo is **`demo/symfony8/`** only (Symfony 8.1 / PHP 8.4, port **8021**). README, INSTALLATION, DEMO-FRANKENPHP, and related docs updated accordingly.
- **Git hygiene (REQ-GIT-001):** CI job + `make check-no-cursor-coauthor` / `make setup-hooks` reject Cursor `Co-authored-by` trailers; `release-check` runs the check first.

### Added

- [CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md) (Contributor Covenant 2.1).
- Spec Kit baseline: [`.specify/`](../.specify/), Cursor skills (`.cursor/skills/speckit-*`), [`docs/SPEC-KIT.md`](SPEC-KIT.md), [`specs/001-baseline/`](../specs/001-baseline/).
- [`docs/GITHUB_CI.md`](GITHUB_CI.md) — CI matrix and git-history checks.

### Documentation

- [UPGRADING.md](UPGRADING.md): 2.0.1 → 2.0.2 (no API or schema changes).
- [CONTRIBUTING.md](CONTRIBUTING.md), [RELEASE.md](RELEASE.md), [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) aligned with Spec Kit and REQ-GIT-001.

## [2.0.1] - 2026-07-05

### Changed

- **`composer.lock`:** bumped dev/tooling dependencies (PHPStan 2.2.5, Rector 2.5.3, PHPUnit-related `nikic/php-parser` 5.8.0, `phpstan/phpstan-phpunit` 2.0.18, transitive `twig/twig` 3.28.0). No runtime `require` changes.

## [2.0.0] - 2026-07-05

### Changed

- **Breaking:** Doctrine table names now use the `dashboard_breadcrumb_*` prefix (`dashboard_breadcrumb_collection`, `dashboard_breadcrumb_item`), aligned with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle).
- **`doctrine.table_prefix`** is applied at runtime via `TablePrefixSubscriber` (same pattern as dashboard menu).

### Added

- Dashboard/form translations: **de**, **fr**, **it**, **nl**, **pt** (`NowoBreadcrumbKitBundle.{locale}.yaml`).
- Unit tests for `TablePrefixSubscriber`; DI integration test asserts subscriber registration.

### Migration

Rename existing tables before deploying (or drop/recreate in dev):

| Old | New |
|-----|-----|
| `nowo_breadcrumb_collection` | `dashboard_breadcrumb_collection` |
| `nowo_breadcrumb_item` | `dashboard_breadcrumb_item` |

With `doctrine.table_prefix: app_`, physical names become `app_dashboard_breadcrumb_*`.

## [1.2.1] - 2026-07-03

### Fixed

- **CI:** Symfony **8.0** / **8.1** matrix cells now override `composer.json` `platform.php` (8.2.0) with the matrix PHP version before install, and require **`doctrine/doctrine-bundle` ^3.0** (2.x does not support Symfony 8; 3.x requires PHP ^8.4).
- Demo `composer.lock` files refreshed for Symfony 8 / Doctrine 3 compatibility.

### Documentation

- [INSTALLATION.md](INSTALLATION.md): Symfony 8 + `doctrine/doctrine-bundle` ^3 note.
- [UPGRADING.md](UPGRADING.md): 1.2.0 → 1.2.1 (no API changes).

## [1.2.0] - 2026-07-03

### Added

- **`presentation` configuration** (YAML under `nowo_breadcrumb_kit.presentation`):
  - `home_icon` — global fallback when a collection has no `homeIcon`.
  - `home_icon_replaces_label` — when `true` (default), the first crumb can show only the home icon; the text label remains in `aria-label`.
  - `hide_when_single_root` — when `true`, hides the trail on pages where the only crumb is the root item and it is the current page (typical home). Per-collection override via `responsiveConfig.hide_when_single_root` in the dashboard.
- Twig partial **`_breadcrumb_crumb.html.twig`** for a single crumb (link, current, or plain text + optional icon).
- Unit tests for single-root hiding and home-icon presentation on `BreadcrumbLoader`.

### Changed

- **`BreadcrumbTrailView`:** new property `homeIconReplacesLabel` (passed from config).
- **`breadcrumb.html.twig`:** includes `_breadcrumb_crumb.html.twig`; wrapper renders only when the trail has nodes or inline-edit toolbar is shown.
- Dashboard collection form: help text for `homeIcon` field (EN/ES).

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md): `presentation.*` reference and example YAML.
- [USAGE.md](USAGE.md): presentation options and `_breadcrumb_crumb.html.twig` override.

## [1.1.0] - 2026-07-03

### Changed

- **Minimum Symfony version is 7.0** (`^7.0 || ^8.0` for Symfony components). Symfony **6.4 is no longer supported**. Minimum PHP remains **8.2** (`>=8.2 <8.6`).
- CI matrix: Symfony **6.4** removed; tests cover Symfony **7.0**, **7.4**, **8.0**, and **8.1**.

### Fixed

- CI: Symfony **8.1** job sets Composer `platform.php` to **8.4.1** (Symfony 8.1 requires PHP >= 8.4.1; the previous `8.4` override resolved as 8.4.0 and broke dependency resolution).

### Documentation

- Requirements updated in README, INSTALLATION, and UPGRADING (Symfony 7+, Symfony 8.1 / PHP 8.4.1 note).

## [1.0.0] - 2026-07-03

First public release.

### Added

- **Core:** Doctrine entities (`BreadcrumbCollection`, `BreadcrumbItem`), `BreadcrumbLoader`, `BreadcrumbUrlResolver`, PSR-6 item-list cache, Twig helpers (`breadcrumb_trail`, `breadcrumb_render`, `breadcrumb_kit_dashboard_collections_url`).
- **Dashboard CRUD** (opt-in): `dashboard.enabled` + `dashboard.path_prefix`; collections/items management, JSON export/import, inline-edit iframe support, pagination (`dashboard.pagination`), modal sizes (`dashboard.modals`), UI aligned with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle).
- **Symfony Flex recipe** (`.symfony/recipe/nowo-tech/breadcrumb-kit-bundle/1.0/`): bundle registration, default config, optional dashboard routes stub.
- **Demos:** FrankenPHP + MySQL — Symfony 7 (`demo/symfony7/`, port **8020**) and Symfony 8.1 / PHP 8.4 (`demo/symfony8/`, port **8021**).
- **Dev tooling:** Docker (`Dockerfile`, `docker-compose.yml`), root `Makefile`, Web Profiler data collector, PHPUnit (~99.5% line coverage on included `src/`), PHPStan, Rector, CI/release workflows.
- Explicit `symfony/yaml` dependency for the DI extension.

### Fixed

- **Twig (REQ-TWIG-001):** `TwigPathsPass` **`prependPath()`** for app overrides under `templates/bundles/NowoBreadcrumbKitBundle/`, then **`addPath()`** for bundle views; resolves **`twig.loader.native`** through chained aliases.
- DI extension registers `%nowo_breadcrumb_kit.*%` parameters before loading `services.yaml`.
- `BreadcrumbKitExtension::getAlias()` returns `nowo_breadcrumb_kit` (documented YAML root key).

### Documentation

- Full `docs/` set (installation, configuration, usage, security, upgrading, spec-driven development, FrankenPHP demos).
- Translation override procedure (REQ-I18N-001) and dashboard security surface documented.