# Security


## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threat model](#threat-model)
- [Mitigations](#mitigations)
- [Content Security Policy (CSP)](#content-security-policy-csp)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging](#logging)
- [Dependencies](#dependencies)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)
- [AI security audit](#ai-security-audit)

## Scope

This document covers the **Breadcrumb Kit Bundle** (`nowo-tech/breadcrumb-kit-bundle`): PHP library code, Symfony integration (configuration, services, Twig), and Doctrine entities used to store breadcrumb metadata.

It does **not** cover your application’s routes, authentication, or hosting environment unless you configure them to interact with this bundle.

## Attack surface

| Input | Description |
|-------|-------------|
| **Configuration** | YAML under `nowo_breadcrumb_kit` (locales, cache pool, Doctrine connection, table prefix). |
| **HTTP requests** | The loader reads the current `Request` (route name, route parameters) to match items and generate URLs. |
| **Dashboard HTTP** (when `web_ui.enabled: true`) | CRUD routes under `web_ui.path_prefix`: collections, items, JSON export/import. Forms use CSRF. Bundle-level access is enforced via `security.access_roles` / optional `security.access_checker` (REQ-UI-002) unless `security.allow_unauthenticated` is true (demo only). |
| **JSON import uploads** | Dashboard import accepts a JSON file bounded by `dashboard.import_max_bytes` (default 2 MiB). |
| **Database** | `BreadcrumbCollection` and `BreadcrumbItem` rows (labels, JSON translations, route names, parameters, parameter mappings). |
| **Twig rendering** | Output uses labels and resolved URLs produced by the loader and `RouterInterface`. Item `icon` / collection `homeIcon` are printed raw (markup icons) after `BreadcrumbIconSanitizer`. |
| **Inline edit iframe** | When `inline_edit.query_param` is set and access checks pass, the default breadcrumb template may embed the dashboard item form in an `<iframe>`. |

When the dashboard is enabled, HTTP endpoints are registered; protect `web_ui.path_prefix` in your application (firewall, `access_control`, VPN, etc.). When disabled, exposure depends on how your app renders breadcrumbs and who can change database content.

## Threat model

| Threat | Risk notes |
|--------|------------|
| **Injection (SQL)** | Mitigated by Doctrine ORM when using parameterized APIs; avoid raw SQL on bundle tables without binding. |
| **XSS** | Labels and URLs are auto-escaped by Twig. Icons are markup and printed raw: `BreadcrumbIconSanitizer` (allowlist of SVG / inline HTML elements and attributes, no event handlers, scripts or external URLs) validates them in the dashboard forms, cleans them on JSON import and re-sanitizes stored values in `BreadcrumbLoader` before rendering. `presentation.home_icon` comes from application config and is trusted. Do not disable escaping for other DB content in overridden templates. |
| **Open redirect / unsafe URLs** | URLs are generated only via Symfony’s router from configured route names and request parameters; external URLs are not supported by design. Exception: a route whose host is a variable links to whatever host that route allows (see Parameter mapping). |
| **Unreachable links** | `link_access_checker` hides links, it does not protect pages: the target routes keep their own access checks. Its answer is never cached by the bundle (the item cache holds rows, not URLs or answers). A rendered page or fragment kept in a shared cache can still show one user's links to another, so do not cache trails across users. The option is opt-in; an alias of the interface is not picked up without it, a blank value and a service whose class does not implement the interface, a decorator of it included, are refused at compile time (a synthetic service, or a factory that returns another type than it declares, is checked only by the constructor's type, at runtime). A `Throwable` from the checker is logged and drops the link, the page stays up. A hidden crumb has no URL, `linkEnabled: false` and no route parameters. |
| **Parameter mapping** (`params`, `target=@source`) | Whoever can edit items (dashboard, JSON import, `setDynamicParamKeys()`) chooses which parameter of the current route goes into a crumb URL, as a path or query parameter. Request attributes are out of reach unless `param_map.attributes` declares them: a declared value can reach access logs and browser history through a crumb URL, so declare none that hold secrets. Names starting with `_` are refused on both sides, so framework attributes (`_controller`, `_firewall_context`) cannot be read and generator parameters (`_fragment`, `_scheme`) cannot be set. Only non-empty strings and integers are used, from at most 16 sources. Values reach the router, which encodes path values, and Twig, which escapes the URL. `BreadcrumbUrlResolver` checks route requirements itself, whatever `framework.router.strict_requirements` says (Symfony's production recipe sets `null`, which turns the generator's check off); a failure gives a crumb with no link. This also covers a localized route named by its base name (`x` for `x.en`), looked up as the generator does. An alias or an unrelated route called `x.en` beside `x`, which generators read differently, must pass along with `x`. The check reads that route: a URL generator of the host that retargets it to another route with a different requirement is not followed. The generator does not encode host variables, so they must also hold only letters, digits, `.`, `_` and `-`. A route whose whole host is a variable, or with a wide requirement, can still link to any host name those characters and that requirement allow: do not map request values to such a variable. The `before:` modifier is a plain string cut, with no regular expression. |
| **Cache poisoning** | PSR-6 cache keys are derived from collection code, context, and locale. Ensure only trusted actors can change breadcrumb rows or cache configuration. |
| **Denial of service** | Large trees or frequent cache misses increase DB load; use sensible TTL and pool sizing. Editor-supplied `pathPattern` regexes are compiled per request: `BreadcrumbPathPatternValidator` rejects patterns that do not compile or use nested quantifiers (catastrophic backtracking) in the dashboard form and the JSON import. |
| **Unauthenticated dashboard** | Mitigated by default `security.access_roles: [ROLE_ADMIN]` + `DashboardAccessSubscriber`. `allow_unauthenticated: true` disables that gate (demo only). Still add host `access_control` on `web_ui.path_prefix`. |
| **CSRF on dashboard mutations** | Delete / CRUD / import go through Symfony forms. **Fails closed**: `CsrfProtectionPass` refuses to compile the container when the dashboard is enabled without `security.csrf.token_manager` + `form.type_extension.csrf`, or with `framework.form.csrf_protection: false` (forms declaring their own CSRF options would otherwise accept tokenless POSTs). |
| **Large JSON uploads** | Import is capped by `dashboard.import_max_bytes`; keep a reasonable limit in production. |

## Mitigations

- Keep Symfony, Doctrine, and this bundle updated; run `composer audit` regularly.
- Restrict who can manage breadcrumb entities (admin UI, migrations, fixtures).
- When using the dashboard: keep `security.allow_unauthenticated: false`, set `security.access_roles` (or a custom `security.access_checker`), and add host `access_control` for `web_ui.path_prefix`.
- Enable CSRF protection (`framework.csrf_protection: true` and form CSRF); the dashboard refuses to compile without it.
- Never enable `security.allow_unauthenticated` outside local demos.
- Use a dedicated cache pool with appropriate TTL for production.
- Prefer least-privilege DB credentials for the application.

## Content Security Policy (CSP)

Every inline `<script>` / `<style>` rendered by the bundle templates carries `nonce="…"` taken from the request attribute **`csp_nonce`** when it is present (nothing is emitted otherwise). Your CSP listener should set it before rendering, e.g. `$request->attributes->set('csp_nonce', $nonce)`, and send the same value in `script-src 'nonce-…'` / `style-src 'nonce-…'`. External scripts (`src=…`) and JSON islands need no nonce, and templates use no inline event handlers (`onclick`, `onsubmit`, …). `tests/Unit/Templates/InlineBlocksDeclareNonceTest.php` enforces this.

The dashboard layout's `tailwind` option loads the Tailwind Play CDN, which injects `<style>` at runtime without a nonce; use a prebuilt stylesheet under a strict `style-src`.

## Secrets and cryptography

The bundle does **not** handle end-user passwords or API keys. Do not store secrets in breadcrumb labels or translation JSON.

## Logging

Avoid logging full request bodies or sensitive route parameters. If you add custom logging around breadcrumbs, redact personal data per your policy.

What the bundle logs (`Psr\Log\LoggerInterface`, autowired; `NullLogger` when built by hand):

| Event | Level | Context |
|-------|-------|---------|
| JSON import started (`nowo_breadcrumb_kit:import` CLI and dashboard import) | info | `bundle`, `action`, `strategy` |
| Import finished without errors | info | + `created`, `updated`, `skipped`, `errors` (counts) |
| Import finished with rejected entries | warning | same counts |
| Import aborted by an exception (transaction rolled back) | error | + `exception` (class name only) |
| `link_access_checker` threw, link dropped | error | `bundle`, `action`, `route` (name), `exception` (class name only) |

Never logged: file contents, labels, route parameters, URLs or exception messages (they may echo payload data).

## Dependencies

- Run `composer audit` before releases.
- Pin compatible Symfony and Doctrine versions per `composer.json`.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| # | Item | Done |
|---|------|------|
| 1 | `docs/SECURITY.md` reviewed and current | [ ] |
| 2 | `.env` / local secrets not committed (see root `.gitignore`) | [ ] |
| 3 | No secrets or credentials in the repository | [ ] |
| 4 | Configuration defaults and documented options are safe | [ ] |
| 5 | Inputs validated at app level where users edit breadcrumb data | [ ] |
| 6 | Twig output remains escaped for untrusted content | [ ] |
| 7 | `composer audit` executed; issues triaged | [ ] |
| 8 | Logging does not leak sensitive data | [ ] |
| 9 | No unsafe deserialization of untrusted data in bundle code | [ ] |
| 10 | Permissions for changing breadcrumb data are appropriate | [ ] |
| 11 | Public exposure of admin routes (if any) reviewed in the app | [ ] |
| 12 | Rate limits / DoS considerations for DB-heavy pages (app-level) | [ ] |
| — | **AI security audit (REQ-SEC-004)** | Grade **Pass (conditional)** / risk **Medium** (2026-07-28). Recorded in the Nowo monorepo `BUNDLES_SECURITY_ANALYSIS.md`. `link_access_checker` and parameter mapping (2.14.0): Appendix HD (2026-11-06, PR #71): audited at `c769e8c`, fixes re-verified at `47f44d9`: **Pass (good) / Low**, no open Critical, High or Medium. Route lookup fixes (2.14.1): Appendix HD (2026-11-07, PR #72, `7ecae34`): **Pass (good) / Low**, no open Critical, High or Medium. |

For responsible disclosure, see [`.github/SECURITY.md`](../.github/SECURITY.md).

## AI security audit

| Field | Value |
| ----- | ----- |
| Date | 2026-07-28 |
| Grade | Pass (conditional) |
| Risk | Medium |
| Method | Cursor campaign static pass (`src/`, dashboard security, recipe, demo, SECURITY docs) |
| Open residuals | Host must keep `access_control` on `web_ui.path_prefix`; never ship `allow_unauthenticated: true` in production; Twig auto-escape must stay on for DB labels; JSON import size/rate limits remain app-owned. |