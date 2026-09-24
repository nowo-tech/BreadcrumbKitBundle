# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/breadcrumb-kit-bundle` (`symfony-bundle`) |
| Audited revision | `v2.1.7` / `a4b408a` |
| Audit date | 2026-09-23 |
| Method | Manual review of every file under `src/` (services, controllers, Twig extensions, form types, subscribers, DI extension, compiler passes, `Resources/config/*.yaml`) |
| Remediation (2026-09-24) | W-01 resolved (closed EntityManager reset on **every** main request / main exception and after the caught unique violation); W-02 resolved for staleness (`Query::HINT_REFRESH` on both lookups), identity-map growth accepted as the application's responsibility; W-04 (stale token when no firewall ran) found and resolved; regression tests in `tests/Integration/WorkerModeDoctrineTest.php` and unit tests |
| **Verdict** | ✅ **Viable** under scenario B — every bundle service is stateless, a closed EntityManager is reset by the bundle before the next main request (including public `breadcrumb_render()`), breadcrumb lookups refresh from the database, and the security token is only trusted when a firewall handled the request. Clearing the identity map between requests remains the application's responsibility |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | All bundle services are `final readonly` or only hold `readonly` constructor config; FormKit's `FormOptionsTrait` builder slot is restored in a `finally` |
| Static properties / `static` locals | ✅ | None; only pure static helpers (`BreadcrumbImporter::normalizeImportPayload()`, enum `values()`, `DashboardControllerTrait::resolveModalClasses()`) |
| `ResetInterface` / `kernel.reset` coverage | ✅ | Nothing in the bundle needs a reset; `BreadcrumbDataCollector::reset()` clears `$data`. The bundle no longer depends on Doctrine's resetter for correctness (W-01, W-02 resolved) |
| Request / user / locale captured in services | ✅ | `RequestStack::getCurrentRequest()` and `TokenStorage::getToken()` are read per call, never stored; the token is ignored when no firewall ran (W-04, resolved) |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; config is compiled into container parameters |
| Doctrine / EntityManager | ✅ | Closed EM reset through `ManagerRegistry` on dashboard routes (W-01, resolved); lookups use `HINT_REFRESH` (W-02, resolved); identity-map growth is the application's responsibility |
| Output, headers, `exit`, shutdown functions | ✅ | Only `echo` inside `StreamedResponse` callbacks (export), which is the Symfony-supported path |
| Resources (files, sockets, cURL) held open | ✅ | None; uploaded import files are read with `UploadedFile::getContent()` |
| Memory growth across requests | ✅ | No accumulating arrays in services; profiler snapshots live on the Request attributes; Doctrine identity-map growth is bounded by the number of collections / items (W-02, accepted) |
| Blocking I/O and timeouts | ✅ | Only DB queries and the PSR-6 cache pool; no HTTP, DNS or processes |
| Third-party static state | ✅ | FormKit trait state is per-instance and restored; no static state used |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Service\BreadcrumbLoader` | yes (public) | none (`final readonly`; optional PSR-6 pool) | ✅ | ✅ (W-02 resolved) |
| `Service\BreadcrumbUrlResolver` | yes (public) | none (`final readonly`) | ✅ | ✅ |
| `Service\BreadcrumbInlineEditResolver` | yes | none (`final readonly`; reads token per call, only when a firewall ran) | ✅ | ✅ (W-04 resolved) |
| `Service\BreadcrumbTrailPreview` | yes | none; pushes a synthetic request and pops it in `finally` (`src/Service/BreadcrumbTrailPreview.php:48-53`) | ✅ | ✅ |
| `Service\BreadcrumbExporter` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\BreadcrumbImporter` | yes | none (`final readonly`); writes through the EntityManager | ✅ | ✅ (a failed flush on the dashboard import route is reset by `DashboardEntityManagerSubscriber`; CLI N/A) |
| `Service\BreadcrumbEntityManagerResetter` (new) | yes | none (`final readonly`) | ✅ | ✅ |
| `Profiler\BreadcrumbProfilerRecorder` | yes | none; snapshots stored in Request attributes (`src/Profiler/BreadcrumbProfilerRecorder.php:61-65`) | ✅ | ✅ |
| `DataCollector\BreadcrumbDataCollector` (dev, WebProfiler only) | yes | `$data`, overwritten on every `collect()`, cleared by `reset()` | ✅ | ✅ |
| `Twig\BreadcrumbExtension`, `BreadcrumbKitDashboardLinkExtension`, `BreadcrumbKitDashboardGlobalsExtension` | yes | none (`readonly` config; globals are constant strings) | ✅ | ✅ |
| `EventSubscriber\DashboardAccessSubscriber` | yes | none (`final readonly`; token read per event, only when a firewall ran) | ✅ | ✅ (W-04 resolved) |
| `EventSubscriber\DashboardEntityManagerSubscriber` (new) | yes | none (`final readonly`) | ✅ | ✅ |
| `EventSubscriber\TablePrefixSubscriber` (Doctrine `loadClassMetadata`) | yes | none (`final readonly` prefix) | ✅ | ✅ |
| `Security\ConfigurableBreadcrumbKitAccessChecker` | yes | none (`final readonly` role list) | ✅ | ✅ |
| `Repository\BreadcrumbCollectionRepository`, `BreadcrumbItemRepository` | yes | none (no result caches) | ✅ | ✅ (W-02 resolved) |
| Dashboard controllers (`CollectionCrudController`, `ItemCrudController`, `ImportExportController`, `DashboardIndexController`) | yes | none (`readonly` config) | ✅ | ✅ (W-01 resolved) |
| 5 form types (`BreadcrumbCollectionType`, `BreadcrumbItemType`, `DashboardGetSearchType`, `DashboardPostDeleteType`, `ImportBreadcrumbType`) | yes | FormKit `FormOptionsTrait` fields; bound builder reset in `finally`, profile name memoized per class | ✅ | ✅ |
| 4 console commands | yes | CLI only | N/A | N/A |

Entities (`BreadcrumbCollection`, `BreadcrumbItem`), DTOs (`BreadcrumbNode`, `BreadcrumbTrailView`, `BreadcrumbInlineEditContext`) and `BreadcrumbTrailBuiltEvent` are created per request / per call and never stored in a service.

## Findings

### W-01 — Caught flush failure leaves the EntityManager closed (Medium)

- **Where:** `src/Controller/Dashboard/CollectionCrudController.php:138-157` (`new()`) and `:204-222` (`edit()`) catch `\Throwable` around `EntityManager::flush()` and, on a unique-constraint violation, keep rendering the form. `src/Service/BreadcrumbImporter.php:136,144,149` and `src/Controller/Dashboard/ItemCrudController.php:136,195,238` flush without a catch.
- **Worker impact:** a failed flush closes the Doctrine EntityManager. Under **A**, DoctrineBundle's `doctrine` registry (tagged `kernel.reset`) replaces the closed manager before the next request. Under **B** the closed manager survives, and every later request of that worker that touches breadcrumbs (including the public `breadcrumb_render()` Twig function) fails with "The EntityManager is closed" until the worker restarts.
- **Recommendation:** keep `services_resetter` active (scenario A). If the bundle wants to be B-safe, inject `ManagerRegistry` in `CollectionCrudController` and call `resetManager()` after catching the unique-constraint violation.
- **Status:** Resolved — confirmed (a duplicate code/context flush closes the manager). New `Service\BreadcrumbEntityManagerResetter` resets the manager of `BreadcrumbCollection` through `ManagerRegistry::resetManager()` when it is closed. `CollectionCrudController` calls it right after catching the unique violation (new optional constructor argument), and `EventSubscriber\DashboardEntityManagerSubscriber` calls it on **every main request** and **every main-request exception** (not only dashboard routes), so a later public `breadcrumb_render()` on the same worker recovers. Covered by `WorkerModeDoctrineTest::testCaughtUniqueViolationDoesNotLeaveTheManagerClosedForTheNextRequest` and unit tests.

### W-02 — Identity map is never cleared by the bundle (Medium)

- **Where:** `src/Service/BreadcrumbLoader.php:47` (`findOneByCodeAndContextKey()`) and `:314` (`findAllForCollection()`), plus the dashboard repositories.
- **Worker impact:** Doctrine returns the already-managed instance when a row is hydrated again, without overwriting its fields. Under **A** the Doctrine resetter calls `clear()` between requests, so each request sees fresh data. Under **B** the identity map of the long-lived EntityManager keeps every collection and item loaded so far: edits made from another worker or process (dashboard, import command) are not visible in this worker, and memory grows with the number of distinct collections / items loaded. The data is not user-specific, so this is stale data, not a cross-user leak.
- **Recommendation:** keep `services_resetter` active. Under B the application must clear the EntityManager itself (for example on `kernel.terminate`).
- **Status:** Resolved (staleness) / Accepted (growth) — confirmed. `findOneByCodeAndContextKey()` and `findAllForCollection()` now set `Query::HINT_REFRESH`, so already-managed collections and items are overwritten with the current row on every load; `WorkerModeDoctrineTest::testEditsFromAnotherWorkerAreVisibleOnTheNextRequest` covers two requests with an external update in between. The bundle does not call `clear()` on the application's manager: the identity map still holds the loaded collections / items (bounded by the table size), and clearing it between requests is the application's responsibility.

### W-03 — Item-row cache is not invalidated on dashboard writes (Info)

- **Where:** `src/Service/BreadcrumbLoader.php:302-325`; pool wired in `src/DependencyInjection/BreadcrumbKitExtension.php:282-287` (default `cache.app`, TTL 60 s from `src/DependencyInjection/Configuration.php`).
- **Worker impact:** none specific to worker mode. The cache is keyed by collection id and holds route definitions that are the same for all users, so it cannot leak user data. After a dashboard edit or import, trails can stay stale for up to `cache.ttl` seconds, exactly as under PHP-FPM.
- **Recommendation:** no change needed for worker mode. Lower `nowo_breadcrumb_kit.cache.ttl` if editors need instant feedback.
- **Status:** Not a bug — confirmed as behaviour identical to PHP-FPM; no change.

### W-04 — Stale security token trusted when no firewall ran (Medium)

- **Where:** `src/EventSubscriber/DashboardAccessSubscriber.php` (`onKernelController()`) and `src/Service/BreadcrumbInlineEditResolver.php` (`resolveUser()`).
- **Worker impact:** found during remediation. `TokenStorage` is reset only by `services_resetter`. Under **B**, a request that is not handled by any firewall (dashboard mounted outside a firewall, or inline editor on a public page) sees the token stored by the previous request of that worker, so the access checkers could receive another user.
- **Recommendation:** only read the token when a firewall handled the main request.
- **Status:** Resolved — both read the token only when the main request carries `_firewall_context` (set by the firewall listener); otherwise the user is `null`. The dashboard subscriber applies the guard to main requests only (sub-requests never carry `_firewall_context`); rendering dashboard controllers as sub-requests of a page outside every firewall is not supported. The inline editor always checks the main request. Covered by `DashboardAccessSubscriberTest::testStaleTokenFromPreviousRequestIsIgnoredWhenNoFirewallRan`, `testSubRequestUsesTheTokenOfTheMainRequest` and `BreadcrumbInlineEditResolverTest::testStaleTokenIsNotPassedToCheckerWhenNoFirewallRanForTheRequest`.

No other findings. Good patterns observed: services read the current request and security token on every call instead of storing them; profiler snapshots are attached to the Request rather than to the recorder; `BreadcrumbTrailPreview` always pops the synthetic request in `finally`.

## Usage recommendations in worker mode

- Keeping Symfony's `services_resetter` enabled (default in FrankenPHP's Symfony runtime) is still recommended. Without it the bundle is safe, but the application must clear its EntityManager between requests to bound memory (W-02).
- Custom `BreadcrumbInlineEditAccessCheckerInterface` / `BreadcrumbKitAccessCheckerInterface` implementations and `BreadcrumbTrailBuiltEvent` subscribers must stay stateless (or implement `ResetInterface`). They receive the request and user per call; do not store them in properties.
- Do not store `BreadcrumbTrailView` or entities returned by the loader in your own services between requests.
- The demo ships a worker-mode Caddyfile (`demo/symfony8/docker/frankenphp/Caddyfile`, `worker { … }` block) that can be used to reproduce behaviour.

## Re-audit triggers

Re-run this audit when a change adds: non-`readonly` properties to any service, an in-memory (non-PSR-6) cache in `BreadcrumbLoader` or the URL resolver, new Doctrine listeners that buffer changes, direct use of `$_SERVER` / `$_ENV` at runtime, or a new FormKit version that changes `FormOptionsTrait` state handling.
