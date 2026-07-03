# Usage

## Table of contents

- [Twig functions](#twig-functions)
- [Overriding templates and translations](#overriding-templates-and-translations)
  - [Twig templates (REQ-TWIG-001)](#twig-templates-req-twig-001)
  - [Overriding translations (REQ-I18N-001)](#overriding-translations-req-i18n-001)
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

## Overriding templates and translations

### Twig templates (REQ-TWIG-001)

**Registration:** `TwigPathsPass` calls **`prependPath()`** for **`templates/bundles/NowoBreadcrumbKitBundle/`** when that directory exists, then **`addPath()`** for the bundle’s **`src/Resources/views`**, so application files always win over vendor for `@NowoBreadcrumbKitBundle/...`. The native loader alias **`twig.loader.native`** is resolved (including chained aliases) before attaching method calls.

**Procedure**

1. In your application, create a file under **`templates/bundles/NowoBreadcrumbKitBundle/`** with the same **`<subpath>`** as in the bundle (see table below).
2. Copy or adapt markup from the bundle’s `src/Resources/views/<subpath>` as needed.
3. Clear the Symfony cache if templates are cached (`php bin/console cache:clear`).

**Templates you can override**

| Subpath | Purpose |
|---------|---------|
| `breadcrumb.html.twig` | Default breadcrumb markup (and optional inline-edit UI). |
| `dashboard/layout.html.twig` | Dashboard shell (Bootstrap navbar, main block). |
| `dashboard/base.html.twig` | Extends configurable layout; flashes + body block. |
| `dashboard/collection/index.html.twig` | Collections list. |
| `dashboard/collection/form.html.twig` | Collection create/edit. |
| `dashboard/item/index.html.twig` | Items list for a collection. |
| `dashboard/item/form.html.twig` | Item create/edit. |
| `dashboard/import.html.twig` | Import page. |
| `dashboard/_collection_form_partial.html.twig` | Collection form partial (modals). |
| `dashboard/_item_form_partial.html.twig` | Item form partial. |
| `dashboard/_import_partial.html.twig` | Import partial. |
| `dashboard/_dashboard_modals.html.twig` | Shared dashboard modals. |
| `dashboard/_icons.html.twig` | Inline SVG action icons. |
| `Collector/breadcrumb.html.twig` | Web Profiler / toolbar panel. |
| `Collector/_icon.svg.twig` | Profiler icon fragment. |

**Dashboard layout integration:** set `nowo_breadcrumb_kit.dashboard.layout_template` to your app layout if it defines block `nowo_breadcrumb_kit_content` (same pattern as [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle) `nowo_dashboard_menu_content`).

### Overriding translations (REQ-I18N-001)

The bundle uses the translation domain **`NowoBreadcrumbKitBundle`** for dashboard UI, forms, and validation messages. Bundle files live at `src/Resources/translations/NowoBreadcrumbKitBundle.{locale}.yaml` (e.g. `NowoBreadcrumbKitBundle.en.yaml`, `NowoBreadcrumbKitBundle.es.yaml`).

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

## Data model (summary)

Breadcrumb definitions are stored with Doctrine (`BreadcrumbCollection`, `BreadcrumbItem`). Items reference a Symfony route name, optional static route parameters (for matching the current request), optional `parent` for trail order (root → leaf), JSON translations per locale, and optional URL generation via `dynamicParamKeys`. See the bundle `README.md` for the conceptual model.

## Caching

When `cache.pool` points to a valid service and the pool exists in the container, `BreadcrumbLoader` caches serialized item lists per collection/context to reduce database reads. Set `pool` to `''` to disable that cache.
