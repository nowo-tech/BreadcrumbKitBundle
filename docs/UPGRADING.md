# Upgrading

This document describes breaking changes and upgrade notes between versions. Sections are ordered from newest to oldest.

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
