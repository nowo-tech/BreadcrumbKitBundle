# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[2.10.0] - 2026-10-28](#2100---2026-10-28)
- [[2.9.0] - 2026-10-20](#290---2026-10-20)
- [[2.8.0] - 2026-10-19](#280---2026-10-19)
- [[2.7.2] - 2026-10-18](#272---2026-10-18)
- [[2.7.1] - 2026-10-18](#271---2026-10-18)
- [[2.7.0] - 2026-10-18](#270---2026-10-18)
- [[2.6.0] - 2026-10-16](#260---2026-10-16)
- [[2.5.0] - 2026-10-13](#250---2026-10-13)
- [[2.4.1] - 2026-10-13](#241---2026-10-13)
- [[2.4.0] - 2026-10-13](#240---2026-10-13)
- [[2.3.2] - 2026-10-09](#232---2026-10-09)
- [[2.3.1] - 2026-10-09](#231---2026-10-09)
- [[2.3.0] - 2026-10-07](#230---2026-10-07)
- [[2.2.0] - 2026-09-28](#220---2026-09-28)
- [[2.1.9] - 2026-09-27](#219---2026-09-27)
- [[2.1.8] - 2026-09-24](#218---2026-09-24)
- [[2.1.7] - 2026-08-24](#217---2026-08-24)
- [[2.1.6] - 2026-08-20](#216---2026-08-20)
- [[2.1.5] - 2026-08-19](#215---2026-08-19)
- [[2.1.4] - 2026-08-19](#214---2026-08-19)
- [[2.1.3] - 2026-08-04](#213---2026-08-04)
- [[2.1.2] - 2026-08-04](#212---2026-08-04)
- [[2.1.1] - 2026-08-04](#211---2026-08-04)
- [[2.1.0] - 2026-08-04](#210---2026-08-04)
- [[2.0.14] - 2026-08-04](#2014---2026-08-04)
- [[2.0.13] - 2026-08-04](#2013---2026-08-04)
- [[2.0.12] - 2026-08-01](#2012---2026-08-01)
- [[2.0.11] - 2026-07-30](#2011---2026-07-30)
- [[2.0.10] - 2026-07-30](#2010---2026-07-30)
- [[2.0.9] - 2026-07-29](#209---2026-07-29)
- [[2.0.8] - 2026-07-28](#208---2026-07-28)
- [[2.0.7] - 2026-07-27](#207---2026-07-27)
- [[2.0.6] - 2026-07-27](#206---2026-07-27)
- [[2.0.5] - 2026-07-23](#205---2026-07-23)
- [[2.0.4] - 2026-07-23](#204---2026-07-23)
- [[2.0.3] - 2026-07-22](#203---2026-07-22)
- [[2.0.2] - 2026-07-16](#202---2026-07-16)
- [[2.0.1] - 2026-07-05](#201---2026-07-05)
- [[2.0.0] - 2026-07-05](#200---2026-07-05)
- [[1.2.1] - 2026-07-03](#121---2026-07-03)
- [[1.2.0] - 2026-07-03](#120---2026-07-03)
- [[1.1.0] - 2026-07-03](#110---2026-07-03)
- [[1.0.0] - 2026-07-03](#100---2026-07-03)

## [Unreleased]

## [2.10.0] - 2026-10-28

Security: the admin UI refuses to compile without form CSRF protection (CsrfProtectionPass, fail closed). See [UPGRADING](UPGRADING.md#to-2100).

### Security

- CSRF fails closed: new `CsrfProtectionPass` makes the container refuse to compile when the dashboard (`web_ui.enabled: true`) is on and form CSRF protection is unavailable (`security.csrf.token_manager` or `form.type_extension.csrf` missing, or `framework.form.csrf_protection: false`). Before, item / collection delete (`DashboardPostDeleteType` declares its own `csrf_token_id`), the CRUD forms and the JSON import accepted tokenless cross-site POSTs in that configuration. See [UPGRADING](UPGRADING.md#unreleased) and [SECURITY](SECURITY.md#threat-model).

### Changed

- Documentation: the USAGE collapse screenshot is now a WebP re-captured from the demo by Playwright (`docs/images/usage/breadcrumb-collapse.webp`, phone width, menu open); the legacy `docs/images/breadcrumb-collapse.png` is removed. `make -C demo/symfony8 demo-screenshots` refreshes it with the README set, and CONTRIBUTING documents `test-e2e` / `demo-screenshots` (REQ-DEMO-013).
- Documentation: USAGE states the Twig override freeze rule, the upgrade-safe alternatives (collection classes, `presentation.*`, UiKit theme slots, `web_ui.templates.<page>` with `@!NowoBreadcrumbKitBundle`, single partials) and how to recover upstream templates after a freeze (REQ-TWIG-001).

## [2.9.0] - 2026-10-20

Wave-2 REQ compliance: import logging, PHPStan without baseline, raw-form gate, Symfony 7.4/8.1 CI matrix and tooling. See [UPGRADING](UPGRADING.md#to-290).

### Added

- `BreadcrumbImporter` logs import start / success (info), rejected entries (warning) and failures (error) with structured, payload-free context through an optional `Psr\Log\LoggerInterface` (CLI `nowo_breadcrumb_kit:import` and dashboard import) (REQ-OBS-001).
- `make check-no-raw-html-form` (in `release-check`) and `.scripts/validate-translations.php` (key parity across the 7 locales, also in CI) (REQ-MAKE-002/004).
- CI `quality` job: PHPStan, Igor and translation parity on every push / PR (REQ-CS-005/006/008).

### Changed

- Dashboard dialog partials (`_import_partial`, `_item_form_partial`, `_collection_form_partial`) no longer print a raw hidden `_modal` input; the import controller still accepts it from 1.x integrations (REQ-TWIG-005).
- PHP-CS-Fixer imports global classes (`global_namespace_import`), so `src/` no longer uses leading-backslash class names (REQ-CS-001).
- PHPStan runs without baseline (removed the empty `phpstan-baseline.neon`); the kernel tests no longer swap global error handlers (`symfony/runtime` in require-dev keeps FrameworkBundle from registering them) (REQ-CS-005/006).
- CI: Symfony matrix 7.4 / 8.1 with every `symfony/*` package pinned to the cell minor (7.0 was below the supported range; 8.0 is end of maintenance); coverage job on Symfony 7.4 (REQ-SF-002).
- Makefile `help` in canonical block order with `igor`; `release-check` in REQ-MAKE-002 order; Rector capped at 2 parallel workers (REQ-MAKE-001/002).
- `.scripts/check-no-cursor-coauthor.sh` accepts git worktrees (REQ-GIT-001).
- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Documentation: wave-1 REQ compliance — README documentation list order (Demo section moved out of Documentation), CONTRIBUTING Cursor Agent settings and post-release-commit trailer check, canonical issue templates, PascalCase GitHub links, doc TOCs, Spec Kit inventory refresh (92/92).

## [2.8.0] - 2026-10-19

Item label translations as FormKit locale tabs (TranslatableMapType) instead of a JSON textarea; labels of other locales kept. See [UPGRADING](UPGRADING.md#to-280).

### Changed

- **Item label translations as locale tabs**: the raw JSON textarea of the item form (`translations`) is a FormKit
  `TranslatableMapType` (one text field per locale, rendered as UiKit locale tabs in the page and the dialog). Tabs
  come from `nowo_breadcrumb_kit.locales` (empty: the FormKit locales, i.e. `framework.enabled_locales`), the default
  tab from `default_locale`. Labels of locales that are not tabs are kept on save and listed in the field help; an
  emptied tab removes its label; no labels at all is stored as `null` as before. Each label is limited to 512
  characters (error on its tab).
- `BreadcrumbItemType` takes `$locales` and `$defaultLocale` constructor arguments (wired by the extension).
- Demo locked to FormKit 2.13 / UiKit 2.5; README screenshot of the item dialog re-captured with the label tabs.
- Requires `nowo-tech/form-kit-bundle` **^2.13** (was ^2.0) and `nowo-tech/ui-kit-bundle` **^2.5.1** (was ^2.2).

### Added

- Translation key `form.breadcrumb_item.translations.help_other_locales`; `translations.label` / `.help` reworded
  (no more JSON example) in every shipped locale.

## [2.7.2] - 2026-10-18

Locale lists accept %parameters% and env vars (no compile error with a parameter). See [UPGRADING](UPGRADING.md#to-272).

### Fixed

- `locales` given as a container parameter or env var (`'%app.locales%'`, `'%kernel.enabled_locales%'`,
  `'%env(json:APP_LOCALES)%'`) no longer breaks the container build ("Expected array, but got string"): `prepend()`
  processed the whole raw host config, where the parameter is still a string (and `kernel.enabled_locales` is not
  defined yet), and the node only accepted a literal list. `prepend()` now reads only the deprecated `dashboard.*` keys,
  `locales` accepts a list or a parameter / env placeholder, and an env var is read at runtime (the first locale
  becomes the loader default when `default_locale` is null). See [CONFIGURATION](CONFIGURATION.md#locales-from-a-parameter-or-env-var).

## [2.7.1] - 2026-10-18

Requires UiKit ^2.2 (breadcrumbs modes); dashboard search and item placeholders translated once. See [UPGRADING](UPGRADING.md#to-271).

### Changed

- Requires `nowo-tech/ui-kit-bundle` **^2.2** (default-theme polish: table header hierarchy, striped rows, filter row
  on a grid, subtle badges, muted help text in the dialogs). See [UPGRADING](UPGRADING.md#unreleased).
- Dashboard form dialogs are UiKit 2.2 remote dialogs: the New / Edit / Collection styles actions carry
  `Action::dialogId` and the full-page form URL (`data-nowo-ui-dialog-src`), `js/nowo-ui-dialog.js` fetches the form
  (the controllers answer an `X-Requested-With` GET, as well as `?_partial=1`, with the form partial) and
  `js/dashboard.js` only shows the loading text, sets the dialog title from the form and keeps submit-once. On a failed
  request the opener now navigates to the full-page form (was: an error text in the dialog).
- Dashboard page headers set `PageHeader::breadcrumbsMode` `auto` (`DashboardPages::BREADCRUMBS_MODE`): the
  BreadcrumbKit trail configured for the dashboard routes, else the dashboard's own trail (UiKit 2.2 falls back to it
  when the kit trail is empty).

### Removed

- The "← Collections" / "← Items" header actions of the collection form, item form, items list and import pages
  (2.7 workaround for a missing way back): the page-header trail is always there with UiKit 2.2.

### Deprecated

- `DashboardPages::dialogOpener()` (the `data-bk-dialog-url` / `?_partial=1` opener attributes): use
  `Action::$dialogId` with the form URL. `js/dashboard.js` still loads such openers until 3.0.

### Fixed

- **Missing / double translations (profiler):** the dashboard search placeholder was translated by the controllers and
  by the page model (`FilterField`), then looked up again by the form theme in `messages`. `DashboardGetSearchType`
  now uses the `NowoBreadcrumbKitBundle` domain and `search_placeholder` is the key `dashboard.search_placeholder`,
  translated once by the theme (no `FilterField` placeholder). The item form's literal placeholders are catalogue keys
  (`form.breadcrumb_item.route_name.placeholder` = `app_product_show`, `form.breadcrumb_item.path_pattern.placeholder`
  = `^/shop/.+`, 7 locales); the parent choices (item labels) and the inline-edit access keys are not translated
  (`choice_translation_domain: false`). A contract test renders every dashboard page with a `DataCollectorTranslator`
  and asserts no missing message.

### Demo

- The host layout prints the UiKit runtimes through the UiKit `scripts` partial (the hand-kept list lacked `modal`,
  `tabs` and `theme`).

## [2.7.0] - 2026-10-18

Dashboard pages on the UiKit 2.1 standard page contract (REQ-UI-003): list, form and confirm scaffolds, UiKit dialogs with no-JS fallbacks, front trail theme slots; dashboard deletes work again; deprecated `dashboard.*` keys mapped to `web_ui.*`. See [UPGRADING](UPGRADING.md#to-270).

Standard page contract (REQ-UI-003) on **UiKit 2.1** — minor: the 2.x configuration keeps working (deprecated aliases,
theme bridge); the dashboard markup changes (see [UPGRADING](UPGRADING.md#template-contract)).

### Added

- Dashboard pages on the UiKit scaffolds: collections and items lists (`page/list`), collection / item / import forms
  (`page/form`), no-JS delete confirmation pages (`page/confirm`, `GET` of the delete routes, `Action::fallbackHref`).
  View models built by `Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages`; [TEMPLATES.md](TEMPLATES.md).
- Canonical configuration `web_ui.enabled`, `web_ui.path_prefix`, `web_ui.layout_template` (default `null` = UiKit
  layout), `web_ui.templates.<page>`, `web_ui.import_max_bytes`, `web_ui.pagination`, `web_ui.dialog_sizes`;
  parameters `%nowo_breadcrumb_kit.web_ui.path_prefix%` / `.web_ui.enabled`.
- Forms open in framework-free UiKit dialogs (`js/dashboard.js` loads `?_partial=1` into them); the import dialog
  renders its form inline. Edit pages get a danger zone (delete through the UiKit confirm contract).
- The front trail (`breadcrumb_render()`) appends the host classes / attributes of the UiKit theme slots `breadcrumbs`,
  `breadcrumb_link`, `breadcrumb_separator` (2.6 markup when none are set); tests cover UiKit page headers rendering the
  trail (`nowo_ui_kit.breadcrumbs.breadcrumb_kit`).
- Page contract test (`PageContractTestCase`, 13 fixtures + partials), BC tests for the deprecated keys and the theme
  bridge; demo on the UiKit host layout with Playwright e2e and WebP screenshots (`docs/images/demo/`).
- Translation keys `dashboard.back_items`, `dashboard.empty_collections_search` (7 locales).

### Changed

- Requires `nowo-tech/ui-kit-bundle` **^2.1** (was ^1.4).
- Header, actions, filters, table, row actions, pagination, empty states, flashes and confirm dialogs come from UiKit and
  its theme; invalid form submissions from a dialog render the full-page form (HTTP 422) instead of a bare fragment.
- Deletes after a confirmation redirect to the list (the referer only when it is that list).
- FormKit profile `breadcrumb_kit` fields get `nowo-ui-input--full`; JSON fields use `nowo-ui-code` instead of the
  Bootstrap `font-monospace` utility; the search field has no FormKit label.
- `nowo_ui_kit.css_framework` / `icon_set` are seeded only from an explicit (deprecated) `dashboard.css_framework` /
  `icon_set` (the UiKit defaults are the same as the former BreadcrumbKit ones).

### Deprecated

- The `dashboard` configuration node: `enabled`, `path_prefix`, `layout_template`, `import_max_bytes`, `pagination`,
  `modals` → `web_ui.*`; `css_framework`, `icon_set` → `nowo_ui_kit`; `classes` → bridged into
  `nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle`; `partials`, `ui_kit_stylesheet`, `collections_view`,
  `row_actions_position`, `cancel_first` → no effect. Removed in 3.0.
- `Nowo\BreadcrumbKitBundle\Dashboard\DashboardChrome`, `Twig\BreadcrumbKitDashboardGlobalsExtension` (Twig globals
  `nowo_breadcrumb_kit_*`), 1.x layouts that only define `nowo_breadcrumb_kit_content` (wrapped by
  `dashboard/_legacy_layout.html.twig`).

### Removed

- `dashboard/layout.html.twig` (4-framework CDN switch), `dashboard/base.html.twig`, `dashboard/_pagination.html.twig`,
  `dashboard/_modal_shell.html.twig`, `dashboard/_dashboard_modals.html.twig`, `dashboard/_icons.html.twig`, the
  `bk_*` page blocks and the layout slot contract (`bk_page_actions_in_layout`, `bk_page_title_in_layout`).

### Fixed

- Dashboard deletes: the unnamed CSRF-only delete form was never submitted (Symfony ignores the token when it looks for
  submitted fields), so deletes redirected without deleting; `DashboardPostDeleteType` now carries a hidden `confirm`
  field.

## [2.6.0] - 2026-10-16

### Added

- **Collapse on narrow screens** (opt-in, spec [`004-collapse-narrow`](../specs/004-collapse-narrow/spec.md)): GitHub-style trail on phones. Below a width breakpoint the trail shows a "…" button, one separator and the current crumb (one line, ellipsis); the "…" button opens a dropdown with every previous crumb (same labels, icons and links; non-link crumbs as text). Above the breakpoint the trail is unchanged. See [USAGE](USAGE.md#collapse-on-narrow-screens).
  - Global config `presentation.collapse` (`enabled` false, `min_items` 3, `breakpoint` `40rem`; `collapse: true` shorthand; invalid breakpoints and `min_items` < 2 rejected at container build).
  - Per collection `responsiveConfig.collapse`: `true` / `false` or `{ "enabled", "min_items", "breakpoint" }` merged key by key over the global value (invalid stored values are ignored).
  - Markup: `data-breadcrumb-collapse` on `.breadcrumb-kit`, `li.breadcrumb-collapse` before the last separator with a `<details>` / `<summary>` disclosure (`aria-label` "Show previous pages", visible "…") and `ol.breadcrumb-collapse__menu[role=list]`. Normal items stay in the DOM.
  - Nonce-stamped styles: container query on the wrapper (`@container breadcrumb-kit`) with a viewport media query fallback, `--breadcrumb-kit-collapse-*` theming tokens with system-colour (light / dark) fallbacks, reduced-motion aware.
  - `js/breadcrumb-collapse.js` (external, `defer`, no inline handlers): Escape closes and returns focus to "…", outside click and choosing a link close. Works without JavaScript.
  - Overridable partials `_breadcrumb_collapse.html.twig` and `_breadcrumb_collapse_assets.html.twig`; `BreadcrumbCollapse` DTO, `BreadcrumbTrailView::$collapse` and `isCollapsible()` (Twig `trail.collapsible`).
  - Translations `breadcrumb.collapse.toggle` / `breadcrumb.collapse.menu` in en, es, de, fr, it, nl, pt.
- Demo: the `default` collection enables the collapse below `40rem` (try `/en/demo/deep/s1/s2/s3/s4/s5/s6` at phone width).

### Unchanged

- With the option off (default), or for trails shorter than `min_items`, `breadcrumb.html.twig` renders byte-identical HTML to 2.5.0 (tested against the 2.5.0 template).

## [2.5.0] - 2026-10-13

### Fixed

- The PSR-6 item-list cache (`cache.pool`, default `cache.app`) is now actually used: the pool was never injected because the extension looked it up on the isolated container used during `load()`. A pool id that does not exist still disables the cache.
- Cached item lists are invalidated when collections or items are created, updated, moved, deleted or imported through Doctrine (dashboard CRUD, `import`), so trails no longer stay stale after an edit.
- A `replace` import now runs in a transaction per collection: a failure while re-creating the tree rolls back and keeps the previous items.

## [2.4.1] - 2026-10-13

### Changed

- CI: GitHub Actions aligned with the org baseline (`actions/checkout` v7, `codecov/codecov-action` v7, `actions/cache` v6).
- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `nowo-tech/form-kit-bundle` 2.6.0 → 2.7.1 (development lock; #51).
- `doctrine/orm` 3.7.4 → 3.7.5 (development lock; #52).
- `nowo-tech/ui-kit-bundle` 1.9.1 → 1.11.0 (development lock; #53).

## [2.4.0] - 2026-10-13

Admin chrome hooks (spec [`003-admin-chrome-hooks`](../specs/003-admin-chrome-hooks/spec.md)): hosts can theme the dashboard without copying pages. Default output is unchanged (snapshot-tested).

### Added

- `dashboard.classes`: class map (allow-listed keys: buttons, row actions, toolbar, search, list/table cells, empty state, badges, page/modal forms, modal actions…). Unset keys keep the UiKit macro / legacy classes. Twig global `nowo_breadcrumb_kit_classes`.
- `dashboard.partials`: `modals`, `modal_shell` (one shell embedded by every modal; ids and `*-body` targets preserved), `pagination` (receives `pagination`, `item_count`, `route`, `route_params`, `query`, `nav_label`, `nav_trans_domain`), `action_inner` (e.g. `@NowoUiKitBundle/partials/_action_inner.html.twig` for `row_actions_display`). Twig global `nowo_breadcrumb_kit_partials`.
- `dashboard.ui_kit_stylesheet` (default `true`), `dashboard.collections_view` (`list`|`table`), `dashboard.row_actions_position` (`start`|`end`), `dashboard.cancel_first` (modal forms).
- Twig blocks on every dashboard page and partial: slot blocks `bk_page_title`, `bk_page_intro`, `bk_page_actions`, `bk_back_link` (host layouts pull them with `block()`), page blocks (`bk_page_header`, `bk_search`, `bk_toolbar`, `bk_results`, `bk_empty_state`, `bk_collections_list`, `bk_collection_row`, `bk_collection_table_row`, `bk_collection_row_actions`, `bk_items_table`, `bk_item_row`, `bk_item_link_cell`, `bk_item_row_actions`, `bk_pagination`, `bk_breadcrumb`, `bk_page_heading`, `bk_form`, `bk_form_fields`, `bk_form_actions`…) and modal blocks (`bk_modal_*`).
- Layout contract variables: `bk_page_actions_in_layout`, `bk_page_title_in_layout`, `bk_title_suffix`.
- `@!NowoBreadcrumbKitBundle/...` Twig namespace (always the bundle copy) so an app override can extend the original page and redefine blocks only.
- New default partials `dashboard/_pagination.html.twig` and `dashboard/_modal_shell.html.twig` (markup extracted from the pages, unchanged).
- Translation key `dashboard.table_items` (all 7 locales); catalogue parity + template key tests; render tests for the default output and every hook.

### Changed

- `dashboard.js` script tag carries `nonce` from the request attribute `csp_nonce` when set.
- Full-page collection/item forms use `ui.btn()` / `ui.row_actions()` when `css_framework` is not Bootstrap (Bootstrap output unchanged).

## [2.3.2] - 2026-10-09

### Changed

- CSP: the inline `<style>` blocks in `breadcrumb.html.twig` (inline-edit toolbar) and `dashboard/layout.html.twig` (`dashboard_head`) now carry `nonce="…"` from the request attribute `csp_nonce` when it is set. Added a template scan test (inline blocks must declare the nonce; no inline event handlers).

## [2.3.1] - 2026-10-09

### Dependencies

- Composer refresh: `doctrine/orm` 3.7.4, `doctrine/dbal` 4.5.0, `nowo-tech/form-kit-bundle` 2.6.0, `nowo-tech/ui-kit-bundle` 1.9.1, Symfony 7.4.20 components (lockfile); dev tooling `phpstan/phpstan` 2.3.1, `phpstan/phpstan-phpunit` 2.1.1, `phpstan/phpstan-symfony` 2.1.0, `rector/rector` 2.7.0, `igor-php/igor-php` 0.10.1.
- Demo: Symfony 8.1.8, `twig/twig` 3.30.0; regenerated `config/reference.php`.

[2.5.0]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.5.0
[2.4.1]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.4.1
[2.4.0]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.4.0
[2.3.2]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.3.2
[2.3.1]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.3.1

## [2.3.0] - 2026-10-07

### Changed

- **CSP-safe config islands:** `dashboard/layout.html.twig` now emits `<script type="application/json" id="nowo-breadcrumb-kit-dashboard">` (cssFramework, importPartialUrl, dashboardBase, i18n) instead of inline `window.__breadcrumbKitDashboard` / `window.breadcrumbKitI18n` assignments. Dashboard page templates emit `<script type="application/json" id="nowo-breadcrumb-kit-dashboard-page">{"page":"…"}</script>`.
- `dashboard.js` parses the islands and merges them; falls back to `window.__breadcrumbKitDashboard` / `window.breadcrumbKitI18n` for one minor (removal planned in 2.4.0).
- Inline breadcrumb editor: the inline IIFE in `breadcrumb.html.twig` is replaced by the external `js/breadcrumb-inline.js` (delegated `data-*` hooks only). `data-breadcrumb-kit-inline-wrap` no longer carries a `="1"` value.

### Deprecated

- `window.__breadcrumbKitDashboard` global config (read-only fallback in `dashboard.js`).

### Added

- `tests/Unit/Resources/CspSafeTemplatesTest.php` guarding the no-inline-script contract.

[2.3.0]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.3.0

## [2.2.0] - 2026-09-28

### Security

- Empty `security.access_roles` is fail-closed (deny). `DashboardAccessSubscriber` is always registered when `allow_unauthenticated` is false.

### Changed

- **Doctrine ORM SortDirection:** replace string `'ASC'`/`'DESC'` in `#[ORM\OrderBy]` and QueryBuilder `orderBy`/`addOrderBy` with `SortDirection::Ascending`/`Descending` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313); require `doctrine/orm` `^3.7` where applicable.

## [2.1.9] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[2.2.0]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.2.0
[2.1.9]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.1.9

## [2.1.8] - 2026-09-24

### Fixed

- **Worker mode (FrankenPHP, no kernel reset):** a caught unique-constraint violation in the collection dashboard no longer leaves the EntityManager closed for later requests. New `BreadcrumbEntityManagerResetter` and `DashboardEntityManagerSubscriber` reset a closed manager through `ManagerRegistry::resetManager()` on **every main request** and main-request exception (so public `breadcrumb_render()` recovers too); `CollectionCrudController` resets it right after catching the violation.
- **Worker mode:** `BreadcrumbCollectionRepository::findOneByCodeAndContextKey()` and `BreadcrumbItemRepository::findAllForCollection()` use `Query::HINT_REFRESH`, so edits made by another worker or process are visible even when the identity map is not cleared between requests.
- **Worker mode:** `DashboardAccessSubscriber` and `BreadcrumbInlineEditResolver` ignore the security token when no firewall handled the main request (no `_firewall_context`), so a token left in `TokenStorage` by a previous request is not trusted.

### Documentation

- [`docs/FRANKENPHP-WORKER-AUDIT.md`](FRANKENPHP-WORKER-AUDIT.md): full audit for worker mode without kernel / `services_resetter` reset (scenario B). Spec Kit: `FR-WORKER-001`, inventory **82/82**.
- [UPGRADING.md](UPGRADING.md): 2.1.7 → 2.1.8.

[2.1.8]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.1.8

## [2.1.7] - 2026-08-24

### Changed

- **Demos:** MySQL env policy in FrankenPHP stack (REQ-DEMO-011).
- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Demos:** wire Hot Reload bundle in FrankenPHP demo (REQ-DEMO-001).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[2.1.7]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.1.7

## [2.1.6] - 2026-08-20

### Security

- **Flex recipe:** ship `security_nowo_breadcrumb_kit.yaml` (`access_control` for `/breadcrumb-kit-admin`). Prefer **`^2.1.6`** so Flex installs the file (noted in 2.1.5; landed on `main` after that tag).

[2.1.6]: https://github.com/nowo-tech/BreadcrumbKitBundle/releases/tag/v2.1.6

## [2.1.5] - 2026-08-19

### Security

- **Flex recipe:** `security.access_control` for breadcrumb kit routes (REQ-SEC-004).

## [2.1.4] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

### Fixed

- **Tests:** restore **100%** PHP line coverage (REQ-TEST-003).

## [2.1.3] - 2026-08-04

### Added

- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Documentation

- [INSTALLATION.md](INSTALLATION.md) / [UPGRADING.md](UPGRADING.md): host Twig Extra Bundle contract; maintainer Twig-CS-Fixer scripts.

## [2.1.2] - 2026-08-04

### Documentation

- [UPGRADING.md](UPGRADING.md) / [INSTALLATION.md](INSTALLATION.md): clarify 2.1.0 schema (`path_pattern`, `match_attributes`) — `Unknown column … path_pattern` means the host DB was not migrated; prefer `nowo_breadcrumb_kit:generate-migration --update`, or in demos/dev `doctrine:schema:update --force`.

## [2.1.1] - 2026-08-04

### Fixed

- **Dashboard item form (full page):** render all FormKit fields in type order. Explicit `form_row` lists skipped `pathPattern` / `matchAttributes`, so those fields appeared after `parent` via `form_rest`.

### Changed

- Dashboard collection/item/import Twig (partials + full-page forms) iterate unrendered children (`{% for child in form %}` / `not child.rendered`) instead of listing each field + `form_rest`.

## [2.1.0] - 2026-08-04

### Added

- **CLI:** `nowo_breadcrumb_kit:generate-migration`, `:export`, `:import`, `:preview`.
- **Events:** `BreadcrumbTrailBuiltEvent` for trail enrichers after load.
- **Matching:** item `pathPattern` (PCRE) + `matchAttributes` JSON; route name `*` when constrained by path/attributes.
- **Preview service:** `BreadcrumbTrailPreview` for synthetic requests.
- Spec Kit feature [`002-enhancements`](../specs/002-enhancements/spec.md); baseline inventory **80/80**.

### Changed

- Dashboard form partials rely on FormKit field classes (no duplicate `ui.input()` overrides).
- `DashboardGetSearchType` uses FormKit; Flex recipe registers UiKit + FormKit and ships `nowo_form_kit.yaml` stub.
- Composer requires `symfony/console`, `symfony/event-dispatcher`, and `symfony/filesystem` explicitly (CLI / events / migration writer).

### Documentation

- Spec Kit baseline (`specs/001-baseline/`): SC-001 **80/80**; FormKit/UiKit as `FR-FORM-003` / `FR-UI-001` / `FR-DEP-001`; inventory external-kit table; constitution principle VI; SPEC-DRIVEN `REQ-UI-001-kit` / `REQ-FORM-001-kit`.
- FR-DEP-002: UiKit/FormKit remain hard requires in 2.x (optional packaging deferred).
- [UPGRADING.md](UPGRADING.md): 2.0.14 → 2.1.0; USAGE / INSTALLATION cover CLI, events, and matching.

## [2.0.14] - 2026-08-04

### Added

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Dashboard form types (`BreadcrumbItemType`, `BreadcrumbCollectionType`, `ImportBreadcrumbType`) use `FormOptionsTrait` + profile `breadcrumb_kit` (`#[FormKitConfig]`). Extension prepends that profile (and maps `dashboard.css_framework` → FormKit `css_framework`) without changing the host `default_profile`. Form types are registered as `form.type` services so `FormOptionsMerger` is injected.

### Fixed

- **Dashboard modals:** `base.html.twig` no longer redefines nested `nowo_ui_styles` / `nowo_ui_scripts` when calling `parent()`, which had replaced the layout Bootstrap CDN and left `data-bs-toggle="modal"` without Bootstrap JS. CSS guard `.modal.nowo-ui-modal.show { display: block; }` keeps UiKit’s `.nowo-ui-modal { display: none }` from hiding open Bootstrap modals.
- **Dashboard modals (DOM):** stop redefining nested `nowo_ui_content` inside `nowo_breadcrumb_kit_content` — Twig replaced the layout wrapper and dropped `_dashboard_modals.html.twig` from the page (Bootstrap then threw `Cannot read properties of undefined (reading 'backdrop')` for missing `#modal-bk-*` targets).
- **Dashboard forms:** apply UiKit `ui.input()` / `ui.btn()` / `ui.modal_dismiss_attrs()` on collection/item/import partials (UiKit is not a Symfony form theme — widgets need explicit classes). Modal forms force full-width inputs (override `.nowo-ui-input` max-width).

### Changed

- Symfony package constraints raised to **`^7.4 || ^8.0`** (aligned with FormKitBundle).

### Documentation

- INSTALLATION / CONFIGURATION / UPGRADING updated for FormKit composition and Symfony 7.4+.
- [UPGRADING.md](UPGRADING.md): 2.0.13 → 2.0.14.

## [2.0.13] - 2026-08-04

### Changed

- **REQ-UI-001-kit:** dashboard UI composes [UiKitBundle](https://github.com/nowo-tech/UiKitBundle) (`nowo-tech/ui-kit-bundle` ^1.4) instead of a private `_ui_macros.html.twig` / vendored `nowo-ui.css`.
  - Twig imports use `@NowoUiKitBundle/macros/ui.html.twig`.
  - Stylesheet loads via asset package `nowo_ui_kit` (`css/nowo-ui.css`); `js/dashboard.js` remains on `nowo_breadcrumb_kit`.
  - When the host has not configured `nowo_ui_kit`, the extension prepends `css_framework` / `icon_set` from `dashboard.*` (defaults `bootstrap5` / `bootstrap-icons`) so `ui.btn()` resolves without a framework argument. Explicit host `nowo_ui_kit` keys are never overridden.
  - Host apps that still reference `asset('css/nowo-ui.css', 'nowo_breadcrumb_kit')` must switch to package `nowo_ui_kit` and re-run `assets:install`.

### Fixed

- **Demo:** `GET /favicon.ico` and Chrome DevTools well-known probe return **204** (no locale prefix), avoiding noisy `NotFoundHttpException` in FrankenPHP logs.

### Documentation

- CONFIGURATION / USAGE / UPGRADING / INSTALLATION updated for UiKit composition.
- Spec Kit baseline inventory remapped to **74/74** `src/` files (UiKit owns macros/`nowo-ui.css`).
- [UPGRADING.md](UPGRADING.md): 2.0.12 → 2.0.13.

## [2.0.12] - 2026-08-01

### Added

- **`css_framework: custom` / `none` support without Bootstrap** (REQ-UI-001).
  - `dashboard.js` now reads `window.__breadcrumbKitDashboard.cssFramework` at init time. For non-bootstrap stacks it registers lightweight `[data-nowo-modal-open]` / `[data-nowo-modal-close]` handlers and dispatches a synthetic `show.bs.modal` event (with `relatedTarget`) so all existing modal listeners (form-load, confirm-delete, import) keep working without Bootstrap JS.
  - `nowo-ui.css` gains a self-contained modal overlay: `.nowo-ui-modal` (hidden by default), `.nowo-ui-modal.nowo-ui-modal-open` (visible), `body.nowo-modal-open` (scroll-lock), plus Bootstrap-shaped `.modal-dialog` / `.modal-content` / `.modal-header` / `.modal-body` / `.btn-close` styles scoped under `.nowo-ui-modal` so custom-framework hosts get correct modal chrome without any extra CSS.
  - Loading and error states inside modals use `nowo-ui-muted` / `nowo-ui-flash nowo-ui-flash--error` classes (retaining `alert alert-danger` as a dual class for bootstrap stacks).

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — new **"Using `css_framework: custom` without Bootstrap"** section with minimal host config, theming guide, and macro attribute notes.

## [2.0.11] - 2026-07-30

### Changed

- **`nowo-ui.css`**: colours use `--nowo-ui-*` custom properties (defaults unchanged: slate/blue). Hosts remapped under `.kit-admin` without forking templates.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — theming via `--nowo-ui-*` tokens
- [UPGRADING.md](UPGRADING.md) — From 2.0.10 to 2.0.11

## [2.0.10] - 2026-07-30

### Changed

- Collections and items index: list filter row matches admin list UX (search input + Filter + always-visible Clear filters), separate from action toolbars.
- Added `dashboard.filter` and `dashboard.clear_filters` translation keys (kept `dashboard.search` / `dashboard.clear` for BC).
- Semantic CSS: `.nowo-ui-search` / `.nowo-ui-search__input`; Twig macros `search_form()` / `search_input()`.

## [2.0.9] - 2026-07-29

### Fixed

- **REQ-TEST-011:** `make demo-smoke` follows HTTP redirects (`curl -L`) so a `302` from `/` to `/en/` still expects a final `200`.
- Demo `config/reference.php` comments for `css_framework` / `icon_set` / pagination aligned with enums and items list.

### Changed

- **REQ-MAKE-009 / REQ-MAKE-010:** Makefiles detect Compose V2 vs `docker-compose`, and soft-include optional monorepo `update-deps` helpers so standalone CI checkouts do not fail.

## [2.0.8] - 2026-07-28

### Added

- **REQ-PHP-001:** backed enums `CssFramework`, `IconSet`, `ModalSize` for dashboard config closed sets.
- **REQ-PERF-001:** dashboard **items** list uses the same `dashboard.pagination` settings as collections.
- **REQ-ASSETS-004:** named Symfony asset package `nowo_breadcrumb_kit` (`base_path` `/bundles/nowobreadcrumbkit`).
- **REQ-TEST-011:** `make demo-smoke` + `.github/workflows/demo-smoke.yml`.

### Changed

- Twig dashboard assets load via `asset('…', 'nowo_breadcrumb_kit')` instead of hard-coded `/bundles/…` paths.
- `.github/SECURITY.md` Supported Versions updated to **2.x**.
- Spec Kit baseline inventory remapped to **76/76** `src/` files (Security, enums, CSS, UI macros).

### Documentation

- README Documentation order (REQ-DOCS-002), Symfony/stars badges (REQ-DOCS-004), TOC fixes (REQ-DOCS-005).
- `docs/SECURITY.md`: REQ-SEC-004 **Pass (conditional)** / Medium (2026-07-28).
- [UPGRADING.md](UPGRADING.md): 2.0.7 → 2.0.8 (named asset package; items pagination).

## [2.0.7] - 2026-07-27

### Added

- **REQ-UI-001:** dashboard `_ui_macros.html.twig` (bootstrap5 / bootstrap4 / tailwind / foundation / custom), semantic `nowo-ui-*` CSS (`public/css/nowo-ui.css`), demo layout CDN switching by `css_framework`, and dual-class markup on list/table/toolbar/modals.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md): CSS framework table, host `parent()` stacking, overridable Twig paths.
- [UPGRADING.md](UPGRADING.md): 2.0.6 → 2.0.7 (`assets:install` after upgrade).

## [2.0.6] - 2026-07-27

### Added

- **REQ-UI-002:** `security.access_roles` / `security.access_checker` / `security.allow_unauthenticated`, `BreadcrumbKitAccessCheckerInterface`, `ConfigurableBreadcrumbKitAccessChecker`, `DashboardAccessSubscriber`, and `DashboardSecurityPass` (compilation fails if the dashboard is enabled without SecurityBundle unless `allow_unauthenticated`).
- **REQ-UI-001 (config):** `dashboard.css_framework` and `dashboard.icon_set` with Twig globals (`nowo_breadcrumb_kit_css_framework`, `nowo_breadcrumb_kit_icon_set`).
- **REQ-MAKE-002 / REQ-REL-003:** `.scripts/check-open-prs.sh` and `make check-open-prs` wired into `release-check`.
- **REQ-CI-002:** root `.scrutinizer.yml`.
- **REQ-SF-005:** `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` in `phpunit.xml.dist` and CI.
- **REQ-DOCS-005:** table of contents on long Markdown docs; **REQ-DOCS-018:** GitHub About Description / Website / Topics.

### Changed

- **Demo:** no committed `.env.dev`; `.gitignore` adds `/.pnpm-store`; demo YAML sets `security.allow_unauthenticated: true` with empty `access_roles` (demo only — never copy to production).

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) / [SECURITY.md](SECURITY.md) / [INSTALLATION.md](INSTALLATION.md): dashboard private-access contract and host `access_control` example.
- [UPGRADING.md](UPGRADING.md): 2.0.5 → 2.0.6 (SecurityBundle required when dashboard enabled).

## [2.0.5] - 2026-07-23

### Changed

- **Demo (`demo/symfony8`):** FrankenPHP base image **PHP 8.5** (`dunglas/frankenphp:1-php8.5-alpine`); `.env.example` / `.env.test` commented per variable; demo `.gitignore` grouped by category (REQ-DEMO-003 / REQ-ENV-001 / REQ-DEMO-010).

### Added

- **REQ-DOCS-017:** `docs/images/frankenphp-friendly.png` + README FrankenPHP worker-friendly banner (after REQ-CS-005).

### Documentation

- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md): troubleshooting in English (REQ-DOCS-016); PHP 8.5 policy note.
- [CONTRIBUTING.md](CONTRIBUTING.md): note that PHPStan includes `nowo-tech/phpstan-frankenphp` (require-dev).
- [UPGRADING.md](UPGRADING.md): 2.0.4 → 2.0.5 (no API or schema changes).

## [2.0.4] - 2026-07-23

### Changed

- **Static analysis:** clear PHPStan level 8 findings (typing, form `@extends`, DataTransformer generics, redundant guards). Empty [`phpstan-baseline.neon`](../phpstan-baseline.neon) kept in the tree for future suppressions.
- **Dev tooling:** require-dev [`nowo-tech/phpstan-frankenphp`](https://github.com/nowo-tech/PhpStanFrankenPhp) (`^1.0`); `phpstan.neon.dist` includes classic + worker rulesets. PHP CS Fixer enables `fully_qualified_strict_types.import_symbols`.
- **`composer.lock` / demo lock:** dependency sync (incl. `nowo-tech/phpstan-frankenphp` v1.0.1). No runtime `require` changes.

### Documentation

- [UPGRADING.md](UPGRADING.md): 2.0.3 → 2.0.4 (no API or schema changes).

## [2.0.3] - 2026-07-22

### Changed

- **Demo (`demo/symfony8`):** FrankenPHP classic vs worker is selected with **`FRANKENPHP_MODE`** (`.env` / Compose; default `worker`), independent of `APP_ENV`. Entrypoint extracted to `docker/entrypoint.sh`.

### Documentation

- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md): document `FRANKENPHP_MODE` and align overview / dev-vs-prod with the entrypoint.
- [UPGRADING.md](UPGRADING.md): 2.0.2 → 2.0.3 (no API or schema changes).

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
