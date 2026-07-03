# Upgrading

This document describes breaking changes and upgrade notes between versions. Sections are ordered from newest to oldest.

## Initial install (v1.0.0)

There is no prior release to upgrade from. Follow [INSTALLATION.md](INSTALLATION.md).

- Configuration root key: **`nowo_breadcrumb_kit`** (not `breadcrumb_kit`).
- Map Doctrine entities under `Nowo\BreadcrumbKitBundle\Entity` and create `breadcrumb_collection` / `breadcrumb_item` tables before use.
- Optional dashboard: set `dashboard.enabled: true`, import `@NowoBreadcrumbKitBundle/Resources/config/routing/dashboard.yaml` with prefix `%nowo_breadcrumb_kit.dashboard.path_prefix%`, and protect the URL in your app firewall.
- Optional Flex recipe: `.symfony/recipe/nowo-tech/breadcrumb-kit-bundle/1.0/` (see [INSTALLATION.md](INSTALLATION.md#with-symfony-flex)).

## From pre-release / local copies to 1.0.0

If you integrated the bundle before this tag:

1. Rename any `config/packages/breadcrumb_kit.yaml` to `nowo_breadcrumb_kit.yaml` and use root key `nowo_breadcrumb_kit:`.
2. Clear cache after `composer update nowo-tech/breadcrumb-kit-bundle`.
3. Sync YAML with [CONFIGURATION.md](CONFIGURATION.md) — new optional keys include `dashboard.pagination`, `dashboard.modals`, `dashboard.layout_template`, and `dashboard.import_max_bytes` (all have safe defaults).
4. If you override Twig templates, verify overrides still win after the `TwigPathsPass` prepend change (see [USAGE.md](USAGE.md#twig-templates-req-twig-001)).

## Doctrine schema

When entity mappings or table names change in a future release, generate and run a migration (or apply documented SQL) before deploying.

There is no bundle-provided migration command yet; use Doctrine migrations or `doctrine:schema:update` in development only.

## General upgrade steps (any version)

1. Read [CHANGELOG.md](CHANGELOG.md) for the target version.
2. Run `composer update nowo-tech/breadcrumb-kit-bundle`.
3. Clear Symfony cache: `bin/console cache:clear`.
4. Sync `config/packages/nowo_breadcrumb_kit.yaml` with [CONFIGURATION.md](CONFIGURATION.md).
