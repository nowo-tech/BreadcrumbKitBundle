# Feature Specification: BreadcrumbKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Status**: Active  
**Last updated**: 2026-08-04 (v2.0.14)

**Package**: `nowo-tech/breadcrumb-kit-bundle`  
**Configuration root**: `nowo_breadcrumb_kit`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Database-driven **breadcrumb trails** for Symfony: route matching, i18n labels, optional links, collection-level presentation, Twig helpers, optional **admin dashboard** (CRUD + import/export), **inline edit** hooks, and Web Profiler panel.

Dashboard look-and-feel composes **UiKitBundle**; dashboard Symfony forms compose **FormKitBundle** (profile `breadcrumb_kit`). Trail resolution does not depend on host UI chrome beyond Twig.

---

## User Scenarios

### US-01 — Route-matched trail (P1)

**Given** items stored for a collection, **When** current route matches an item's route criteria, **Then** `BreadcrumbLoader` builds a `BreadcrumbTrailView` with ordered `BreadcrumbNode` entries.

### US-02 — Twig rendering (P1)

**Given** a collection code, **When** `breadcrumb()` Twig function runs, **Then** `BreadcrumbExtension` resolves URLs via `BreadcrumbUrlResolver` and renders `breadcrumb.html.twig`.

### US-03 — Dashboard CRUD (P2)

**Given** `dashboard.enabled`, **When** admin manages collections/items, **Then** dashboard controllers expose index/form routes with modal partials, UiKit macros/CSS, FormKit form types, and `dashboard.js` UX.

### US-04 — Import/export (P2)

**Given** YAML/JSON export from `BreadcrumbExporter`, **When** uploaded via `ImportExportController`, **Then** `BreadcrumbImporter` upserts collections and items.

### US-05 — Profiler & inline edit (P3)

**Given** dev environment, **When** request completes, **Then** `BreadcrumbDataCollector` records trail; inline edit context resolves via `BreadcrumbInlineEditResolver` when access checker allows.

---

## Requirements

### Bundle & config

- **FR-BUNDLE-001**: `NowoBreadcrumbKitBundle` alias `nowo_breadcrumb_kit`.
- **FR-CFG-001**: `Configuration` — project id, doctrine connection/prefix, cache TTL/pool, locales, presentation, dashboard (incl. `css_framework` / `icon_set` / pagination / modals), security, inline edit.
- **FR-CFG-002**: `BreadcrumbKitExtension` loads services, dashboard, profiler YAML; prepends Framework asset package `nowo_breadcrumb_kit`; when present, prepends UiKit defaults and FormKit profile `breadcrumb_kit` without overriding explicit host keys / `default_profile`.
- **FR-PHP-001**: Backed enums `CssFramework`, `IconSet`, `ModalSize` for closed dashboard config sets.
- **FR-DEP-001**: Composer requires `nowo-tech/ui-kit-bundle` ^1.4 and `nowo-tech/form-kit-bundle` ^2.0; Symfony floor `^7.4 || ^8.0` (aligned with FormKit).

### Domain model

- **FR-ORM-001**: `BreadcrumbCollection`, `BreadcrumbItem` entities with route/label/link metadata.
- **FR-ORM-002**: Repositories with collection-scoped queries.
- **FR-EVT-001**: `TablePrefixSubscriber` applies configurable table prefix.

### Trail resolution

- **FR-SVC-001**: `BreadcrumbLoader` — match route, build trail, cache item lists.
- **FR-SVC-002**: `BreadcrumbUrlResolver` / interface — generate crumb URLs from route params.
- **FR-SVC-003**: `BreadcrumbInlineEditResolver` + `BreadcrumbInlineEditAccessCheckerInterface`.

### Import/export

- **FR-SVC-004**: `BreadcrumbExporter`, `BreadcrumbImporter` — portable trail definitions.

### DTOs

- **FR-DTO-001**: `BreadcrumbNode`, `BreadcrumbTrailView`, `BreadcrumbInlineEditContext`.

### HTTP — dashboard

- **FR-CTRL-001**: Collection/item CRUD, index, import/export controllers; shared dashboard traits.
- **FR-SEC-001**: Dashboard access via `security.access_roles` / optional `access_checker` / `allow_unauthenticated` (demo); `DashboardAccessSubscriber` + `DashboardSecurityPass`.

### Forms

- **FR-FORM-001**: Collection/item types, dashboard search/delete/import forms.
- **FR-FORM-002**: `JsonObjectTransformer`, `JsonStringListTransformer` for JSON fields (empty/null-friendly UX; preferred over FormKit `addJsonField` where entity null semantics matter).
- **FR-FORM-003**: CRUD form types (`BreadcrumbItemType`, `BreadcrumbCollectionType`, `ImportBreadcrumbType`) use FormKit `FormOptionsTrait` + `#[FormKitConfig('breadcrumb_kit')]`; registered as `form.type` services so `FormOptionsMerger` is injected.

### Twig & UI composition

- **FR-TWIG-001**: `BreadcrumbExtension`, dashboard globals/link extensions; `TwigPathsPass`.
- **FR-TWIG-002**: Public breadcrumb + dashboard Twig templates (overridable under host `templates/bundles/NowoBreadcrumbKitBundle/`).
- **FR-UI-001**: Dashboard markup imports `@NowoUiKitBundle/macros/ui.html.twig` and loads `asset('css/nowo-ui.css', 'nowo_ui_kit')`; `dashboard.js` remains on package `nowo_breadcrumb_kit` (REQ-UI-001-kit). Child templates must not nest/replace layout `nowo_ui_styles` / `nowo_ui_scripts` / content wrappers in a way that drops CDN or modal DOM.

### Profiler

- **FR-PROF-001**: `BreadcrumbDataCollector` + collector Twig views.
- **FR-PROF-002**: `BreadcrumbProfilerRecorder`; polyfill for template-aware collector.

### DI

- **FR-DI-001**: Core `services.yaml` (incl. Form `form.type` resource), dashboard/profiler service files.
- **FR-DI-002**: `BreadcrumbInlineEditAccessLocatorPass`.

### Assets & i18n

- **FR-ASSET-001**: `dashboard.js` for modal CRUD UX (bootstrap and non-bootstrap / custom stacks).
- **FR-ASSET-002**: Named Symfony asset package `nowo_breadcrumb_kit` (`base_path` `/bundles/nowobreadcrumbkit`).
- **FR-I18N-001**: Seven locale translation files (`en`, `es`, `de`, `fr`, `it`, `nl`, `pt`); form labels stay under `form.breadcrumb_*` in domain `NowoBreadcrumbKitBundle`.

---

## Success Criteria

- **SC-001**: **80/80** production files under `src/` mapped in [`code-inventory.md`](code-inventory.md).
- **SC-002**: Config keys match `docs/CONFIGURATION.md` (incl. UiKit / FormKit prepend notes).
- **SC-003**: QA/CI green (`make release-check` / PHPUnit / PHPStan / coverage floor).
- **SC-004**: Hosts registering the dashboard can resolve UiKit macros/CSS and FormKit profile `breadcrumb_kit` without forking BreadcrumbKit form PHP for basic field chrome.

---

## Explicit non-goals

- Dashboard authentication / firewall (host app; protect `dashboard.path_prefix`).
- External-only URLs without Symfony route backing.
- Owning UiKit CSS/macros or FormKit merger implementation inside this package.
- Doctrine schema migration **command** and event-based trail enrichers (documented as planned product follow-ups, not baseline).

---

## Validation

`composer qa` / `make release-check`, PHPUnit, PHPStan, inventory row audit (`find src -type f | wc -l` ↔ summary total).
