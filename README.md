# Breadcrumb Kit Bundle

[![CI](https://github.com/nowo-tech/BreadcrumbKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/BreadcrumbKitBundle/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/breadcrumb-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/breadcrumb-kit-bundle)
[![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/breadcrumb-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/breadcrumb-kit-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.x%20%7C%208.x-000000?logo=symfony)](https://symfony.com)
[![GitHub stars](https://img.shields.io/github/stars/nowo-tech/BreadcrumbKitBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/BreadcrumbKitBundle)
[![Coverage](https://img.shields.io/badge/Coverage-99.52%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** Install from [Packagist](https://packagist.org/packages/nowo-tech/breadcrumb-kit-bundle) and consider starring the [GitHub repository](https://github.com/nowo-tech/BreadcrumbKitBundle).

Symfony bundle for **database-driven breadcrumb trails**: match the current route by name + static parameters, walk a **parent chain**, support **i18n** (JSON translations on entities), **optional links**, per-**collection** presentation (icons, CSS, responsive JSON), and **PSR-6 caching** of serialized item rows.

Design aligns with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle) (Doctrine, YAML defaults, cache pool, Twig namespace overrides).

## Version information

Latest release: **[v2.0.0](https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.0.0)** (2026-07-05). Requires **PHP 8.2+** and **Symfony 7+**. Tables: `dashboard_breadcrumb_*` (see [UPGRADING.md](docs/UPGRADING.md) from v1.2.x).

## Status

**MVP (v2.0.0):** entities (`dashboard_breadcrumb_*` tables, aligned with DashboardMenuBundle), repositories, `BreadcrumbLoader`, `BreadcrumbUrlResolver`, Twig (`breadcrumb_trail`, `breadcrumb_render`), **optional web dashboard**, **presentation options**, dashboard i18n **en/es/de/fr/it/nl/pt**. **Demos**: Symfony 7 (**8020**) and Symfony 8.1 (**8021**) with FrankenPHP. **Flex recipe** in `.symfony/recipe/`. Planned: migration command, event-based enrichers.

**FrankenPHP worker mode:** demos use worker-enabled `Caddyfile` for production-style runs; development uses `Caddyfile.dev` without workers. See [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md).

## Requirements

- PHP `>=8.2 <8.6`
- Symfony 7.x or 8.x (see `composer.json`)
- Doctrine ORM

## Quick start

```bash
composer require nowo-tech/breadcrumb-kit-bundle
```

Register the bundle in `config/bundles.php`:

```php
Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle::class => ['all' => true],
```

With **Symfony Flex**, the recipe (when available from Packagist or your recipe index) registers the bundle and adds config/routes. Without Flex, see [docs/INSTALLATION.md](docs/INSTALLATION.md) for manual steps.

Example `config/packages/nowo_breadcrumb_kit.yaml`:

```yaml
nowo_breadcrumb_kit:
    locales: ['en', 'es']
    default_locale: 'en'
    default_collection: 'default'
    cache:
        ttl: 60
        pool: cache.app
```

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)

### Additional documentation

- [DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md) — FrankenPHP demos (`demo/symfony7`, `demo/symfony8`)

### Demo applications

**Symfony 7** (default):

```bash
make -C demo/symfony7 up
```

Opens at `http://localhost:8020` (see `demo/symfony7/.env.example` → `PORT`).

**Symfony 8** (Symfony 8.1, PHP 8.4):

```bash
make -C demo/symfony8 up
```

Opens at `http://localhost:8021` by default; the app redirects `/` to `/en/`.

## Tests and coverage

- **PHP**: run `composer test-coverage` or `make test-coverage` (Docker). Update the percentage below when you change code or tests; CI enforces a green build and generates `coverage.xml`.

| Language | Coverage (Lines / notes) |
|----------|---------------------------|
| PHP      | **99.52%** (Lines, PHPUnit + PCOV; run `make test-coverage` to refresh) |
| TS/JS    | N/A |
| Python   | N/A |

## Development

```bash
composer install
composer test
composer phpstan
```

With Docker from the bundle root:

```bash
make install
make test-coverage
```

## License

MIT. See [LICENSE](LICENSE).
