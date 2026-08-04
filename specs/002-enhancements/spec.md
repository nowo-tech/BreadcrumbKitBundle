# Feature Specification: Post-2.0.14 enhancements

**Feature Branch**: `002-enhancements`  
**Status**: Active  
**Created**: 2026-08-04  
**Depends on**: [`001-baseline`](../001-baseline/spec.md) (v2.0.14+)

---

## Summary

Product follow-ups proposed after the UiKit/FormKit composition review: optional-dashboard packaging notes, FormKit coverage for remaining forms, single form chrome source, Doctrine migration CLI, import/export CLI, trail enricher events, path/attribute matching, trail preview, Form type tests, and Flex recipe updates.

---

## User Scenarios

### US-01 — Schema bootstrap (P1)

**Given** a host app with Doctrine Migrations, **When** they run `nowo_breadcrumb_kit:generate-migration`, **Then** a migration file creates `dashboard_breadcrumb_*` tables (and optional column updates).

### US-02 — CLI portable data (P1)

**Given** collections in the DB, **When** they run export/import console commands, **Then** JSON matches dashboard exporter/importer semantics.

### US-03 — Enrich trail nodes (P1)

**Given** a subscriber on `BreadcrumbTrailBuiltEvent`, **When** a trail is built, **Then** the subscriber may replace the `BreadcrumbTrailView` (labels, URLs, extra nodes).

### US-04 — Path / attribute match (P1)

**Given** an item with `pathPattern` and/or `matchAttributes` (and optional route `*`), **When** the request matches, **Then** `BreadcrumbLoader` selects that item (scored vs static params).

### US-05 — Preview (P2)

**Given** collection code + route/path, **When** preview CLI (or service) runs, **Then** it prints matched trail / status without requiring a full HTTP dashboard session.

### US-06 — Form Kit completeness (P2)

**Given** dashboard search form, **When** rendered, **Then** it uses FormOptionsTrait / profile `breadcrumb_kit`; Twig does not duplicate input classes already set by FormKit.

---

## Requirements

- **FR-CLI-001**: `nowo_breadcrumb_kit:generate-migration` (SchemaTool create + `--update` for new item columns).
- **FR-CLI-002**: `nowo_breadcrumb_kit:export` / `nowo_breadcrumb_kit:import` (JSON file or stdout/stdin).
- **FR-CLI-003**: `nowo_breadcrumb_kit:preview` — resolve trail for a synthetic request.
- **FR-EVT-002**: `BreadcrumbTrailBuiltEvent` dispatched after a successful / empty trail build (with request when available); mutable view.
- **FR-ORM-003**: `BreadcrumbItem.pathPattern` (nullable string) and `matchAttributes` (nullable JSON map).
- **FR-SVC-005**: Loader matches path/attributes; route name `*` allowed when path or attributes constrain the match.
- **FR-SVC-006**: `BreadcrumbTrailPreview` builds a Request and calls the loader.
- **FR-FORM-004**: `DashboardGetSearchType` uses FormKit; delete form remains CSRF-only (no fields).
- **FR-UI-002**: Dashboard form partials rely on FormKit field classes; avoid duplicate `ui.input()` class overrides on standard widgets.
- **FR-DEP-002**: Document that UiKit/FormKit stay hard `require` in 2.x; optional/suggest packaging is a future major (trail-only installs still pull kits today).
- **FR-RECIPE-002**: Flex recipe registers UiKit + FormKit bundles and optional `nowo_form_kit.yaml` stub.
- **FR-TEST-001**: Unit/integration coverage for new matching, event, and CLI smoke; Form type builds with merger stub where practical.

---

## Success Criteria

- **SC-001**: New `src/` files appear in baseline inventory (or this feature inventory section).
- **SC-002**: Existing loader tests pass; new tests cover path/`*` match and event mutation.
- **SC-003**: `make release-check` green (coverage ≥ 99%).

---

## Explicit non-goals

- Splitting Composer packages in 2.x (optional kits).
- Changing DashboardMenuBundle (FormKit alignment there is out of this repo).
- Full Symfony form theme package beyond FormKit defaults.

---

## Validation

PHPUnit, PHPStan, inventory audit, demo smoke optional for CLI-only changes.
