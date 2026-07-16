# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
