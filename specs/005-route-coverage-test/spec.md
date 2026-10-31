# Feature Specification: Breadcrumb route coverage test helper

**Feature Branch**: `feat/breadcrumb-route-coverage`  
**Status**: Implemented (Unreleased)  
**Created**: 2026-10-31  
**Depends on**: [`001-baseline`](../001-baseline/spec.md)  
**Upstream request**: symfony-beacon KIT-51 — extract `BreadcrumbRouteCoverageTest` ("every page route has a breadcrumb")

---

## Summary

Hosts that want a breadcrumb on every page need a test that fails when a new page route ships without a row. The
bundle ships a PHPUnit trait for that, with a configurable non-page filter and a configurable row source.

## User scenarios

- **US-01 (P1)** — Given a host test using the trait and rows covering every page, when the suite runs, then it passes.
- **US-02 (P1)** — Given a new page route without a row, when the suite runs, then it fails listing that route (name, methods, path) and how to fix it.
- **US-03 (P1)** — Given JSON endpoints, downloads, redirects, format routes, POST-only and internal routes, when the suite runs, then they are not reported.
- **US-04 (P2)** — Given non-pages the rules cannot see, when the host lists names / paths (`fnmatch()` patterns) or a predicate, then they are skipped.
- **US-05 (P2)** — Given rows in the database or in a JSON / YAML fixture (export shape or flat `route` / `parent`), then both are read.
- **US-06 (P2)** — Given a row naming a removed route or a parent without a row, then the suite fails listing it.

## Requirements

- **FR-001** `Testing\BreadcrumbRouteCoverageTrait`: `assertEveryPageRouteHasBreadcrumb()` (KernelTestCase: `router`, rows from hooks), hooks `breadcrumbCoverageFixturePath()` (null → database), `breadcrumbCoverageCollection()`, `breadcrumbCoverageFilter()`, `breadcrumbCoverageChecksRows()`, `breadcrumbCoverageRows()`, `breadcrumbCoverageDatabaseRows()`; static `missingBreadcrumbRoutes()`, `assertBreadcrumbCoverage()`, `assertBreadcrumbRowsAreConsistent()` (`*` rows are not routes).
- **FR-002** `Testing\NonPageRouteFilter` (immutable): non-GET, `_` name or `/_` path, option `breadcrumb: false`, `_format` requirement / non-HTML default / `.{` path, non-HTML extension, `RedirectController`, controller return type JSON / redirect / file / streamed (reflection, switchable), `ignoredRoutes`, `ignoredPaths`, `isNonPage`; `nonPageReason()` for debugging.
- **FR-003** `Testing\BreadcrumbRows`: `fromEntityManager()`, `fromFile()` (JSON / YAML, clear errors), `fromArray()`; a route in several rows keeps its first parent.
- **FR-004** Shipped under the normal `autoload` (like UiKit `Test\PageContractTestCase`); `phpunit/phpunit` suggested for the host's `require-dev`; no runtime service, no config key.

## Success criteria

- **SC-001** Kernel tests (`CoverageTestKernel`: page, JSON, download, redirect, legacy redirect, format, POST-only, internal, opted-out and fragment routes) prove pass / fail with fixture and database rows.
- **SC-002** Unit tests cover every filter rule and every row shape; coverage stays at 100 %.
- **SC-003** USAGE "Testing: breadcrumb coverage" has a copy-paste test class.
