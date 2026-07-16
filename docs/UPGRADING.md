# Upgrading

This document describes breaking changes and upgrade notes between versions. Sections are ordered from newest to oldest.

## From 2.0.1 to 2.0.2

No intentional breaking changes to the bundle API, routes, schema, or runtime dependencies. Patch release focused on demos, docs, and maintainer tooling.

- If you used **`demo/symfony7/`** locally, switch to **`demo/symfony8/`** (`make -C demo/symfony8 up`, port **8021**). Application integrations are unaffected.
- Contributors: run `make setup-hooks` once per clone (REQ-GIT-001).

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
```

## From 2.0.0 to 2.0.1

No intentional breaking changes or runtime dependency changes. Dev lockfile sync only.

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
```

## From 1.2.x to 2.0.0

**Breaking:** entity table names changed from `nowo_breadcrumb_*` to `dashboard_breadcrumb_*`. Pin **`^1.2`** if you cannot migrate yet.

1. Run `composer update nowo-tech/breadcrumb-kit-bundle:^2.0`.
2. Apply a migration renaming tables (or drop and recreate in dev):

   | Old | New |
   |-----|-----|
   | `nowo_breadcrumb_collection` | `dashboard_breadcrumb_collection` |
   | `nowo_breadcrumb_item` | `dashboard_breadcrumb_item` |

3. Optional: set `nowo_breadcrumb_kit.doctrine.table_prefix` for an extra prefix (prepended to `dashboard_breadcrumb_*`, same as dashboard menu).
4. Clear cache: `bin/console cache:clear`.

No intentional breaking changes to route names, Twig function signatures, or YAML config keys.

## From 1.2.0 to 1.2.1

No intentional breaking changes to the bundle API, routes, or schema. Patch release (CI and demo lockfiles).

If you run **Symfony 8** with **PHP >= 8.4**, ensure your application uses **`doctrine/doctrine-bundle` ^3.0** (required for Symfony 8; the bundle already allows `^2.8 || ^3.0` in `composer.json`).

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^1.2
```

## From 1.1.0 to 1.2.0

No intentional breaking changes. New options are optional and default to previous behaviour (`hide_when_single_root: false`, `home_icon_replaces_label: true`).

1. Run `composer update nowo-tech/breadcrumb-kit-bundle:^1.2`.
2. Optionally add `presentation` to `config/packages/nowo_breadcrumb_kit.yaml` (see [CONFIGURATION.md](CONFIGURATION.md)).
3. To hide breadcrumbs on a lone root/home page, set `presentation.hide_when_single_root: true` globally or `hide_when_single_root` inside a collection’s `responsiveConfig` JSON.
4. Clear cache: `bin/console cache:clear`.
5. If you override `breadcrumb.html.twig`, consider including `@NowoBreadcrumbKitBundle/_breadcrumb_crumb.html.twig` or copy its markup for home-icon behaviour.

## From 1.0.0 to 1.1.0

**Symfony 6.4 is no longer supported.** All Symfony component constraints are now `^7.0 || ^8.0`.

1. Upgrade your application to **Symfony 7.0+** (or **8.x** with PHP >= 8.4) before running `composer update nowo-tech/breadcrumb-kit-bundle:^1.1`.
2. If you must stay on Symfony 6.4, pin the bundle to **`^1.0`** and do not upgrade past v1.0.0.
3. Clear cache: `bin/console cache:clear`.

No intentional breaking changes to route names, Twig function signatures, or entity schema in this release.

## Initial install (v1.1.0+)

Follow [INSTALLATION.md](INSTALLATION.md).

- **PHP** >= 8.2, < 8.6
- **Symfony** 7.x or 8.x
- Configuration root key: **`nowo_breadcrumb_kit`**
- Optional dashboard: `dashboard.enabled: true` + route import (see INSTALLATION)

## From pre-release / local copies to 1.0.0

If you integrated the bundle before v1.0.0:

1. Rename any `config/packages/breadcrumb_kit.yaml` to `nowo_breadcrumb_kit.yaml` and use root key `nowo_breadcrumb_kit:`.
2. Clear cache after `composer update nowo-tech/breadcrumb-kit-bundle`.
3. Sync YAML with [CONFIGURATION.md](CONFIGURATION.md).
4. If you override Twig templates, verify overrides still win after the `TwigPathsPass` prepend change (see [USAGE.md](USAGE.md#twig-templates-req-twig-001)).

## Doctrine schema

When entity mappings or table names change in a future release, generate and run a migration (or apply documented SQL) before deploying.

There is no bundle-provided migration command yet; use Doctrine migrations or `doctrine:schema:update` in development only.

## General upgrade steps (any version)

1. Read [CHANGELOG.md](CHANGELOG.md) for the target version.
2. Run `composer update nowo-tech/breadcrumb-kit-bundle`.
3. Clear Symfony cache: `bin/console cache:clear`.
4. Sync `config/packages/nowo_breadcrumb_kit.yaml` with [CONFIGURATION.md](CONFIGURATION.md).
