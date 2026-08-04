# Breadcrumb Kit Bundle

[![CI](https://github.com/nowo-tech/BreadcrumbKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/BreadcrumbKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/breadcrumb-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/breadcrumb-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/breadcrumb-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/breadcrumb-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/breadcrumb-kit-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/BreadcrumbKitBundle) [![Coverage](https://img.shields.io/badge/Coverage-99.26%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** Install from [Packagist](https://packagist.org/packages/nowo-tech/breadcrumb-kit-bundle) and consider starring the [GitHub repository](https://github.com/nowo-tech/BreadcrumbKitBundle).

Symfony bundle for **database-driven breadcrumb trails**: match the current route by name + static parameters, walk a **parent chain**, support **i18n** (JSON translations on entities), **optional links**, per-**collection** presentation (icons, CSS, responsive JSON), and **PSR-6 caching** of serialized item rows.
Design aligns with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle) (Doctrine, YAML defaults, cache pool, Twig namespace overrides).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

## Table of contents

- [Status](#status)
- [Version information](#version-information)
- [Quick start](#quick-start)
- [Requirements](#requirements)
- [Development](#development)
- [Documentation](#documentation)
- [Tests and coverage](#tests-and-coverage)
- [License](#license)

## Status

**MVP (v2.1.3):** entities (`dashboard_breadcrumb_*` tables, aligned with DashboardMenuBundle), repositories, `BreadcrumbLoader`, `BreadcrumbUrlResolver`, Twig (`breadcrumb_trail`, `breadcrumb_render`) + **Twig Extra** (**REQ-TWIG-004**), **CLI** (migration / export / import / preview), **`BreadcrumbTrailBuiltEvent`** enrichers, path/attribute matching, **optional web dashboard** with **UiKitBundle** look-and-feel (**REQ-UI-001-kit**), **FormKitBundle** forms, **REQ-UI-002** access roles, named asset package `nowo_breadcrumb_kit`, **presentation options**, dashboard i18n **en/es/de/fr/it/nl/pt**. **Demo**: Symfony 8.1 / PHP 8.5 (**8021**) with FrankenPHP. **Flex recipe** in `.symfony/recipe/`.

**FrankenPHP:** demo selects classic vs worker via **`FRANKENPHP_MODE`** (default `worker`). See [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md).

## Version information

Latest release: **[v2.1.3](https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.1.3)** (2026-08-04). Requires **PHP 8.2+** and **Symfony 7.4+**. Tables: `dashboard_breadcrumb_*` (see [UPGRADING.md](docs/UPGRADING.md) from v1.2.x / 2.0.14→2.1.0 / 2.1.2→2.1.3 Twig Extra).

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

## Requirements

- PHP `>=8.2 <8.6`
- Symfony 7.4+ or 8.x (`^7.4 || ^8.0`; see `composer.json`)
- Doctrine ORM
- Twig Extra Bundle (`twig/extra-bundle` + `twig/string-extra`; REQ-TWIG-004)
- UiKitBundle + FormKitBundle (transitive; register both for the dashboard)

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

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit manual](docs/SPEC-KIT.md)

### Additional documentation

- [DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md) — FrankenPHP demo (`demo/symfony8`)
- [GitHub Actions CI requirements](docs/GITHUB_CI.md)

### Demo application

**Symfony 8** (Symfony 8.1, PHP 8.5):

```bash
make -C demo/symfony8 up
```

Opens at `http://localhost:8021` by default; the app redirects `/` to `/en/`.

## Tests and coverage

- **PHP**: run `composer test-coverage` or `make test-coverage` (Docker). Update the percentage below when you change code or tests; CI enforces a green build and generates `coverage.xml`.

| Language | Coverage (Lines / notes) |
|----------|---------------------------|
| PHP      | **99.26%** (Lines, PHPUnit + PCOV; run `make test-coverage` to refresh) |
| TS/JS    | N/A |
| Python   | N/A |

## License

MIT. See [LICENSE](LICENSE).
