# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 2.7.2](#to-272)
- [To 2.7.1](#to-271)
- [To 2.7.0](#to-270)
  - [Template contract](#template-contract)
  - [Deprecated configuration](#deprecated-configuration)
- [To 2.6.0](#to-260)
- [To 2.5.0](#to-250)
- [To 2.4.1](#to-241)
- [To 2.4.0](#to-240)
- [To 2.3.2](#to-232)
- [To 2.3.1](#to-231)
- [To 2.3.0](#to-230)
- [To 2.2.0](#to-220)
- [To 2.1.9](#to-219)
- [From 2.1.7 to 2.1.8](#from-217-to-218)
- [From 2.1.6 to 2.1.7](#from-216-to-217)
- [From 2.1.5 to 2.1.6](#from-215-to-216)
- [From 2.1.4 to 2.1.5](#from-214-to-215)
- [From 2.1.3 to 2.1.4](#from-213-to-214)
- [From 2.1.2 to 2.1.3](#from-212-to-213)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
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
- [From pre-release / local copies to 1.0.0](#from-pre-release--local-copies-to-100)
- [Doctrine schema](#doctrine-schema)
- [General upgrade steps (any version)](#general-upgrade-steps-any-version)

## Unreleased

## To 2.7.2

From **2.7.1**:

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

## To 2.7.1

From **2.7.0**:

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

Requires UiKit **^2.2** (`composer require nowo-tech/ui-kit-bundle:^2.2`). No configuration change. What hosts may
notice:

- **Way back**: the "← Collections" / "← Items" header actions are gone; the page header trail (the BreadcrumbKit
  trail you configured for the dashboard routes, else Collections › code › page) leads back.
- **Form dialogs**: openers are UiKit remote-dialog links (`data-nowo-ui-dialog-open` + `data-nowo-ui-dialog-src` =
  the full-page form URL) instead of `data-bk-dialog-url="…?_partial=1"`. The dialog body carries
  `data-nowo-ui-dialog-body`. Host layouts must load UiKit's `nowo-ui-dialog.js` (2.2 `scripts` partial) — they
  already did for the 2.7 dialogs. The new / edit routes now return the form partial for any GET with
  `X-Requested-With: XMLHttpRequest` (as for `?_partial=1`): a host script that fetched those pages over XHR and
  expected the full page must drop that header.
- Host templates that still print `DashboardPages::dialogOpener()` attributes keep working (deprecated, removed in 3.0).
- UiKit 2.2 default theme: small uppercase table headers, striped rows, the search filter as a grid row, subtle badges,
  muted help text (UiKit UPGRADING "Template contract (2.2)").

## To 2.7.0

From **2.6.0**:

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

Standard page contract (REQ-UI-003, UiKit 2.1). Requires `nowo-tech/ui-kit-bundle` **^2.1**. Minor release: every
2.x key keeps working (deprecated, bridged into the UiKit theme); what changes is the markup of the dashboard pages.
The front trail (`breadcrumb_render()`) is unchanged unless your `nowo_ui_kit.theme` sets the `breadcrumbs`,
`breadcrumb_link` or `breadcrumb_separator` slots.

```bash
composer update nowo-tech/breadcrumb-kit-bundle nowo-tech/ui-kit-bundle
php bin/console assets:install
```

1. **Layout.** The pages extend UiKit scaffolds, which extend `nowo_ui_kit.layouts.admin` (default
   `@NowoUiKit/layout/admin.html.twig`) — or `nowo_breadcrumb_kit.web_ui.layout_template` when you set it. That layout
   renders the canonical blocks (`nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_navigation`,
   `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, `nowo_ui_scripts`) and loads
   `nowo-ui.css` + `js/nowo-ui-confirm.js` + `js/nowo-ui-dialog.js` (UiKit asset package `nowo_ui_kit`). See UiKit
   `docs/PAGE_CONTRACT.md` "Example: host integration".
   - The bundle CDN layout `@NowoBreadcrumbKitBundle/dashboard/layout.html.twig` is gone; that value of
     `layout_template` reads as `null` (UiKit layout).
   - A 2.x host layout that only defines `nowo_breadcrumb_kit_content` (and `title` / `stylesheets` / `javascripts`) is
     wrapped by the deprecated `dashboard/_legacy_layout.html.twig` (a deprecation is triggered per page): it keeps
     working and gains `nowo-ui.css` (unless `dashboard.ui_kit_stylesheet: false`), the UiKit scripts and the UiKit page
     header. A layout that wraps `nowo_breadcrumb_kit_content` in its own `nowo_ui_content` block (a copy of the 2.x
     bundle layout) loses the page header: render the canonical blocks instead.
2. **Breadcrumbs in the page header.** With `nowo_ui_kit.breadcrumbs.breadcrumb_kit: true` (UiKit default) the page
   headers render your BreadcrumbKit trail of the current route (collection `nowo_ui_kit.breadcrumbs.collection`, else
   `default_collection`): add items for the dashboard routes (`nowo_breadcrumb_kit_dashboard_*`) if you want a trail
   there, or set `breadcrumb_kit: false` to show the pages' own Collections › code › page trail.
3. **Overrides.** Re-check every `templates/bundles/NowoBreadcrumbKitBundle/dashboard/` file against
   [TEMPLATES.md](TEMPLATES.md) — most can be deleted in favour of `nowo_ui_kit.theme`; keep bundle-specific ones as
   `web_ui.templates.<page>` targets extending `@!NowoBreadcrumbKitBundle/…`.
4. **Config.** Move `dashboard.*` to `web_ui.*` and `nowo_ui_kit` when convenient (the old keys work until 3.0).
   Routing imports may keep `%nowo_breadcrumb_kit.dashboard.path_prefix%` (same value as
   `%nowo_breadcrumb_kit.web_ui.path_prefix%`).

### Template contract

What hosts may notice:

| 2.6 | 2.7 |
| --- | --- |
| `collection/index`, `item/index` with their own toolbar, search form, list / table, `_pagination`, modals | extend `@NowoUiKit/page/list.html.twig`; fill `nowo_ui_list_row`, `nowo_ui_modals`, `nowo_ui_scripts` |
| `collection/form`, `item/form`, `import` with their own breadcrumb nav, `<h1>`, buttons | extend `@NowoUiKit/page/form.html.twig`; fill `nowo_ui_form_body` |
| Delete through the Bootstrap modal `#modal-bk-delete` filled by `dashboard.js` | UiKit confirm `<dialog>` per row (CSP-safe) + no-JS confirm page (`page/confirm`, `GET …/delete`) |
| Bootstrap modals `#modal-bk-collection-form`, `#modal-bk-item-form`, `#modal-bk-import` (`_modal_shell`, `_dashboard_modals`) | UiKit `dialog` partial: `#bk-collection-form-dialog`, `#bk-item-form-dialog` (forms fetched by `js/dashboard.js` from `data-bk-dialog-url`), `#bk-import-dialog` (inline form) |
| `layout.html.twig` (CDN switch), `base.html.twig`, `_pagination`, `_modal_shell`, `_dashboard_modals`, `_icons` | **removed** (UiKit layout, scaffolds, `pagination` / `dialog` / `confirm` partials, UiKit icons) |
| Blocks `bk_page_header`, `bk_search`, `bk_toolbar`, `bk_results`, `bk_empty_state`, `bk_collections_list`, `bk_collection_row`, `bk_collection_table_row`, `bk_collection_row_actions`, `bk_pagination`, `bk_page_heading`, `bk_search_summary`, `bk_items_table`, `bk_items_table_head`, `bk_item_row`, `bk_item_link_cell`, `bk_item_row_actions`, `bk_breadcrumb`, `bk_form`, `bk_back_link`, `bk_modal_*`, slot blocks `bk_page_title` / `bk_page_intro` / `bk_page_actions` | removed — use the scaffold data blocks or the theme; the legacy layout bridge still defines `bk_page_title` |
| Partial blocks `bk_form_fields`, `bk_form_actions`, `bk_form_submit`, `bk_form_cancel`, `bk_import_intro` | kept in `_collection_form_partial`, `_item_form_partial`, `_import_partial` (now theme slots; Cancel closes the UiKit dialog) |
| Twig context `collections`, `collection_item_counts`, `items`, `search`, `search_form`, `delete_form`, `pagination` (`current_page`…), `dashboard_routes`, `modal_classes`, `title` | `page` = `ListPage` / `FormPage` / `ConfirmPage` (+ `import_form`, `collection`, `item`) |
| Layout contract `bk_page_actions_in_layout`, `bk_page_title_in_layout`, `bk_title_suffix` | header actions / title in `nowo_ui_page_header`; only `bk_title_suffix` is still read by the legacy bridge |
| Invalid dialog submission → bare form fragment | full-page form, HTTP 422 |
| Collections `list` view (default) / `table` view | always the UiKit table (code, name, items, context); `Context` hides below 60rem of table width |
| Link column plain text yes / no | badges (`badge_success` / `badge_neutral` slots) |
| JSON CSP island `#nowo-breadcrumb-kit-dashboard(-page)` | removed (`dashboard.js` reads `data-*` attributes) |

### Deprecated configuration

| Key | Replacement | Behaviour until 3.0 |
| --- | --- | --- |
| `dashboard.enabled`, `path_prefix`, `layout_template`, `import_max_bytes`, `pagination.*` | `web_ui.*` | aliases (the `web_ui` key wins) |
| `dashboard.modals.*` | `web_ui.dialog_sizes.*` | `normal` → `md`; `delete` no effect |
| `dashboard.css_framework`, `dashboard.icon_set` | `nowo_ui_kit.css_framework`, `nowo_ui_kit.icon_set` | seeded into UiKit when set here and not in `nowo_ui_kit` |
| `dashboard.classes.*` | `nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle.classes.<slot>` | bridged (prepended; the value replaces the slot's framework defaults, row-action slots are additive) |
| `dashboard.partials.*` | `nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle.partials.<name>` | ignored (inputs differ: e.g. the UiKit `pagination` partial gets `page`, not `current_page`) |
| `dashboard.ui_kit_stylesheet` | — | read only by the legacy layout bridge |
| `dashboard.collections_view`, `row_actions_position`, `cancel_first` | — | no effect |
| `DashboardChrome`, `BreadcrumbKitDashboardGlobalsExtension` (globals `nowo_breadcrumb_kit_*`) | the UiKit theme | kept, unused by the pages |

`dashboard.classes` → slot: `btn_primary`, `btn_secondary`, `btn_danger`, `btn_link`, `row_actions`, `toolbar`,
`page_title`, `page_intro`, `breadcrumb_link`, `breadcrumb_separator`, `empty`, `muted`, `table_wrap`, `table`, `form`,
`form_panel`, `form_actions` → same name; `action_view`, `action_edit` → `action_neutral` (`action_edit` wins);
`action_delete` → `action_danger`; `breadcrumb_nav` → `breadcrumbs`; `search_form` → `filters`; `badge`, `badge_no` →
`badge_neutral` (`badge_no` wins); `badge_yes` → `badge_success`; `thead_row` → `table_head_row`; `th` →
`table_head_cell`; `th_actions` → `table_actions_head`; `row` → `table_row`; `td` → `table_cell`; `td_actions` →
`table_actions_cell`; `modal_message` → `confirm_message`; `modal_form_body` → `dialog_body`. No slot (dropped):
`search_input`, `search_summary`, `results`, `list`, `list_item`, `modal_loading`, `modal_hint`, `modal_form`,
`modal_actions`, `form_style_grid`. Bridged values only apply to the dashboard pages
(`theme.bundles.NowoBreadcrumbKitBundle`), never to the front trail.

## To 2.6.0

From **2.5.0** — new opt-in feature, no breaking change.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
php bin/console assets:install
```

- Nothing changes unless you enable **collapse on narrow screens** (GitHub-style "…" menu + current crumb below a breakpoint): `nowo_breadcrumb_kit.presentation.collapse: true` (or `{ enabled: true, min_items: 3, breakpoint: '40rem' }`), or per collection `"collapse": true` in **Responsive (JSON)**. See [USAGE](USAGE.md#collapse-on-narrow-screens).
- `assets:install` publishes the new `js/breadcrumb-collapse.js` (needed only when the option is on).
- If you override `breadcrumb.html.twig`, the collapse needs three hooks in your copy (wrapper attribute + two includes); without them your override keeps working and simply never collapses.
- New translation keys `breadcrumb.collapse.toggle` and `breadcrumb.collapse.menu` (7 locales); override them in `translations/NowoBreadcrumbKitBundle.<locale>.yaml` if needed.
- Theme the dropdown with the `--breadcrumb-kit-collapse-*` custom properties (defaults follow `color-scheme`).

## To 2.5.0

From **2.4.1** — fixes - item-list cache wired and invalidated on writes, atomic replace import.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

- The item-list cache (default `cache.app`, TTL 60s) is now actually enabled. Doctrine writes invalidate it automatically; changes made with raw SQL can stay stale for up to `cache.ttl` — set `cache.pool: ''` to disable it.
- A failed replace import now rolls back (existing items survive) and closes the EntityManager.

## To 2.4.1

From **2.4.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

- No breaking changes. No application upgrade steps.

## To 2.4.0

From **2.3.x** — admin chrome hooks. No breaking changes; with no new options the dashboard renders as before.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
php bin/console cache:clear
```

- If you copied dashboard pages under `templates/bundles/NowoBreadcrumbKitBundle/` only to change classes, modal markup, pagination or button order, replace them with `dashboard.classes`, `dashboard.partials`, `dashboard.collections_view`, `dashboard.row_actions_position`, `dashboard.cancel_first`, `dashboard.ui_kit_stylesheet` and a layout that pulls `block('bk_page_actions')` (see [USAGE — Customising the admin chrome](USAGE.md#customising-the-admin-chrome)).
- A remaining override can now extend the original: `{% extends '@!NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig' %}` and redefine blocks only.
- Copies of `_dashboard_modals.html.twig` keep working; the stock file now renders `dashboard/_modal_shell.html.twig` (same markup).
- The deprecated `window.__breadcrumbKitDashboard` / `window.breadcrumbKitI18n` fallback in `dashboard.js` is still honoured in 2.4.0 (minor release); it will be removed in the next major.

## To 2.3.2

No action required. Inline `<style>` blocks now emit `nonce` from the request attribute `csp_nonce` (see [Security — CSP](SECURITY.md#content-security-policy-csp)). If you overrode `breadcrumb.html.twig` or the `dashboard_head` block, add the nonce to your own inline `<style>` (`{% set _csp_nonce = app.request ? app.request.attributes.get('csp_nonce')|default('') : '' %}` then `<style{% if _csp_nonce %} nonce="{{ _csp_nonce }}"{% endif %}>`) (or drop the override if it only added the nonce).

## To 2.3.1

From **2.3.0** — dependency refresh only.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

- No breaking changes. No application upgrade steps.

## To 2.3.0

From **2.2.0** — CSP-safe JSON config islands (no inline JS in dashboard templates).

```bash
composer update nowo-tech/breadcrumb-kit-bundle
php bin/console assets:install public
php bin/console cache:clear
```

- Dashboard config is now a JSON island: `<script type="application/json" id="nowo-breadcrumb-kit-dashboard">`. Page templates add `id="nowo-breadcrumb-kit-dashboard-page"`. `dashboard.js` reads both.
- `window.__breadcrumbKitDashboard` / `window.breadcrumbKitI18n` are still honoured as a **deprecated fallback** in `dashboard.js` for this minor; they will be removed in 2.4.0. If you override `dashboard/layout.html.twig`, switch to the island.
- Inline breadcrumb editor: the inline `<script>` is gone; the template loads `js/breadcrumb-inline.js` (run `assets:install`). If you override `breadcrumb.html.twig`, drop your inline IIFE, include the script via `asset('js/breadcrumb-inline.js', 'nowo_breadcrumb_kit')` and use `data-breadcrumb-kit-inline-wrap` (no value needed), `data-bk-inline-open`, `data-bk-inline-dialog`, `data-bk-inline-close`.
- Allow the island with CSP: JSON islands are non-executable and need no nonce. The inline `<style>` in `breadcrumb.html.twig` still needs `style-src` nonce/`unsafe-inline` or an override.

## To 2.2.0

From **2.1.9** — dashboard `access_roles` fail-closed; Doctrine `SortDirection`.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
php bin/console cache:clear
```

- Empty `security.access_roles` no longer allows dashboard access. Set at least one role, a custom `access_checker`, or (demo only) `allow_unauthenticated: true`.
- Ensure `doctrine/orm` is `^3.7` (SortDirection).

## To 2.1.9

From **2.1.8** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/breadcrumb-kit-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

This document describes breaking changes and upgrade notes between versions. Sections are ordered from newest to oldest.

## From 2.1.7 to 2.1.8

No configuration change is required. Behaviour changes relevant to FrankenPHP worker mode (kernel **not** reset / no `services_resetter` between requests):

- The collection and item lookups used by the loader and the dashboard now refresh already-managed entities from the database (`Query::HINT_REFRESH`). Unflushed in-memory changes to those entities are overwritten when they are loaded again.
- The dashboard access check and the inline editor only read the user from `TokenStorage` when a firewall handled the main request. Routes outside every firewall are evaluated with a `null` user, as they already were on a freshly booted kernel.
- `CollectionCrudController` accepts a new optional `BreadcrumbEntityManagerResetter` constructor argument (wired automatically).
- A closed breadcrumb EntityManager is replaced on **every** main HTTP request (and main-request exception), so public trail rendering recovers after a failed dashboard flush on the same worker.
- The bundle never calls `EntityManager::clear()`. Under worker mode without `services_resetter`, clearing the identity map between requests remains the application's responsibility.
- Full write-up: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

## From 2.1.6 to 2.1.7

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

## From 2.1.5 to 2.1.6

Prefer **`^2.1.6`** so Flex copies `security_nowo_breadcrumb_kit.yaml`. Review `access_control` if you changed `dashboard.path_prefix`.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

## From 2.1.4 to 2.1.5

Review Flex recipe `security_nowo_breadcrumb_kit.yaml` access rules after install/update.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

## From 2.1.3 to 2.1.4

No application upgrade steps.

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

## From 2.1.2 to 2.1.3

Additive (REQ-TWIG-004). No schema or PHP API changes.

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically. Updating the package also pulls these as Composer dependencies.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).

```bash
composer update nowo-tech/breadcrumb-kit-bundle
```

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
