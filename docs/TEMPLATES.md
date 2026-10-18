# Templates

Dashboard pages of BreadcrumbKitBundle on the UiKit 2.1 page contract (REQ-UI-003). The controllers build the view
models with `Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages`, which also renders them (per-page template, layout
override, shared context). The templates extend a UiKit scaffold and fill data blocks only. Contract test:
`tests/Integration/Contract/PageContractTest.php` (UiKit `PageContractTestCase`, 13 fixtures).

## Table of contents

- [Pages](#pages)
- [Shared page context](#shared-page-context)
- [Dialogs](#dialogs)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
- [Bundle partials](#bundle-partials)
- [Front trail (host chrome)](#front-trail-host-chrome)
- [Allowed exceptions](#allowed-exceptions)

## Pages

| Page | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Collections | `dashboard/collection/index.html.twig` | `page/list` | `ListPage` (header: `dashboard.collections_heading`, `dashboard.collections_lead`, actions Export all, Import (opens the import dialog, `data-testid="bk-import-open"`), New collection (create; opens the collection form dialog, `data-testid="bk-collection-new"`); columns: code (required), name, items (end, priority 2), contextKey (priority 3); rows `{id, code, name, contextKey, items}`; rowActions: view items, export JSON, edit (dialog), delete (POST, CSRF `FormView`, `Confirm`, `fallbackHref`); filters: `DashboardGetSearchType` (`q`, `FilterField` width `lg`), `filtersSubmit`, `filtersClear`; `Pagination` (route `nowo_breadcrumb_kit_dashboard_collections_index`); `EmptyState`; attributes `data-testid="breadcrumb-kit-collections-table"`) + `import_form` | `nowo_ui_list_row`, `nowo_ui_modals` (collection form + import dialogs), `nowo_ui_scripts` (`js/dashboard.js`) |
| Collection form | `dashboard/collection/form.html.twig` | `page/form` | `FormPage` (title new / `Edit · code`, breadcrumbs, header action ← Collections; submit create / update, cancel; on edit: secondary actions Items + Export collection, `deleteAction` (`Confirm`, `fallbackHref`); attributes `data-testid="breadcrumb-kit-collection-form"`) + `collection` | `nowo_ui_form_body` (form errors, then the default child loop) |
| Delete collection (no-JS) | `dashboard/collection/delete.html.twig` | `page/confirm` | `ConfirmPage` (header `dashboard.confirm_delete`; `Confirm` `dashboard.delete_collection_confirm`; POST action with the CSRF form; cancel to the list; attributes `data-testid="breadcrumb-kit-collection-delete"`) + `collection` | — |
| Items of a collection | `dashboard/item/index.html.twig` | `page/list` | `ListPage` (header: `Items · code`, collection name as intro, actions ← Collections, Export collection, Collection styles (config; opens the collection form dialog), New item (create; opens the item form dialog, `data-testid="bk-item-new"`); columns: routeName (required), label, parent (priority 3), linkEnabled (priority 2); rows: `BreadcrumbItem`; rowActions: filter by route, edit (dialog), delete (POST, CSRF, `Confirm`, `fallbackHref`); filters as above; caption "n result(s) for q" while searching; `EmptyState` with Add one (create) or View all (search); attributes `data-testid="breadcrumb-kit-items-table"`) + `collection` | `nowo_ui_list_row`, `nowo_ui_modals` (item form + collection form dialogs), `nowo_ui_scripts` |
| Item form | `dashboard/item/form.html.twig` | `page/form` | `FormPage` (title `dashboard.item_new_page_title` / `Edit item #id`, breadcrumbs, header action ← Items; submit / cancel; `deleteAction` on edit; attributes `data-testid="breadcrumb-kit-item-form"`) + `collection`, `item` | `nowo_ui_form_body` (form errors, then the default child loop) |
| Delete item (no-JS) | `dashboard/item/delete.html.twig` | `page/confirm` | `ConfirmPage` (`Confirm` `dashboard.delete_item_confirm`; attributes `data-testid="breadcrumb-kit-item-delete"`) + `collection`, `item` | — |
| Import | `dashboard/import.html.twig` | `page/form` | `FormPage` (title `dashboard.import_page_title`, header action ← Collections, submit `dashboard.import_submit`, cancel; attributes `data-testid="breadcrumb-kit-import-form"`) | `nowo_ui_form_body` (format hint in the `muted` slot, form errors, child loop) |

Every page passes `bundle: 'NowoBreadcrumbKitBundle'` (theme exception `nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle`)
and `layout: web_ui.layout_template` (null = `nowo_ui_layout.admin`). Labels are `NowoBreadcrumbKitBundle` translation
keys (`DashboardPages::label()`). Cells use the slots `table_cell`, `table_cell_muted`, `muted`, `badge_neutral` (items
count, link "no") and `badge_success` (link "yes"); identifiers are plain `<code>`. Header breadcrumbs
(`PageHeader::breadcrumbs`: Collections › code › page) show when `nowo_ui_kit.breadcrumbs.breadcrumb_kit` is `false`;
otherwise the UiKit page header renders the BreadcrumbKit trail configured for the dashboard routes, and the "←" header
action keeps the way back.

Deletes from the lists and the edit forms open the UiKit confirm `<dialog>`; without JavaScript the `<noscript>` link
(`Action::fallbackHref`) opens the confirm page: `GET {path_prefix}/collections/{id}/delete` and
`GET {path_prefix}/collections/{collectionId}/items/{id}/delete`; both POST to the same URL with the CSRF form
(`DashboardPostDeleteType`: `_token` + `confirm`, token ids `delete_collection_<id>` / `delete_item_<id>`).

## Shared page context

`DashboardPages::render()` adds to every page:

| Variable | Content |
| --- | --- |
| `nowo_breadcrumb_kit_dialog_sizes` | `web_ui.dialog_sizes` (`collection_form`, `item_form`, `import`: `sm` / `md` / `lg` / `xl`) |
| `nowo_breadcrumb_kit_legacy_layout` | The 1.x layout wrapped by the legacy bridge, else null |

## Dialogs

All dialogs are the framework-free UiKit `dialog` partial (`nowo_ui_partial('dialog', page.bundle)`, opened by
`data-nowo-ui-dialog-open`, `js/nowo-ui-dialog.js`):

| Dialog id | Content | Opened by |
| --- | --- | --- |
| `bk-collection-form-dialog` | `_collection_form_partial.html.twig`, fetched from `…/collections/new?_partial=1` or `…/{id}/edit?_partial=1` by `js/dashboard.js` (`data-bk-dialog-url`) | New collection, row Edit, Collection styles |
| `bk-item-form-dialog` | `_item_form_partial.html.twig` (`…/items/new?_partial=1`, `…/items/{id}/edit?_partial=1`) | New item, Add one, row Edit |
| `bk-import-dialog` | `_import_partial.html.twig`, rendered inline (no fetch) | Import |

The openers are links to the full-page forms: without JavaScript they open the form pages. The forms POST normally;
valid submissions redirect back (flash), invalid ones render the full-page form (HTTP 422).

## Unique pages

- (none)

## Per-page overrides

`nowo_breadcrumb_kit.web_ui.templates.<page>` (`collections_index`, `collection_form`, `collection_delete`,
`items_index`, `item_form`, `item_delete`, `import`): a host template that extends
`@!NowoBreadcrumbKitBundle/dashboard/<template>` and overrides a data block (`nowo_ui_list_row`, `nowo_ui_list_after`,
`nowo_ui_list_filters`, `nowo_ui_form_body`, `nowo_ui_form_aside`) or `nowo_ui_modals`. Prefer theming the UiKit slots
(`nowo_ui_kit.theme`) and scaffolds once for every bundle.

## Bundle partials

| Partial | Role | Renders through |
| --- | --- | --- |
| `dashboard/_collection_form_partial.html.twig` | Collection form in the dialog (blocks `bk_form_fields`, `bk_form_actions`, `bk_form_submit`, `bk_form_cancel`; `data-bk-dialog-title`) | `form`, `form_actions`, `btn_primary`, `btn_secondary` slots; Cancel has `data-nowo-ui-dialog-close` |
| `dashboard/_item_form_partial.html.twig` | Item form in the dialog (same blocks) | same slots |
| `dashboard/_import_partial.html.twig` | Import form (blocks `bk_import_intro`, `bk_form_*`; `data-bk-submit-once`) | `muted`, `form`, `form_actions`, `btn_*` slots |
| `dashboard/_form_dialog.html.twig` | Empty dialog with the loading / error texts that `js/dashboard.js` fills | `nowo_ui_partial('dialog')`, `muted` slot |
| `dashboard/_dashboard_scripts.html.twig` | `js/dashboard.js` (`defer`, CSP nonce) | — |
| `dashboard/_legacy_layout.html.twig` | Deprecated bridge around a 1.x `web_ui.layout_template` that defines `nowo_breadcrumb_kit_content` | Removed in 3.0 |

The partials are fragments (AJAX responses), not pages; the contract test checks them with the UiKit assertions
(no forbidden markup, no framework classes outside slots, slots read).

## Front trail (host chrome)

`breadcrumb.html.twig`, `_breadcrumb_crumb.html.twig`, `_breadcrumb_collapse*.html.twig` are not admin pages: they keep
their 2.x markup and add the host classes / attributes of the UiKit slots `breadcrumbs`, `breadcrumb_link`,
`breadcrumb_separator` ([USAGE](USAGE.md#theme-slots-of-the-trail)).

## Allowed exceptions

- (none) — `allowedClasses()` is not overridden in the contract test. The JSON fields of the forms carry the UiKit
  semantic classes `nowo-ui-input nowo-ui-input--full nowo-ui-code` (no framework classes).
