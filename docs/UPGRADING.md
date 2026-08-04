# Upgrading

This document describes breaking changes and upgrade notes between versions. Sections are ordered from newest to oldest.


## Table of contents

- [From 2.1.1 to 2.1.2](#from-211-to-212)
- [From 2.1.0 to 2.1.1](#from-210-to-211)
- [From 2.0.14 to 2.1.0](#from-2014-to-210)
- [From 2.0.13 to 2.0.14](#from-2013-to-2014)
- [From 2.0.12 to 2.0.13](#from-2012-to-2013)
- [From 2.0.10 to 2.0.11](#from-2010-to-2011)
- [From 2.0.9 to 2.0.10](#from-209-to-2010)
- [From 2.0.8 to 2.0.9](#from-208-to-209)
- [From 2.0.7 to 2.0.8](#from-207-to-208)
- [From 2.0.6 to 2.0.7](#from-206-to-207)
- [From 2.0.5 to 2.0.6](#from-205-to-206)
- [From 2.0.4 to 2.0.5](#from-204-to-205)
- [From 2.0.3 to 2.0.4](#from-203-to-204)
- [From 2.0.2 to 2.0.3](#from-202-to-203)
- [From 2.0.1 to 2.0.2](#from-201-to-202)
- [From 2.0.0 to 2.0.1](#from-200-to-201)
- [From 1.2.x to 2.0.0](#from-12x-to-200)
- [From 1.2.0 to 1.2.1](#from-120-to-121)
- [From 1.1.0 to 1.2.0](#from-110-to-120)
- [From 1.0.0 to 1.1.0](#from-100-to-110)
- [Initial install (v1.1.0+)](#initial-install-v110)
- [From pre-release / local copies to 1.0.0](#from-pre-release-local-copies-to-100)
- [Doctrine schema](#doctrine-schema)
- [General upgrade steps (any version)](#general-upgrade-steps-any-version)

## From 2.1.1 to 2.1.2

Documentation-only. No schema or PHP API changes.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

## From 2.1.0 to 2.1.1

No schema or PHP API changes.

- **Twig overrides:** if you copied dashboard form templates (`_item_form_partial`, `_collection_form_partial`, `_import_partial`, `item/form`, `collection/form`) with explicit `form_row` lists, prefer iterating unrendered children so new FormKit fields keep type order (or re-copy from the bundle).

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

## From 2.0.14 to 2.1.0

Additive release (CLI, enricher event, path/attribute matching). No intentional BC breaks.

- **Schema:** new nullable columns on `dashboard_breadcrumb_item`: `path_pattern`, `match_attributes`. Run `php bin/console nowo_breadcrumb_kit:generate-migration --update` (or full generate on greenfield), then migrate.
- If Twig fails with `Unknown column '…path_pattern'` / `SQLSTATE[42S22]`, the entity mapping is ahead of the database — apply the migration (or, **development/demo only**, `php bin/console doctrine:schema:update --force`).
- **CLI:** `nowo_breadcrumb_kit:export|import|preview|generate-migration`.
- **Enrichers:** subscribe to `Nowo\BreadcrumbKitBundle\Event\BreadcrumbTrailBuiltEvent` and call `setView()`.
- **Matching:** optional path PCRE + request attributes; route `*` only when path/attributes constrain the match.
- Form partials no longer force UiKit input classes (FormKit profile supplies them).

```bash
composer update nowo-tech/breadcrumb-kit-bundle
php bin/console nowo_breadcrumb_kit:generate-migration --update
# then: php bin/console doctrine:migrations:migrate
# demo/dev alternative: php bin/console doctrine:schema:update --force
```

## From 2.0.13 to 2.0.14

**Dashboard forms:** BreadcrumbKit now requires [FormKitBundle](https://github.com/nowo-tech/FormKitBundle) (`nowo-tech/form-kit-bundle` ^2.0).

- Register `Nowo\FormKitBundle\NowoFormKitBundle` (Flex / `bundles.php`).
- Symfony floor is **7.4+** (aligned with FormKit); Composer constraints are `^7.4 || ^8.0`.
- Form types use profile `breadcrumb_kit` (`#[FormKitConfig]`). BreadcrumbKit prepends that profile when missing; host `default_profile` is unchanged.
- Optional: `config/packages/nowo_form_kit.yaml` — see FormKit docs. Translation keys for dashboard forms stay under `form.breadcrumb_*` in `NowoBreadcrumbKitBundle`.
- **Modal / form layout fixes:** do not nest `nowo_ui_styles` / `nowo_ui_scripts` / `nowo_ui_content` inside child templates that also call `parent()` (that replaced layout CDN / dropped modal markup). Form partials pass UiKit input/button classes explicitly.

```bash
composer update nowo-tech/breadcrumb-kit-bundle nowo-tech/form-kit-bundle
```

## From 2.0.12 to 2.0.13

**Dashboard look-and-feel (REQ-UI-001-kit):** the dashboard now depends on [UiKitBundle](https://github.com/nowo-tech/UiKitBundle) (`nowo-tech/ui-kit-bundle` ^1.4) for macros and `nowo-ui.css`.

- Replace Twig imports of `@NowoBreadcrumbKitBundle/dashboard/_ui_macros.html.twig` with `@NowoUiKitBundle/macros/ui.html.twig`.
- Load CSS via `asset('css/nowo-ui.css', 'nowo_ui_kit')` (not `nowo_breadcrumb_kit`). Keep `dashboard.js` on `nowo_breadcrumb_kit`.
- Remap `--nowo-ui-*` tokens under host chrome as before; the stylesheet now ships from UiKit (`/bundles/nowouikit/…`).
- Optional: set `nowo_ui_kit.css_framework` / `icon_set` in the host. If unset, BreadcrumbKit prepends from `dashboard.css_framework` / `dashboard.icon_set` (defaults `bootstrap5` / `bootstrap-icons`). Explicit host keys are never overridden.
- Ensure `NowoUiKitBundle` is registered (Symfony Flex usually does this via the Composer dependency).

```bash
composer update nowo-tech/breadcrumb-kit-bundle nowo-tech/ui-kit-bundle
php bin/console assets:install
```

## From 2.0.10 to 2.0.11

No Doctrine schema or public API changes.

- **`nowo-ui.css`:** hard-coded colours replaced with `--nowo-ui-*` custom properties (defaults match the previous slate/blue look). Hosts can remap under `.kit-admin` without forking Twig.
- Run `php bin/console assets:install` after upgrade.

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
php bin/console assets:install
```

## From 2.0.9 to 2.0.10

No Doctrine schema or public API changes.

- **Dashboard list filter UX:** collections and items indexes use a dedicated filter row (Filter + always-visible Clear filters), separate from action toolbars. New keys `dashboard.filter` / `dashboard.clear_filters`; `dashboard.search` / `dashboard.clear` remain for BC.
- **Twig / CSS:** macros `search_form()` / `search_input()` and classes `.nowo-ui-search` / `.nowo-ui-search__input`. Hosts that override those templates may want to adopt the same layout.

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
php bin/console assets:install
```

## From 2.0.8 to 2.0.9

No Doctrine schema or public API changes. Development Makefiles now soft-include optional monorepo `update-deps` helpers and prefer Docker Compose V2 when available.

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
```

## From 2.0.7 to 2.0.8

**Assets (REQ-ASSETS-004):** templates now use the named package `nowo_breadcrumb_kit`. If you overrode dashboard Twig and hard-coded `bundles/nowobreadcrumbkit/…`, switch to:

```twig
{{ asset('css/nowo-ui.css', 'nowo_breadcrumb_kit') }}
{{ asset('js/dashboard.js', 'nowo_breadcrumb_kit') }}
```

**Pagination:** `dashboard.pagination` also applies to the **items** list (same `enabled` / `per_page`).

No Doctrine schema changes. Config string values for `css_framework` / `icon_set` / modals are unchanged (backed by enums internally).

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
php bin/console assets:install
```

## From 2.0.6 to 2.0.7

**Dashboard look-and-feel (REQ-UI-001):** `dashboard.css_framework` now drives Twig macros and CDN choice in the demo layout (`bootstrap5`, `bootstrap4`, `tailwind`, `foundation`, `custom` / `none`). Semantic classes live in `bundles/nowobreadcrumbkit/css/nowo-ui.css`. Re-run `assets:install` after upgrade. Host layouts should keep `stylesheets` / `javascripts` blocks so pages can call `{{ parent() }}`.

No Doctrine schema or public PHP API renames. Default remains `bootstrap5`.

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
php bin/console assets:install
```

## From 2.0.5 to 2.0.6

**Dashboard security (REQ-UI-002):** enabling `dashboard.enabled: true` now requires `symfony/security-bundle` unless you set `security.allow_unauthenticated: true` (demo/dev only). Default `security.access_roles` is `['ROLE_ADMIN']`. Add host `access_control` for your `dashboard.path_prefix`.

Optional new keys (defaults preserve previous look): `dashboard.css_framework` (`bootstrap5`), `dashboard.icon_set` (`bootstrap-icons`).

No Doctrine schema or public Twig/API renames.

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
```

## From 2.0.4 to 2.0.5

No intentional breaking changes to the bundle API, routes, schema, or runtime dependencies. Patch release focused on org compliance (demo env docs, English Markdown, FrankenPHP banner) and the Symfony 8 demo PHP image.

- Local demo: rebuild/recreate after pulling (`make -C demo/symfony8 up`) to pick up **PHP 8.5** FrankenPHP. Refresh `.env` from `.env.example` if you still lack per-variable comments or `FRANKENPHP_MODE`.
- Application integrations are unaffected.

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
```

## From 2.0.3 to 2.0.4

No intentional breaking changes to the bundle API, routes, schema, or runtime dependencies. Patch release focused on PHPStan level 8 cleanliness and maintainer tooling.

- Contributors: `composer update` pulls require-dev `nowo-tech/phpstan-frankenphp`; `make phpstan` / `composer phpstan` use the empty baseline + FrankenPHP rulesets.
- Application integrations are unaffected.

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
```
## From 2.0.2 to 2.0.3

No intentional breaking changes to the bundle API, routes, schema, or runtime dependencies. Patch release focused on the Symfony 8 FrankenPHP demo.

- Local demo: FrankenPHP mode is no longer implied by `APP_ENV=dev`. Set **`FRANKENPHP_MODE=classic`** or **`worker`** in `demo/symfony8/.env` (default `worker`), then recreate the container (`docker compose up -d` / `make up`). See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).
- Application integrations are unaffected.

```bash
composer update nowo-tech/breadcrumb-kit-bundle:^2.0
```

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

When entity mappings or table names change, generate and run a migration (or apply documented SQL) before deploying.

Prefer the bundle CLI:

```bash
php bin/console nowo_breadcrumb_kit:generate-migration          # greenfield tables
php bin/console nowo_breadcrumb_kit:generate-migration --update # additive columns (e.g. 2.1.0)
```

You can still use Doctrine migrations manually or, in development only, `doctrine:schema:update`.

## General upgrade steps (any version)

1. Read [CHANGELOG.md](CHANGELOG.md) for the target version.
2. Run `composer update nowo-tech/breadcrumb-kit-bundle`.
3. Clear Symfony cache: `bin/console cache:clear`.
4. Sync `config/packages/nowo_breadcrumb_kit.yaml` with [CONFIGURATION.md](CONFIGURATION.md).