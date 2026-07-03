# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
