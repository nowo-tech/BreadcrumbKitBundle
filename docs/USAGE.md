# Usage

## Table of contents

- [Twig functions](#twig-functions)
- [Presentation (home icon, hide on home)](#presentation-home-icon-hide-on-home)
- [Console commands](#console-commands)
- [Trail enrichers (events)](#trail-enrichers-events)
- [Overriding templates and translations](#overriding-templates-and-translations)
  - [Twig templates (REQ-TWIG-001)](#twig-templates-req-twig-001)
  - [Overriding translations (REQ-I18N-001)](#overriding-translations-req-i18n-001)
- [Customising the admin chrome](#customising-the-admin-chrome)
- [Data model (summary)](#data-model-summary)
- [Caching](#caching)

## Twig functions

Render the default breadcrumb markup (uses the bundle template):

```twig
{{ breadcrumb_render() }}
{{ breadcrumb_render('admin') }}
{# Optional: custom template, then context key (same as loader): #}
{{ breadcrumb_render('default', '@NowoBreadcrumbKitBundle/breadcrumb.html.twig', '') }}
```

When `inline_edit` is configured, `breadcrumb_render()` passes `breadcrumb_inline_edit` (`show`, `iframe_url`, `modal_title`) into the template; override the bundle template if you need different UI.

Build a custom layout from resolved nodes:

```twig
{% for node in breadcrumb_trail('default', '') %}
    {% if node.url %}<a href="{{ node.url }}">{{ node.label }}</a>{% else %}{{ node.label }}{% endif %}
{% endfor %}
```

Optional dashboard link in your admin shell:

```twig
{% set url = breadcrumb_kit_dashboard_collections_url() %}
{% if url %}<a href="{{ url }}">Breadcrumb Kit</a>{% endif %}
```

## Presentation (home icon, hide on home)

Global defaults live under `nowo_breadcrumb_kit.presentation` (see [CONFIGURATION.md](CONFIGURATION.md)):

| Option | Effect |
|--------|--------|
| `home_icon` | Fallback icon/HTML for the first crumb when the collection has no `homeIcon`. |
| `home_icon_replaces_label` | When `true` (default), first crumb shows the icon only; `aria-label` keeps the text label. |
| `hide_when_single_root` | When `true`, no trail is rendered if the only matched crumb is the root and it is the current page. |

Per collection, set `homeIcon` in the dashboard and optionally `hide_when_single_root` inside **Responsive (JSON)**:

```json
{ "hide_when_single_root": true, "breakpoint": "md" }
```

Collection `homeIcon` wins over `presentation.home_icon`. Override `@NowoBreadcrumbKitBundle/_breadcrumb_crumb.html.twig` in your app for custom first-crumb markup.

## Console commands

| Command | Purpose |
|---------|---------|
| `nowo_breadcrumb_kit:generate-migration` | Write a Doctrine migration for breadcrumb tables (`--update` for additive columns such as `path_pattern` / `match_attributes`). |
| `nowo_breadcrumb_kit:export` | Export collections to JSON (`--file`, optional `--code` / `--context-key`). |
| `nowo_breadcrumb_kit:import` | Import JSON (`--file`, `--strategy=skip_existing\|replace`). |
| `nowo_breadcrumb_kit:preview` | Resolve a trail for a synthetic request (`--collection`, `--path`, `--route`, `--route-params`, `--attributes`, `--locale`). |

```bash
php bin/console nowo_breadcrumb_kit:generate-migration --update
php bin/console nowo_breadcrumb_kit:preview --path=/shop/item --route='*' --attributes='{}'
```

## Trail enrichers (events)

After `BreadcrumbLoader` builds a view, it dispatches `Nowo\BreadcrumbKitBundle\Event\BreadcrumbTrailBuiltEvent`. Subscribers may call `setView()` to replace labels, URLs, or inject nodes.

```php
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbTrailBuiltEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EnrichBreadcrumbsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [BreadcrumbTrailBuiltEvent::class => 'onTrail'];
    }

    public function onTrail(BreadcrumbTrailBuiltEvent $event): void
    {
        // $event->getView() / $event->setView(...)
    }
}
```

## Overriding templates and translations

### Twig templates (REQ-TWIG-001)

**Registration:** `TwigPathsPass` calls **`prependPath()`** for **`templates/bundles/NowoBreadcrumbKitBundle/`** when that directory exists, then **`addPath()`** for the bundle’s **`src/Resources/views`**, so application files always win over vendor for `@NowoBreadcrumbKitBundle/...`. The native loader alias **`twig.loader.native`** is resolved (including chained aliases) before attaching method calls.

**Procedure**

1. In your application, create a file under **`templates/bundles/NowoBreadcrumbKitBundle/`** with the same **`<subpath>`** as in the bundle (see table below).
2. Copy or adapt markup from the bundle’s `src/Resources/views/<subpath>` as needed — or, since 2.4.0, `{% extends '@!NowoBreadcrumbKitBundle/<subpath>' %}` and redefine only the blocks you need (see [Customising the admin chrome](#customising-the-admin-chrome)).
3. Clear the Symfony cache if templates are cached (`php bin/console cache:clear`).

**Templates you can override**

| Subpath | Purpose |
|---------|---------|
| `breadcrumb.html.twig` | Default breadcrumb markup (and optional inline-edit UI). |
| `_breadcrumb_crumb.html.twig` | Single crumb (icon, link, current page). |
| `dashboard/layout.html.twig` | Dashboard shell (Bootstrap navbar, main block). |
| `dashboard/base.html.twig` | Extends configurable layout; flashes + body block; loads UiKit CSS + `dashboard.js`. |
| `dashboard/collection/index.html.twig` | Collections list. |
| `dashboard/collection/form.html.twig` | Collection create/edit. |
| `dashboard/item/index.html.twig` | Items list for a collection. |
| `dashboard/item/form.html.twig` | Item create/edit. |
| `dashboard/import.html.twig` | Import page. |
| `dashboard/_collection_form_partial.html.twig` | Collection form partial (modals). |
| `dashboard/_item_form_partial.html.twig` | Item form partial. |
| `dashboard/_import_partial.html.twig` | Import partial. |
| `dashboard/_dashboard_modals.html.twig` | Shared dashboard modals. |
| `dashboard/_modal_shell.html.twig` | Modal shell rendered by every modal (prefer `dashboard.partials.modal_shell`). |
| `dashboard/_pagination.html.twig` | List pagination (prefer `dashboard.partials.pagination`). |
| `dashboard/_icons.html.twig` | Inline SVG action icons. |
| `Collector/breadcrumb.html.twig` | Web Profiler / toolbar panel. |
| `Collector/_icon.svg.twig` | Profiler icon fragment. |

**Dashboard CSS / macros (REQ-UI-001-kit):** import `@NowoUiKitBundle/macros/ui.html.twig` and load `asset('css/nowo-ui.css', 'nowo_ui_kit')`. Domain CRUD stays in this bundle; look-and-feel comes from [UiKitBundle](https://github.com/nowo-tech/UiKitBundle). See [CONFIGURATION.md](CONFIGURATION.md) for `dashboard.css_framework` / `nowo_ui_kit` alignment.

**Dashboard forms (FormKit):** CRUD types use [FormKitBundle](https://github.com/nowo-tech/FormKitBundle) (`FormOptionsTrait`, profile `breadcrumb_kit`). Dashboard search/delete actions also render through Symfony Form Types (`DashboardGetSearchType`, `DashboardPostDeleteType`) via Twig `form_start` / `form_end`, not hand-rolled `<form>` tags. Register `NowoFormKitBundle`; optional host `nowo_form_kit` YAML — see [CONFIGURATION.md](CONFIGURATION.md).

**Dashboard layout integration:** set `nowo_breadcrumb_kit.dashboard.layout_template` to your app layout if it defines block `nowo_breadcrumb_kit_content` (same pattern as [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle) `nowo_dashboard_menu_content`).

### Overriding translations (REQ-I18N-001)

The bundle uses the translation domain **`NowoBreadcrumbKitBundle`** for dashboard UI, forms, and validation messages. Bundle files live at `src/Resources/translations/NowoBreadcrumbKitBundle.{locale}.yaml` (bundled locales: **en**, **es**, **de**, **fr**, **it**, **nl**, **pt**).

Keys are grouped under **`dashboard`** (panel labels, buttons, pagination), **`form`** (field labels and help), and **`flash`** (success/error messages). Dashboard templates use `{% trans_default_domain 'NowoBreadcrumbKitBundle' %}` or explicit `|trans({}, 'NowoBreadcrumbKitBundle')`.

**Override bundle strings** — create or edit in your project:

`translations/NowoBreadcrumbKitBundle.{locale}.yaml`

Example (`translations/NowoBreadcrumbKitBundle.es.yaml`):

```yaml
dashboard:
  title: 'Migas de pan'
  new_collection: 'Nueva colección'
  search_placeholder: 'Buscar por código o nombre…'

form:
  breadcrumb_collection_type:
    code:
      label: 'Código de colección'
```

You only need the keys you want to change; missing keys fall back to the bundle defaults. After editing translations, clear the cache: `php bin/console cache:clear`.

**Runtime breadcrumb labels** on the public site come from **JSON translations on `BreadcrumbItem` entities** (configured via the dashboard or fixtures), not from these YAML files. YAML overrides affect the admin dashboard and form messages only.

## Customising the admin chrome

Since **2.4.0** (spec [`003-admin-chrome-hooks`](../specs/003-admin-chrome-hooks/spec.md)) a host can give the dashboard its own look **without copying pages**. Everything below is opt-in: with no option set, the markup is the 2.3 markup (snapshot-tested). Behaviour hooks stay in the bundle: modal ids (`modal-bk-*`, `*-body`), `btn-bk-*` classes, `data-bk-*` attributes, CSRF ids and routes.

Pick the lightest tool that does the job:

1. **Class map** (`dashboard.classes`) — swap classes on buttons, row actions, toolbar, search, list/table, empty state, badges, page and modal forms.
2. **Options** — `collections_view: table`, `row_actions_position: end`, `cancel_first: true`, `ui_kit_stylesheet: false`.
3. **Partial paths** (`dashboard.partials`) — your own modal shell, pagination or row-action inner markup.
4. **Layout slots** — your `layout_template` pulls the page title / intro / actions into its own header.
5. **Block overrides** — an app override extends `@!NowoBreadcrumbKitBundle/...` and redefines one block.

### Class map keys

| Group | Keys (default when unset) |
|-------|---------------------------|
| Buttons | `btn_primary`, `btn_secondary`, `btn_danger`, `btn_link` (`ui.btn(variant)`; full-page forms: `btn btn-*` on Bootstrap) |
| Row actions | `action_view`, `action_edit`, `action_delete` (`ui.action(kind)`), `row_actions` (`ui.row_actions()`) |
| Page | `toolbar`, `page_title`, `page_intro`, `breadcrumb_nav`, `breadcrumb_link`, `breadcrumb_separator`, `search_form`, `search_input`, `search_summary`, `empty`, `badge`, `badge_yes`, `badge_no` (link column: plain text by default), `muted` |
| Tables / lists | `results` (wraps results and pagination in a `<section>` with `data-testid` `breadcrumb-kit-collections-table` / `breadcrumb-kit-items-table`; none by default), `list`, `list_item`, `table_wrap`, `table`, `thead_row`, `th`, `th_actions`, `row`, `td`, `td_actions` (no class attribute by default) |
| Full-page forms | `form` (appended to `bk-dashboard-form`), `form_panel` (wraps the form in a `<section>` with `data-testid` `breadcrumb-kit-collection-form` / `breadcrumb-kit-item-form`), `form_actions` |
| Modals | `modal_loading`, `modal_message`, `modal_hint`, `modal_form` (appended), `modal_form_body` (wraps the fields — and the import intro — inside the form), `modal_actions`, `form_style_grid` (groups the four CSS-class fields of the collection form) |

### Partials

| Key | Default | Receives |
|-----|---------|----------|
| `modals` | `dashboard/_dashboard_modals.html.twig` | page context (`delete_form`, `modal_classes`) |
| `modal_shell` | `dashboard/_modal_shell.html.twig` | `modal_id`, `modal_title`, `modal_size` (`collection_form`\|`item_form`\|`import`\|`delete`), `modal_size_class`, `modal_body_id` (null for delete), `modal_scrollable`; blocks `bk_modal_body` (default: loading placeholder) and `bk_modal_footer` |
| `pagination` | `dashboard/_pagination.html.twig` | `pagination` (`current_page`, `per_page`, `total`, `total_pages`), `item_count`, `route`, `route_params`, `query`, `nav_label`, `nav_trans_domain` |
| `action_inner` | bundle SVG icons | `display` (`nowo_ui_kit_row_actions_display`), `icon_name` (`view`, `export`, `edit`, `delete`, `config`, `filter`), `label` |

### Layout slots and contract

Pages define **slot blocks** that the stock layout ignores: `bk_page_title`, `bk_page_intro`, `bk_page_actions` (index pages) and `bk_back_link` (items, import). A host layout renders them with `block()` (guard with `is defined`, form pages have no actions) and sets, at its top level:

| Variable | Effect |
|----------|--------|
| `bk_page_actions_in_layout` | Index pages skip their inline toolbar (the layout shows `bk_page_actions`); the items page shows `bk_page_intro` instead of its heading row. |
| `bk_page_title_in_layout` | Form and import pages skip their `<h1>`. |
| `bk_title_suffix` | Appended to every page `title` block (e.g. `' — ' ~ brand`). |

### Blocks

| Template | Blocks |
|----------|--------|
| `collection/index` | `bk_page_header`, `bk_search`, `bk_toolbar`, `bk_results`, `bk_empty_state`, `bk_collections_list`, `bk_collection_row` (list), `bk_collection_table_row` (table), `bk_collection_row_actions`, `bk_pagination` |
| `item/index` | `bk_page_header`, `bk_toolbar`, `bk_page_heading`, `bk_search`, `bk_search_summary`, `bk_results`, `bk_empty_state`, `bk_items_table`, `bk_items_table_head`, `bk_item_row`, `bk_item_link_cell`, `bk_item_row_actions`, `bk_pagination` |
| `collection/form`, `item/form` | `bk_breadcrumb`, `bk_page_heading`, `bk_form`, `bk_form_fields`, `bk_form_actions` |
| `import` | `bk_page_heading`, `bk_back_link` |
| `_collection_form_partial`, `_item_form_partial`, `_import_partial` | `bk_form_fields`, `bk_form_actions`, `bk_form_submit`, `bk_form_cancel` (+ `bk_import_intro`) |
| `_dashboard_modals` | `bk_modal_collection_form`, `bk_modal_item_form`, `bk_modal_import`, `bk_modal_delete` |

### Example (Tailwind admin, no page copies)

```yaml
# config/packages/nowo_breadcrumb_kit.yaml
nowo_breadcrumb_kit:
    dashboard:
        layout_template: 'admin/breadcrumb_layout.html.twig'
        css_framework: tailwind
        ui_kit_stylesheet: false          # the admin layout loads nowo-ui.css first
        collections_view: table
        row_actions_position: end
        cancel_first: true
        classes:
            btn_primary: 'btn-primary'
            btn_secondary: 'btn-ghost'
            btn_danger: 'btn-danger'
            action_view: 'btn-ghost btn-icon'
            action_edit: 'btn-ghost btn-icon'
            action_delete: 'btn-danger btn-icon'
            row_actions: 'inline-flex gap-1 nowo-ui-row-actions nowo-ui-row-actions--icon'
            search_form: 'admin-toolbar mb-4'
            search_input: 'input flex-1'
            table_wrap: 'panel overflow-x-auto'
            table: 'data-table w-full text-sm'
            th: 'px-2 py-2 font-medium'
            td: 'px-2 py-3'
            th_actions: 'px-2 py-2 text-end'
            td_actions: 'px-2 py-3 text-end whitespace-nowrap'
            badge_yes: 'badge badge--ok'
            badge_no: 'badge'
            modal_form: 'modal-panel'
            modal_form_body: 'modal-content'
            modal_actions: 'modal-actions'
        partials:
            modal_shell: 'admin/_breadcrumb_modal_shell.html.twig'
            pagination: 'admin/_pagination.html.twig'
            action_inner: '@NowoUiKitBundle/partials/_action_inner.html.twig'
```

```twig
{# templates/admin/breadcrumb_layout.html.twig #}
{% extends 'admin/base.html.twig' %}
{% set bk_page_actions_in_layout = true %}
{% set bk_page_title_in_layout = true %}
{% set bk_title_suffix = ' — ' ~ brand_name %}
{% set admin_heading %}{{ block('bk_page_title') }}{% endset %}
{% set admin_actions %}
    {% if block('bk_back_link') is defined %}{{ block('bk_back_link') }}{% endif %}
    {% if block('bk_page_actions') is defined %}{{ block('bk_page_actions') }}{% endif %}
{% endset %}
{% block content %}{% block nowo_breadcrumb_kit_content %}{% endblock %}{% endblock %}
```

```twig
{# templates/admin/_breadcrumb_modal_shell.html.twig — keep modal_id, "{modal_id}-label" and modal_body_id #}
{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}
<dialog class="nowo-ui-modal admin-dialog{{ {collection_form: ' admin-dialog--xl', item_form: ' admin-dialog--wide'}[modal_size] ?? '' }}" id="{{ modal_id }}" aria-labelledby="{{ modal_id }}-label" aria-hidden="true">
    <header><h2 id="{{ modal_id }}-label">{{ modal_title }}</h2><button type="button" class="btn-close" {{ ui.modal_dismiss_attrs('tailwind')|raw }} aria-label="{{ 'dashboard.close'|trans({}, 'NowoBreadcrumbKitBundle') }}"></button></header>
    {% if modal_body_id %}
        <div id="{{ modal_body_id }}"><div class="modal-content">{% block bk_modal_body %}<p>{{ 'dashboard.loading'|trans({}, 'NowoBreadcrumbKitBundle') }}</p>{% endblock %}</div></div>
    {% else %}
        <div class="modal-content">{{ block('bk_modal_body') }}</div>
    {% endif %}
    {% block bk_modal_footer %}{% endblock %}
</dialog>
```

Still need one structural change? Override a single block and keep the rest of the page:

```twig
{# templates/bundles/NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig #}
{% extends '@!NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig' %}
{% block bk_empty_state %}{% include 'admin/_empty.html.twig' with {message: 'dashboard.empty_collections'|trans({}, 'NowoBreadcrumbKitBundle')} %}{% endblock %}
```

## Data model (summary)

Breadcrumb definitions are stored with Doctrine (`BreadcrumbCollection`, `BreadcrumbItem`). Items reference a Symfony route name (or `*` when constrained by path/attributes), optional static route parameters, optional `pathPattern` (PCRE against the request path) and `matchAttributes` (JSON map of request attributes), optional `parent` for trail order (root → leaf), JSON translations per locale, and optional URL generation via `dynamicParamKeys`. See the bundle `README.md` for the conceptual model.

## Caching

When `cache.pool` points to a valid service and the pool exists in the container, `BreadcrumbLoader` caches serialized item lists per collection/context to reduce database reads. Set `pool` to `''` to disable that cache.
