# Feature Specification: BreadcrumbKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Status**: Active  

**Package**: `nowo-tech/breadcrumb-kit-bundle`  
**Configuration root**: `nowo_breadcrumb_kit`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Database-driven **breadcrumb trails** for Symfony: route matching, i18n labels, optional links, collection-level presentation, Twig helpers, optional **admin dashboard** (CRUD + import/export), **inline edit** hooks, and Web Profiler panel.

---

## User Scenarios

### US-01 — Route-matched trail (P1)

**Given** items stored for a collection, **When** current route matches an item's route criteria, **Then** `BreadcrumbLoader` builds a `BreadcrumbTrailView` with ordered `BreadcrumbNode` entries.

### US-02 — Twig rendering (P1)

**Given** a collection code, **When** `breadcrumb()` Twig function runs, **Then** `BreadcrumbExtension` resolves URLs via `BreadcrumbUrlResolver` and renders `breadcrumb.html.twig`.

### US-03 — Dashboard CRUD (P2)

**Given** `dashboard.enabled`, **When** admin manages collections/items, **Then** dashboard controllers expose index/form routes with modal partials and `dashboard.js` UX.

### US-04 — Import/export (P2)

**Given** YAML/JSON export from `BreadcrumbExporter`, **When** uploaded via `ImportExportController`, **Then** `BreadcrumbImporter` upserts collections and items.

### US-05 — Profiler & inline edit (P3)

**Given** dev environment, **When** request completes, **Then** `BreadcrumbDataCollector` records trail; inline edit context resolves via `BreadcrumbInlineEditResolver` when access checker allows.

---

## Requirements

### Bundle & config

- **FR-BUNDLE-001**: `NowoBreadcrumbKitBundle` alias `nowo_breadcrumb_kit`.
- **FR-CFG-001**: `Configuration` — project id, doctrine connection/prefix, cache TTL/pool, locales, presentation, dashboard, inline edit, profiler.
- **FR-CFG-002**: `BreadcrumbKitExtension` loads services, dashboard, profiler YAML.

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

### Forms

- **FR-FORM-001**: Collection/item types, dashboard search/delete/import forms.
- **FR-FORM-002**: `JsonObjectTransformer`, `JsonStringListTransformer` for JSON fields.

### Twig

- **FR-TWIG-001**: `BreadcrumbExtension`, dashboard globals/link extensions; `TwigPathsPass`.
- **FR-TWIG-002**: Public breadcrumb + dashboard Twig templates.

### Profiler

- **FR-PROF-001**: `BreadcrumbDataCollector` + collector Twig views.
- **FR-PROF-002**: `BreadcrumbProfilerRecorder`; polyfill for template-aware collector.

### DI

- **FR-DI-001**: Core `services.yaml`, dashboard/profiler service files.
- **FR-DI-002**: `BreadcrumbInlineEditAccessLocatorPass`.

### Assets & i18n

- **FR-ASSET-001**: `dashboard.js` for modal CRUD UX.
- **FR-I18N-001**: Seven locale translation files.

---

## Success Criteria

- **SC-001**: **67/67** files mapped in inventory.
- **SC-002**: Config keys match `docs/CONFIGURATION.md`.
- **SC-003**: QA/CI green.

---

## Explicit non-goals

- Dashboard authentication (host app).
- External-only URLs without Symfony route backing.

---

## Validation

`composer qa`, PHPUnit, PHPStan, inventory row audit.
