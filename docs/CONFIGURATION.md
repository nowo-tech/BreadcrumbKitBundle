# Configuration

Configuration root key: `nowo_breadcrumb_kit` (see `Nowo\BreadcrumbKitBundle\DependencyInjection\Configuration`).

## Table of contents

- [Reference](#reference)
- [Inline edit (modal)](#inline-edit-modal)
- [Dashboard (CRUD)](#dashboard-crud)
- [Example](#example)

## Reference

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `project` | `string\|null` | `null` | Optional identifier when several apps share one database. |
| `doctrine.connection` | `string` | `default` | Doctrine connection name for entity metadata. |
| `doctrine.table_prefix` | `string` | `''` | Prefix for table names (`dashboard_breadcrumb_collection`, `dashboard_breadcrumb_item`). |
| `cache.ttl` | `int` | `60` | TTL in seconds for the PSR-6 cache of serialized breadcrumb items (0 disables TTL semantics per pool). |
| `cache.pool` | `string` | `cache.app` | PSR-6 cache pool service id. Empty string keeps the loader without a pool (no item-list cache). |
| `locales` | `string[]` | `[]` | Supported locales for resolving labels from JSON translations on items. |
| `default_locale` | `string\|null` | `null` | Fallback locale; if null and `locales` is non-empty, the first locale is used. |
| `default_collection` | `string` | `default` | Collection code used when Twig helpers omit an explicit collection. |
| `presentation.home_icon` | `string\|null` | `null` | Fallback home/root icon when the collection `homeIcon` is empty (HTML, emoji, or an app-specific token such as `tabler:home` with Symfony UX Icons). |
| `presentation.home_icon_replaces_label` | `bool` | `true` | When `true` and a home icon is set, the first crumb shows the icon instead of its text label (`aria-label` keeps the label for accessibility). |
| `presentation.hide_when_single_root` | `bool` | `false` | When `true`, hides the trail on pages where the only crumb is the root item and it is the current page (typical home). Per-collection override: `responsiveConfig.hide_when_single_root` in the dashboard. |
| `dashboard.enabled` | `bool` | `false` | When `true`, registers CRUD controllers (requires `symfony/form` + `symfony/framework-bundle`; import routing as below). |
| `dashboard.path_prefix` | `string` | `/breadcrumb-kit-admin` | URL prefix for dashboard routes (leading slash, no trailing slash). Must match the `prefix` used when importing bundle routes. |
| `dashboard.layout_template` | `string` | `@NowoBreadcrumbKitBundle/dashboard/layout.html.twig` | Twig layout extended by dashboard pages (override in the app like DashboardMenuBundle). Must define block `nowo_breadcrumb_kit_content`. |
| `dashboard.import_max_bytes` | `int` | `2097152` | Maximum JSON upload size for dashboard import (default 2 MiB). |
| `dashboard.pagination.enabled` | `bool` | `true` | When `true`, the collections list in the dashboard is paginated. |
| `dashboard.pagination.per_page` | `int` | `20` | Number of collections per page (1–500). |
| `dashboard.modals.collection_form` | `string` | `lg` | Bootstrap modal size for collection create/edit: `normal`, `lg`, or `xl`. |
| `dashboard.modals.item_form` | `string` | `lg` | Modal size for item create/edit. |
| `dashboard.modals.import` | `string` | `normal` | Modal size for JSON import. |
| `dashboard.modals.delete` | `string` | `normal` | Modal size for delete confirmation. |
| `inline_edit.query_param` | `string\|null` | `null` | When non-empty, a truthy value for this query key (`1`, `true`, `yes`, `on`) enables the optional inline editor UI in `breadcrumb_render()` (requires `dashboard.enabled`, a collection `inline_edit_access_key`, and a passing access checker). |
| `inline_edit.access_services` | `array<string,string>` | `[]` | Map of **logical keys** to **service ids** implementing `BreadcrumbInlineEditAccessCheckerInterface`. Each collection selects one key in the dashboard; the service receives the current `Request` and `?UserInterface` (or `null` if anonymous / no Security). |

To override dashboard or form UI strings from your application, see [USAGE.md](USAGE.md#overriding-translations-req-i18n-001) (domain `NowoBreadcrumbKitBundle`, files `translations/NowoBreadcrumbKitBundle.{locale}.yaml`).

## Inline edit (modal)

1. Set `inline_edit.query_param` (e.g. `breadcrumb_edit`).
2. Register one or more checker services and list them under `inline_edit.access_services` (`demo_key: app.my_checker`).
3. Enable the dashboard and import its routes (see below).
4. In the collection form, choose a checker key (or leave disabled). The default `breadcrumb.html.twig` renders a button + `<dialog>` + `<iframe>` pointing at the item edit URL for the matched route (or the “new item” URL if there is no row yet).

Override `templates/bundles/NowoBreadcrumbKitBundle/breadcrumb.html.twig` in your app if you need different markup; your template receives `breadcrumb_inline_edit` with `show`, `iframe_url`, and `modal_title`.

## Dashboard (CRUD)

1. Set `dashboard.enabled: true` and choose `dashboard.path_prefix` (e.g. `/admin/breadcrumbs`).
2. Import routing in your app (outside a `/{_locale}` group if you want a single non-localized admin URL):

```yaml
# config/routes.yaml
nowo_breadcrumb_kit_dashboard:
    resource: '@NowoBreadcrumbKitBundle/Resources/config/routing/dashboard.yaml'
    type: yaml
    prefix: '%nowo_breadcrumb_kit.dashboard.path_prefix%'
```

3. Protect the prefix in production (`access_control`, firewall, or IP allowlist). The bundle does not enforce authentication by default.
4. Forms and delete actions need **CSRF**. With `symfony/security-bundle`, this is usually automatic. Without it, enable `framework.csrf_protection: true` (see Symfony docs).

After changes from the UI, clear or wait out the PSR-6 item-list cache if enabled (`cache.pool`).

The dashboard UI aligns visually with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle): Bootstrap 5 list/table views, fetch-loaded modals (`?_partial=1`), search on collections and items, optional pagination on the collections index, and export/import JSON.

### Twig helper

`breadcrumb_kit_dashboard_collections_url()` returns the collections index URL or `null` if `dashboard.enabled` is false or the route is not registered (e.g. routing import missing). Use it in templates instead of `path('nowo_breadcrumb_kit_dashboard_collections_index')` when the dashboard is optional.

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
    dashboard:
        enabled: false
        path_prefix: /breadcrumb-kit-admin
        layout_template: '@NowoBreadcrumbKitBundle/dashboard/layout.html.twig'
        import_max_bytes: 2097152
        pagination:
            enabled: true
            per_page: 20
        modals:
            collection_form: lg
            item_form: lg
            import: normal
            delete: normal
    inline_edit:
        query_param: null
        access_services: []
```
