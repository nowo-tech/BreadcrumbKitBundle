# Installation

This guide covers installing Breadcrumb Kit Bundle in a Symfony application.

## Table of contents

- [Requirements](#requirements)
- [Install with Composer](#install-with-composer)
- [Register the bundle](#register-the-bundle)
  - [With Symfony Flex](#with-symfony-flex)
  - [Without Flex (manual)](#without-flex-manual)
- [Doctrine mapping and schema](#doctrine-mapping-and-schema)
- [Import routes (dashboard)](#import-routes-dashboard)
- [Verify](#verify)
- [Docker (bundle development)](#docker-bundle-development)
- [Next steps](#next-steps)

## Requirements

- **PHP** >= 8.2, < 8.6
- **Symfony** 7.4+ or 8.x (`^7.4 || ^8.0` in `composer.json`; FormKitBundle floor)
- **Doctrine ORM** ^2.13 || ^3.0
- **Doctrine Bundle** ^2.8 || ^3.0 — on **Symfony 8** with **PHP >= 8.4**, Composer resolves **`doctrine/doctrine-bundle` ^3.0** (2.x supports Symfony up to 7.x only).
- **UiKitBundle** (`nowo-tech/ui-kit-bundle` ^1.4) — pulled in transitively for dashboard macros / `nowo-ui.css` (REQ-UI-001-kit). Ensure `NowoUiKitBundle` is registered and run `assets:install` so package `nowo_ui_kit` is available.
- **FormKitBundle** (`nowo-tech/form-kit-bundle` ^2.0) — pulled in transitively for dashboard Symfony forms (`FormOptionsTrait`, profile `breadcrumb_kit`). Ensure `NowoFormKitBundle` is registered (Symfony Flex / demo `bundles.php`). Optional host YAML: `config/packages/nowo_form_kit.yaml` (see [CONFIGURATION](CONFIGURATION.md)).

**Note:** Symfony **8.0+** requires **PHP >= 8.4**. Symfony **8.1+** requires **PHP >= 8.4.1**. With PHP 8.2 or 8.3, Composer resolves Symfony **7.x** only.

## Install with Composer

```bash
composer require nowo-tech/breadcrumb-kit-bundle
```

## Register the bundle

### With Symfony Flex

If you use [Symfony Flex](https://symfony.com/doc/current/setup/flex.html) and the bundle is installed from Packagist (or a recipe index that includes this repository’s recipe), Flex will:

- Register `NowoBreadcrumbKitBundle` in `config/bundles.php`
- Copy `config/packages/nowo_breadcrumb_kit.yaml` (reference defaults)
- Copy `config/routes_nowo_breadcrumb_kit.yaml` for optional dashboard routing

The recipe lives in this repository at `.symfony/recipe/nowo-tech/breadcrumb-kit-bundle/1.0/` until it is published to the Symfony recipe index.

Then continue with [Doctrine mapping and schema](#doctrine-mapping-and-schema) and, if you use the admin UI, [Import routes (dashboard)](#import-routes-dashboard).

### Without Flex (manual)

1. **Register the bundle** in `config/bundles.php`:

```php
Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle::class => ['all' => true],
```

2. **Create config** — add `config/packages/nowo_breadcrumb_kit.yaml`. See [CONFIGURATION.md](CONFIGURATION.md) for the full reference.

## Doctrine mapping and schema

1. Map entities under `Nowo\BreadcrumbKitBundle\Entity` in your Doctrine configuration (attribute mapping).
2. Create tables `dashboard_breadcrumb_collection` and `dashboard_breadcrumb_item` (respecting `doctrine.table_prefix` if set):

```bash
php bin/console nowo_breadcrumb_kit:generate-migration
# or for additive columns after an upgrade:
php bin/console nowo_breadcrumb_kit:generate-migration --update
```

In development only you may use `doctrine:schema:update --force` instead.

After upgrading to **2.1.0+**, if the dashboard errors with `Unknown column '…path_pattern'`, run `--update` (or `schema:update` in demos) — the entity gained `path_pattern` / `match_attributes`.
3. Seed at least one `BreadcrumbCollection` whose `code` matches `default_collection`, then add `BreadcrumbItem` rows (`routeName`, `staticRouteParams`, optional `pathPattern` / `matchAttributes`, optional `parent` chain).

See the demo fixtures in `demo/symfony8/src/DataFixtures/BreadcrumbDemoFixtures.php` for an example.

## Import routes (dashboard)

The web dashboard is **opt-in** (`dashboard.enabled: true`). Import routes with a prefix that matches `dashboard.path_prefix`.

**With Flex recipe file** — add to `config/routes.yaml`:

```yaml
_nowo_breadcrumb_kit_dashboard:
    resource: routes_nowo_breadcrumb_kit.yaml
```

**Without Flex** — import directly:

```yaml
nowo_breadcrumb_kit_dashboard:
    resource: '@NowoBreadcrumbKitBundle/Resources/config/routing/dashboard.yaml'
    type: yaml
    prefix: '%nowo_breadcrumb_kit.dashboard.path_prefix%'
```

Protect the prefix in production with Symfony `security.access_control` **and** bundle `security.access_roles` (default `ROLE_ADMIN`; see [CONFIGURATION.md](CONFIGURATION.md) / [SECURITY.md](SECURITY.md), REQ-UI-002). Do **not** set `security.allow_unauthenticated: true` outside local demos. Forms and deletes require CSRF (`framework.csrf_protection: true` or SecurityBundle).

```yaml
# Host firewall (example)
security:
    access_control:
        - { path: ^/breadcrumb-kit-admin, roles: ROLE_ADMIN }
```

## Verify

```bash
php bin/console debug:config nowo_breadcrumb_kit
php bin/console list nowo_breadcrumb_kit
php bin/console cache:clear
```

Render a trail in Twig — see [USAGE.md](USAGE.md). Preview without HTTP: `php bin/console nowo_breadcrumb_kit:preview --path=/ --route=app_home`.

## Docker (bundle development)

From the **bundle repository** root (not your app):

```bash
docker compose up -d --build
docker compose exec php composer install
```

Or `make install` / `make test` as described in the root `README.md`.

## Twig Extra Bundle (REQ-TWIG-004)

This package ships Twig templates. Host applications **must** install and enable Twig Extra:

```bash
composer require twig/extra-bundle twig/string-extra
```

Register `Twig\Extra\TwigExtraBundle\TwigExtraBundle` in `config/bundles.php` (Flex usually does this). Demos already include the same stack. The package `release-check` runs `make check-twig-extra` to guard this contract.

## Next steps

- [USAGE.md](USAGE.md) — Twig helpers, template and translation overrides.
- [CONFIGURATION.md](CONFIGURATION.md) — full configuration reference.
- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) — run the Symfony 8 demo (`demo/symfony8/`).
