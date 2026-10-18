# Configuration

Configuration root key: `nowo_breadcrumb_kit` (see `Nowo\BreadcrumbKitBundle\DependencyInjection\Configuration`).

## Table of contents

- [Reference](#reference)
- [Inline edit (modal)](#inline-edit-modal)
- [Dashboard (CRUD)](#dashboard-crud)
  - [Look and feel (UiKit page contract)](#look-and-feel-uikit-page-contract)
  - [Form Kit (`nowo_form_kit`)](#form-kit-nowo_form_kit)
  - [Deprecated dashboard keys](#deprecated-dashboard-keys)
  - [Twig helper](#twig-helper)
- [Example](#example)

## Reference

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `project` | `string\|null` | `null` | Optional identifier when several apps share one database. |
| `doctrine.connection` | `string` | `default` | Doctrine connection name for entity metadata. |
| `doctrine.table_prefix` | `string` | `''` | Prefix for table names (`dashboard_breadcrumb_collection`, `dashboard_breadcrumb_item`). |
| `cache.ttl` | `int` | `60` | TTL in seconds for the PSR-6 cache of serialized breadcrumb items (0 disables TTL semantics per pool). |
| `cache.pool` | `string` | `cache.app` | PSR-6 cache pool service id. Empty string keeps the loader without a pool (no item-list cache); an id that does not exist in the container also disables the cache. Entries are deleted automatically on Doctrine writes (dashboard, import); raw SQL changes wait for `cache.ttl`. |
| `locales` | `string[]` | `[]` | Supported locales for resolving labels from JSON translations on items. |
| `default_locale` | `string\|null` | `null` | Fallback locale; if null and `locales` is non-empty, the first locale is used. |
| `default_collection` | `string` | `default` | Collection code used when Twig helpers omit an explicit collection. |
| `presentation.home_icon` | `string\|null` | `null` | Fallback home/root icon when the collection `homeIcon` is empty (HTML, emoji, or an app-specific token such as `tabler:home` with Symfony UX Icons). |
| `presentation.home_icon_replaces_label` | `bool` | `true` | When `true` and a home icon is set, the first crumb shows the icon instead of its text label (`aria-label` keeps the label for accessibility). |
| `presentation.hide_when_single_root` | `bool` | `false` | When `true`, hides the trail on pages where the only crumb is the root item and it is the current page (typical home). Per-collection override: `responsiveConfig.hide_when_single_root` in the dashboard. |
| `presentation.collapse.enabled` | `bool` | `false` | Opt-in **collapse on narrow screens** (GitHub-style): below `breakpoint` the trail shows only a "…" menu with the previous crumbs, one separator and the current crumb. `collapse: true` is a shorthand. Off = markup identical to 2.5.0. See [USAGE](USAGE.md#collapse-on-narrow-screens). |
| `presentation.collapse.min_items` | `int` | `3` | Minimum number of crumbs (current included) before the trail collapses; `>= 2`. Shorter trails render unchanged. |
| `presentation.collapse.breakpoint` | `string\|int` | `40rem` | Width of the `.breadcrumb-kit` wrapper (container query; viewport media query fallback) below which the trail collapses. Length in `px`, `rem` or `em` (`'40rem'`, `'640px'`) or an integer (pixels). Anything else is rejected at container build. |
| `web_ui.enabled` | `bool` | `false` | When `true`, registers the dashboard (CRUD) controllers (requires `symfony/form` + `symfony/framework-bundle`; import routing as below). |
| `web_ui.path_prefix` | `string` | `/breadcrumb-kit-admin` | URL prefix of the dashboard routes (leading slash, no trailing slash). Parameter `%nowo_breadcrumb_kit.web_ui.path_prefix%` (also `%nowo_breadcrumb_kit.dashboard.path_prefix%`, kept for existing routing imports). |
| `web_ui.layout_template` | `string\|null` | `null` | Per-bundle layout of the dashboard pages; `null` = `nowo_ui_kit.layouts.admin` (UiKit page contract, REQ-UI-003). The layout must render the UiKit canonical blocks; a 1.x layout that only defines `nowo_breadcrumb_kit_content` is wrapped by a deprecated bridge (see [UPGRADING](UPGRADING.md#template-contract)). |
| `web_ui.templates.<page>` | `array<string,string>` | `{}` | Per-page template override: `collections_index`, `collection_form`, `collection_delete`, `items_index`, `item_form`, `item_delete`, `import` (see [TEMPLATES](TEMPLATES.md)). Unknown pages fail at container build. |
| `web_ui.import_max_bytes` | `int` | `2097152` | Maximum JSON upload size for the dashboard import (default 2 MiB). |
| `web_ui.pagination.enabled` | `bool` | `true` | Paginate the collections and items lists. |
| `web_ui.pagination.per_page` | `int` | `20` | Rows per page (1–500). |
| `web_ui.dialog_sizes.collection_form` | `string` | `lg` | UiKit dialog size (`sm`, `md`, `lg`, `xl`) of the collection form dialog. |
| `web_ui.dialog_sizes.item_form` | `string` | `lg` | Dialog size of the item form dialog. |
| `web_ui.dialog_sizes.import` | `string` | `md` | Dialog size of the JSON import dialog. |
| `dashboard.*` | — | — | **Deprecated since 2.7** (removed in 3.0), see [Deprecated dashboard keys](#deprecated-dashboard-keys). |
| `security.access_roles` | `string[]` | `['ROLE_ADMIN']` | User must be granted **at least one** role for dashboard routes (REQ-UI-002). Empty list disables bundle-level role checks. |
| `security.access_checker` | `string\|null` | `null` | Optional service id implementing `BreadcrumbKitAccessCheckerInterface`. `null` = built-in role checker. |
| `security.allow_unauthenticated` | `bool` | `false` | **DEV/DEMO only.** Skip SecurityBundle requirement and dashboard access subscriber. Never `true` in production. |
| `inline_edit.query_param` | `string\|null` | `null` | When non-empty, a truthy value for this query key (`1`, `true`, `yes`, `on`) enables the optional inline editor UI in `breadcrumb_render()` (requires `web_ui.enabled`, a collection `inline_edit_access_key`, and a passing access checker). |
| `inline_edit.access_services` | `array<string,string>` | `[]` | Map of **logical keys** to **service ids** implementing `BreadcrumbInlineEditAccessCheckerInterface`. Each collection selects one key in the dashboard; the service receives the current `Request` and `?UserInterface` (or `null` if anonymous / no Security). |

To override dashboard or form UI strings from your application, see [USAGE.md](USAGE.md#overriding-translations-req-i18n-001) (domain `NowoBreadcrumbKitBundle`, files `translations/NowoBreadcrumbKitBundle.{locale}.yaml`).

## Inline edit (modal)

1. Set `inline_edit.query_param` (e.g. `breadcrumb_edit`).
2. Register one or more checker services and list them under `inline_edit.access_services` (`demo_key: app.my_checker`).
3. Enable the dashboard and import its routes (see below).
4. In the collection form, choose a checker key (or leave disabled). The default `breadcrumb.html.twig` renders a button + `<dialog>` + `<iframe>` pointing at the item edit URL for the matched route (or the “new item” URL if there is no row yet).

Override `templates/bundles/NowoBreadcrumbKitBundle/breadcrumb.html.twig` in your app if you need different markup; your template receives `breadcrumb_inline_edit` with `show`, `iframe_url`, and `modal_title`.

## Dashboard (CRUD)

1. Set `web_ui.enabled: true` and choose `web_ui.path_prefix` (e.g. `/admin/breadcrumbs`).
2. Import routing in your app (outside a `/{_locale}` group if you want a single non-localized admin URL):

```yaml
# config/routes.yaml
nowo_breadcrumb_kit_dashboard:
    resource: '@NowoBreadcrumbKitBundle/Resources/config/routing/dashboard.yaml'
    type: yaml
    prefix: '%nowo_breadcrumb_kit.web_ui.path_prefix%'
```

3. **Secure the dashboard (REQ-UI-002)** — production defaults require `symfony/security-bundle` and at least one of `security.access_roles` (default `ROLE_ADMIN`):

```yaml
# config/packages/nowo_breadcrumb_kit.yaml
nowo_breadcrumb_kit:
    web_ui:
        enabled: true
        path_prefix: /admin/breadcrumbs
        # layout_template: null          # null = nowo_ui_kit.layouts.admin
    security:
        access_roles: [ROLE_ADMIN]
        # access_checker: App\Security\BreadcrumbKitAccessChecker
        allow_unauthenticated: false

# config/packages/security.yaml (host firewall layer)
security:
    access_control:
        - { path: ^/admin/breadcrumbs, roles: ROLE_ADMIN }
```

   The demo sets `security.allow_unauthenticated: true` and empty `access_roles` so the CRUD UI works without login — **never copy that into production**.

4. Forms and delete actions need **CSRF**. With `symfony/security-bundle`, this is usually automatic. Without it, enable `framework.csrf_protection: true` (see Symfony docs).

Changes made through Doctrine (dashboard, import, your own entity code) invalidate the PSR-6 item-list cache of the affected collection automatically; changes written with raw SQL wait out `cache.ttl` (or clear the pool).

### Look and feel (UiKit page contract)

Since **2.7** the dashboard pages extend the [UiKitBundle](https://github.com/nowo-tech/UiKitBundle) **2.1** page scaffolds
(REQ-UI-003): the host themes them **once** for every Nowo bundle, with no BreadcrumbKit-specific chrome config.

```yaml
nowo_ui_kit:
    css_framework: bootstrap5          # bootstrap5 | bootstrap4 | tailwind | foundation | tabler | custom | none
    layouts:
        admin: 'layouts/admin.html.twig'   # your layout or a one-file bridge rendering the canonical blocks
    breadcrumbs:
        breadcrumb_kit: true            # page headers render this bundle's trail (breadcrumb_render())
    theme:
        classes: { btn_primary: 'btn btn-moss', table: 'data-table' }
        tokens: { primary: 'var(--color-moss)' }
        bundles:
            NowoBreadcrumbKitBundle: { classes: { table: 'data-table data-table--dense' } }   # optional exception
```

- Layout: `nowo_ui_kit.layouts.admin` (UiKit default `@NowoUiKit/layout/admin.html.twig`), or `web_ui.layout_template`
  for this bundle only. It loads `nowo-ui.css` and the UiKit scripts (`nowo-ui-confirm.js`, `nowo-ui-dialog.js`); the
  pages add `js/dashboard.js` (asset package `nowo_breadcrumb_kit`) through `nowo_ui_scripts`.
- Pages, data blocks and dialogs: [TEMPLATES.md](TEMPLATES.md). Slots: UiKit `docs/THEME_SLOTS.md`.
- The bundle no longer loads any CSS framework from a CDN.
- The **front trail** (`breadcrumb_render()`, `breadcrumb.html.twig`) is host chrome, not a dashboard page: it appends
  the host classes / attributes of the UiKit slots `breadcrumbs` (the `<nav>`), `breadcrumb_link` and
  `breadcrumb_separator` from `nowo_ui_kit.theme` (2.6 markup when none are set). See [USAGE](USAGE.md#theme-slots-of-the-trail).

### Form Kit (`nowo_form_kit`)

Dashboard CRUD forms use [FormKitBundle](https://github.com/nowo-tech/FormKitBundle) (`FormOptionsTrait` + profile `breadcrumb_kit`). When FormKit is present and the host has not defined `profiles.breadcrumb_kit`, BreadcrumbKit prepends that profile (`translation_domain: NowoBreadcrumbKitBundle`, `nowo-ui-input nowo-ui-input--full` + Bootstrap field classes). FormKit `css_framework` is seeded from the deprecated `dashboard.css_framework` when set (else `bootstrap`), mapped to FormKit’s allowed set (`bootstrap` | `tailwind` | `foundation` | `none`). Host `default_profile` is not changed.

### Deprecated dashboard keys

The `dashboard` node is deprecated since **2.7** (every key triggers a deprecation when set) and removed in **3.0**:

| Deprecated key | Replacement | Behaviour until 3.0 |
| --- | --- | --- |
| `dashboard.enabled`, `path_prefix`, `layout_template`, `import_max_bytes`, `pagination.*` | `web_ui.*` (same names) | aliases (the `web_ui` key wins); the 1.x default `@NowoBreadcrumbKitBundle/dashboard/layout.html.twig` reads as `null` |
| `dashboard.modals.*` | `web_ui.dialog_sizes.*` | `normal` → `md`, `lg`, `xl`; `delete` has no effect (UiKit confirm dialog) |
| `dashboard.css_framework`, `dashboard.icon_set` | `nowo_ui_kit.css_framework`, `nowo_ui_kit.icon_set` | seeded into UiKit when set here and not in `nowo_ui_kit` (no longer seeded by default: both default to `bootstrap5` / `bootstrap-icons`) |
| `dashboard.classes` | `nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle.classes` | bridged (prepended, the host's UiKit config wins), mapping in [UPGRADING](UPGRADING.md#deprecated-configuration) |
| `dashboard.partials` | `nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle.partials` | ignored (the UiKit partial inputs differ) |
| `dashboard.ui_kit_stylesheet` | — | read only by the 1.x layout bridge |
| `dashboard.collections_view`, `row_actions_position`, `cancel_first` | — | no effect |

Twig globals `nowo_breadcrumb_kit_layout_template`, `nowo_breadcrumb_kit_css_framework`, `nowo_breadcrumb_kit_icon_set`,
`nowo_breadcrumb_kit_classes`, `nowo_breadcrumb_kit_partials`, … are deprecated (unused by the pages, removed in 3.0).

### Twig helper

`breadcrumb_kit_dashboard_collections_url()` returns the collections index URL or `null` if `web_ui.enabled` is false or the route is not registered (e.g. routing import missing). Use it in templates instead of `path('nowo_breadcrumb_kit_dashboard_collections_index')` when the dashboard is optional.

## Example

```yaml
# config/packages/nowo_breadcrumb_kit.yaml
nowo_breadcrumb_kit:
    project: null
    doctrine:
        connection: default
        table_prefix: ''
    cache:
        ttl: 60
        pool: cache.app
    locales: ['en', 'es']
    default_locale: 'en'
    default_collection: 'default'
    presentation:
        home_icon: null
        home_icon_replaces_label: true
        hide_when_single_root: false
        collapse:
            enabled: false
            min_items: 3
            breakpoint: '40rem'
    web_ui:
        enabled: false
        path_prefix: /breadcrumb-kit-admin
        layout_template: null            # null = nowo_ui_kit.layouts.admin
        templates: {}                    # page => template (see TEMPLATES.md)
        import_max_bytes: 2097152
        pagination:
            enabled: true
            per_page: 20
        dialog_sizes:
            collection_form: lg
            item_form: lg
            import: md
    inline_edit:
        query_param: null
        access_services: []
```
