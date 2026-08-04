# Configuration

Configuration root key: `nowo_breadcrumb_kit` (see `Nowo\BreadcrumbKitBundle\DependencyInjection\Configuration`).

## Table of contents

- [Reference](#reference)
- [Inline edit (modal)](#inline-edit-modal)
- [Dashboard (CRUD)](#dashboard-crud)
  - [CSS framework (REQ-UI-001)](#css-framework-req-ui-001)
  - [Twig helper](#twig-helper)
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
| `dashboard.css_framework` | `string` | `bootstrap5` | CSS stack for dashboard markup (`bootstrap`/`bootstrap5`, `bootstrap4`, `tailwind`, `foundation`, `custom`, `tabler`, `none`). Exposed as Twig global `nowo_breadcrumb_kit_css_framework`. |
| `dashboard.icon_set` | `string` | `bootstrap-icons` | Icon set for dashboard actions (`bootstrap-icons`, `tabler-icons`, `ux_icon`, `svg_inline`, `none`). Exposed as Twig global `nowo_breadcrumb_kit_icon_set`. |
| `dashboard.import_max_bytes` | `int` | `2097152` | Maximum JSON upload size for dashboard import (default 2 MiB). |
| `dashboard.pagination.enabled` | `bool` | `true` | When `true`, collections and items lists in the dashboard are paginated. |
| `dashboard.pagination.per_page` | `int` | `20` | Number of rows per page for collections and items (1–500). |
| `dashboard.modals.collection_form` | `string` | `lg` | Bootstrap modal size for collection create/edit: `normal`, `lg`, or `xl`. |
| `dashboard.modals.item_form` | `string` | `lg` | Modal size for item create/edit. |
| `dashboard.modals.import` | `string` | `normal` | Modal size for JSON import. |
| `dashboard.modals.delete` | `string` | `normal` | Modal size for delete confirmation. |
| `security.access_roles` | `string[]` | `['ROLE_ADMIN']` | User must be granted **at least one** role for dashboard routes (REQ-UI-002). Empty list disables bundle-level role checks. |
| `security.access_checker` | `string\|null` | `null` | Optional service id implementing `BreadcrumbKitAccessCheckerInterface`. `null` = built-in role checker. |
| `security.allow_unauthenticated` | `bool` | `false` | **DEV/DEMO only.** Skip SecurityBundle requirement and dashboard access subscriber. Never `true` in production. |
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

3. **Secure the dashboard (REQ-UI-002)** — production defaults require `symfony/security-bundle` and at least one of `security.access_roles` (default `ROLE_ADMIN`):

```yaml
# config/packages/nowo_breadcrumb_kit.yaml
nowo_breadcrumb_kit:
    dashboard:
        enabled: true
        path_prefix: /admin/breadcrumbs
        layout_template: 'base.html.twig'   # host layout
        css_framework: bootstrap5
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

After changes from the UI, clear or wait out the PSR-6 item-list cache if enabled (`cache.pool`).

The dashboard UI aligns visually with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle): list/table views, fetch-loaded modals (`?_partial=1`), search, optional pagination, export/import JSON. Twig globals: `nowo_breadcrumb_kit_layout_template`, `nowo_breadcrumb_kit_css_framework`, `nowo_breadcrumb_kit_icon_set`.

### CSS framework (REQ-UI-001)

Set `dashboard.css_framework` to switch look-and-feel **without forking page templates**. Markup uses [UiKitBundle](https://github.com/nowo-tech/UiKitBundle) macros (`@NowoUiKitBundle/macros/ui.html.twig`) and semantic `nowo-ui-*` classes from UiKit’s `nowo-ui.css`:

| Value | Markup / CDN (demo layout) |
| ----- | -------------------------- |
| `bootstrap5` / `bootstrap` / `tabler` | Bootstrap 5 classes + CDN (default) |
| `bootstrap4` | Bootstrap 4 classes + CDN |
| `tailwind` | Tailwind utilities + CDN script |
| `foundation` | Foundation classes + CDN |
| `custom` / `none` | Semantic `nowo-ui-*` only (host CSS / UiKit `nowo-ui.css`) |

When `layout_template` points at the **project layout**, the demo CDN is skipped; the host layout must expose `stylesheets` / `javascripts` blocks. Bundle pages call `{{ parent() }}` then add assets (REQ-ASSETS-004):

```twig
<link href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}" rel="stylesheet">
<script src="{{ asset('js/dashboard.js', 'nowo_breadcrumb_kit') }}" defer></script>
```

Prefer a single host setting for the shared kit:

```yaml
nowo_ui_kit:
    css_framework: custom
    icon_set: ux_icon
```

If `nowo_ui_kit` keys are unset, BreadcrumbKit prepends `css_framework` / `icon_set` from `dashboard.*` (defaults `bootstrap5` / `bootstrap-icons`). Explicit host `nowo_ui_kit` values win and are never overridden, so `ui.btn('primary')` resolves correctly without a framework argument.

UiKit `nowo-ui.css` uses `--nowo-ui-*` custom properties (slate/blue defaults). Remap under host chrome (e.g. `.kit-admin`) without forking templates:

```css
.kit-admin {
  --nowo-ui-primary: var(--brand-primary);
  --nowo-ui-text: var(--brand-ink);
  --nowo-ui-border: var(--brand-border);
  --nowo-ui-surface: var(--brand-surface);
}
```

Override nested blocks `nowo_ui_styles` / `nowo_ui_scripts` if needed.

Stable content block: `nowo_breadcrumb_kit_content` (also aliased as `nowo_ui_content` in the demo layout). Overridable Twig paths: `dashboard/*.html.twig`, `dashboard/collection/*`, `dashboard/item/*`, `breadcrumb.html.twig` (see [USAGE.md](USAGE.md) / REQ-TWIG-001).

### Using `css_framework: custom` (or `none`) without Bootstrap

Set `dashboard.css_framework: custom` (or `none`) when your host layout does **not** load Bootstrap.
The dashboard will use only the semantic `nowo-ui-*` classes defined in UiKit’s `nowo-ui.css`.

**What the stack provides automatically:**

- UiKit `nowo-ui.css` includes a self-contained modal overlay (`.nowo-ui-modal` + `.nowo-ui-modal-open`) so modals render correctly without Bootstrap's `.modal` CSS.
- `dashboard.js` detects `window.__breadcrumbKitDashboard.cssFramework` at runtime:
  - For **bootstrap / bootstrap5 / bootstrap4 / tabler** → defers to Bootstrap JS for modal management.
  - For any other value → registers lightweight custom open/close handlers for `[data-nowo-modal-open]` / `[data-nowo-modal-close]` attributes, dispatches a synthetic `show.bs.modal` event so the existing listeners (form-load, confirm-delete, etc.) keep working.
- Loading and error states inside modals use `nowo-ui-muted` / `nowo-ui-flash nowo-ui-flash--error` classes (no Bootstrap dependency).

**Minimal host configuration:**

```yaml
# config/packages/nowo_breadcrumb_kit.yaml
nowo_breadcrumb_kit:
    dashboard:
        enabled: true
        layout_template: 'base.html.twig'   # host layout (must define nowo_breadcrumb_kit_content block)
        css_framework: custom

# optional — or let BreadcrumbKit prepend from dashboard.* above
nowo_ui_kit:
    css_framework: custom
```

```twig
{# templates/base.html.twig — expose the required blocks #}
{% block stylesheets %}
    <link href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}" rel="stylesheet">
    {# host stylesheet — remap --nowo-ui-* tokens here #}
{% endblock %}
{% block javascripts %}
    <script src="{{ asset('js/dashboard.js', 'nowo_breadcrumb_kit') }}" defer></script>
{% endblock %}
```

**Theming without Bootstrap** — remap `--nowo-ui-*` custom properties under your layout wrapper:

```css
.my-admin-layout {
  --nowo-ui-primary: var(--brand-primary);
  --nowo-ui-surface: var(--color-surface);
  --nowo-ui-border: var(--color-border);
  --nowo-ui-text: var(--color-ink);
}
```

> UiKit macros automatically emit `data-nowo-modal-open` / `data-nowo-modal-target` / `data-nowo-modal-close` attributes instead of `data-bs-toggle` / `data-bs-target` / `data-bs-dismiss` when the framework is not bootstrap/tabler. No template overrides are required.

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
        css_framework: bootstrap5
        icon_set: bootstrap-icons
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
