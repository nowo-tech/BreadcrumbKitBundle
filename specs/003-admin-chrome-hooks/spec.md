# Feature Specification: Admin chrome hooks

**Feature Branch**: `003-admin-chrome-hooks`  
**Status**: Implemented (v2.4.0)  
**Created**: 2026-10-13  
**Depends on**: [`002-enhancements`](../002-enhancements/spec.md)  
**Upstream request**: symfony-beacon spec 134 FR-010 (kit upstream improvements)

---

## Summary

Hosts that embed the dashboard in their own admin (Beacon: Tailwind tokens, `<dialog>` modals, page header with actions, UiKit `row_actions_display`) had to copy eight dashboard templates (~570 lines) to change *chrome only*. This release exposes that chrome as configuration, partial paths, Twig blocks and a small layout contract, so the copies can be deleted. Behaviour (routes, CSRF ids, modal ids, `btn-bk-*`, `data-bk-*`, `dashboard.js`) does not move.

## User scenarios

- **US-01 (P1)** — Given a host with its own button / table classes, when it sets `dashboard.classes`, then every dashboard page and modal uses them and no page copy is needed.
- **US-02 (P1)** — Given a host whose modals are `<dialog>` elements, when it sets `dashboard.partials.modal_shell`, then the four modals render through its shell with the same ids, `*-label` and `*-body` targets, and `dashboard.js` keeps working.
- **US-03 (P1)** — Given a host layout with a page header, when it sets `bk_page_actions_in_layout` and renders `block('bk_page_actions')`, then the list actions appear in its header and the inline toolbar disappears.
- **US-04 (P2)** — Given a host that needs one structural change, when it overrides a page under `templates/bundles/` with `{% extends '@!NowoBreadcrumbKitBundle/…' %}`, then it redefines a single block and inherits the rest.
- **US-05 (P1)** — Given an existing app with no new options, when it upgrades, then the HTML is unchanged.

## Requirements

- **FR-001** `dashboard.classes`: allow-listed keys (`DashboardChrome::CLASS_KEYS`), unknown keys rejected at container build, empty values ignored; Twig global `nowo_breadcrumb_kit_classes`. Templates use `classes.key ?? <default>`; wrapper keys (`results`, `form_panel`, `modal_form_body`, `form_style_grid`) add an element only when set.
- **FR-002** `dashboard.partials`: `modals`, `modal_shell`, `pagination`, `action_inner`; Twig global `nowo_breadcrumb_kit_partials`. Defaults `dashboard/_dashboard_modals.html.twig`, `dashboard/_modal_shell.html.twig`, `dashboard/_pagination.html.twig` (markup extracted unchanged), bundle SVG icons.
- **FR-003** Options: `ui_kit_stylesheet` (bool, true), `collections_view` (`list`|`table`), `row_actions_position` (`start`|`end`), `cancel_first` (bool).
- **FR-004** Blocks on every page / partial (see USAGE § Customising the admin chrome); slot blocks `bk_page_title`, `bk_page_intro`, `bk_page_actions`, `bk_back_link` are definitions the stock layout never renders.
- **FR-005** Layout contract variables: `bk_page_actions_in_layout`, `bk_page_title_in_layout`, `bk_title_suffix`.
- **FR-006** `TwigPathsPass` registers `!NowoBreadcrumbKitBundle` → bundle views.
- **FR-007** `dashboard.js` carries the request `csp_nonce` attribute; full-page forms follow `css_framework` through UiKit macros when it is not Bootstrap (Bootstrap output unchanged).
- **FR-008** i18n: new key `dashboard.table_items` in all 7 locales; no hard-coded English in dashboard templates; catalogue parity enforced by test.

## Success criteria

- **SC-001** Snapshot tests (`DashboardDefaultOutputTest`, 14 renders) prove default output is identical to v2.3.2.
- **SC-002** `DashboardChromeHooksTest` renders a Beacon-like host (class map, shell, pagination, action inner, table view, actions at end, cancel first, layout slots, `@!` override) without any page copy.
- **SC-003** `make release-check` green (CS, Rector, PHPStan, Igor, coverage gate).

## Host adoption — symfony-beacon forks (spec 134 FR-024)

| Beacon fork | Verdict | How |
|-------------|---------|-----|
| `dashboard/base.html.twig` | **Delete** | `ui_kit_stylesheet: false`; nonce on `dashboard.js` now upstream; `layout_template` already configured. |
| `dashboard/_dashboard_modals.html.twig` | **Delete** | `partials.modal_shell: kit/breadcrumb/_modal_shell.html.twig` (host `<dialog>` shell, ~15 lines, not a fork) + classes `modal_message`, `modal_actions`, `btn_secondary`, `btn_danger`. |
| `dashboard/_import_partial.html.twig` | **Delete** | classes `modal_form`, `modal_form_body`, `modal_hint`, `modal_actions`, `btn_*`; `cancel_first: true`. |
| `dashboard/_collection_form_partial.html.twig` | **Delete** | as above + `form_style_grid: 'grid gap-3 sm:grid-cols-2 min-w-0'`. |
| `dashboard/_item_form_partial.html.twig` | **Delete** | as above. |
| `dashboard/collection/form.html.twig` | **Delete** | classes `breadcrumb_nav`, `breadcrumb_link`, `breadcrumb_separator`, `form_panel` (keeps `data-testid="breadcrumb-kit-collection-form"`), `form`, `form_actions`; layout sets `bk_page_title_in_layout`, `bk_title_suffix`. |
| `dashboard/collection/index.html.twig` | **Delete** | `collections_view: table`, `row_actions_position: end`, `partials.action_inner`, `partials.pagination: kit/_pagination.html.twig`, classes (`results` keeps `data-testid="breadcrumb-kit-collections-table"`); header actions via layout `block('bk_page_actions')`. The `?new=1` open-modal island moves to the layout bridge (`kit_admin_extra_javascripts`, collections index route only). |
| `dashboard/item/index.html.twig` | **Delete** | same config; layout renders `block('bk_back_link')` + `block('bk_page_actions')`; `page_intro`, `search_summary`, `badge_yes` / `badge_no` classes; `results` keeps `data-testid="breadcrumb-kit-items-table"`. |
| `kit/breadcrumb_dashboard_layout.html.twig` (bridge) | **Stays, +~8 lines** | sets `bk_page_actions_in_layout`, `bk_page_title_in_layout`, `bk_title_suffix`; `kit_admin_heading` from `block('bk_page_title')`; `kit_admin_header_actions` from the slot blocks. |

Accepted visual deltas (product decision for Beacon): empty states render as a paragraph inside the results panel instead of a table row; per-cell opacity variations collapse to one `td` class; the items page "Add one" link uses `btn_link`.

## Out of scope

- Moving the `?new=1` deep-link into `dashboard.js` (host behaviour).
- Removing the deprecated `window.__breadcrumbKitDashboard` fallback (next major).
