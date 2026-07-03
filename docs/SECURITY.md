# Security

## Scope

This document covers the **Breadcrumb Kit Bundle** (`nowo-tech/breadcrumb-kit-bundle`): PHP library code, Symfony integration (configuration, services, Twig), and Doctrine entities used to store breadcrumb metadata.

It does **not** cover your application’s routes, authentication, or hosting environment unless you configure them to interact with this bundle.

## Attack surface

| Input | Description |
|-------|-------------|
| **Configuration** | YAML under `nowo_breadcrumb_kit` (locales, cache pool, Doctrine connection, table prefix). |
| **HTTP requests** | The loader reads the current `Request` (route name, route parameters) to match items and generate URLs. |
| **Dashboard HTTP** (when `dashboard.enabled: true`) | CRUD routes under `dashboard.path_prefix`: collections, items, JSON export/import. Forms use CSRF tokens; the bundle does **not** enforce authentication or authorization. |
| **JSON import uploads** | Dashboard import accepts a JSON file bounded by `dashboard.import_max_bytes` (default 2 MiB). |
| **Database** | `BreadcrumbCollection` and `BreadcrumbItem` rows (labels, JSON translations, route names, parameters). |
| **Twig rendering** | Output uses labels and resolved URLs produced by the loader and `RouterInterface`. |
| **Inline edit iframe** | When `inline_edit.query_param` is set and access checks pass, the default breadcrumb template may embed the dashboard item form in an `<iframe>`. |

When the dashboard is enabled, HTTP endpoints are registered; protect `dashboard.path_prefix` in your application (firewall, `access_control`, VPN, etc.). When disabled, exposure depends on how your app renders breadcrumbs and who can change database content.

## Threat model

| Threat | Risk notes |
|--------|------------|
| **Injection (SQL)** | Mitigated by Doctrine ORM when using parameterized APIs; avoid raw SQL on bundle tables without binding. |
| **XSS** | Labels and URLs are passed to Twig; use Symfony/Twig auto-escaping in templates. Do not disable escaping for untrusted DB content. |
| **Open redirect / unsafe URLs** | URLs are generated only via Symfony’s router from configured route names and request parameters; external URLs are not supported by design. |
| **Cache poisoning** | PSR-6 cache keys are derived from collection code, context, and locale. Ensure only trusted actors can change breadcrumb rows or cache configuration. |
| **Denial of service** | Large trees or frequent cache misses increase DB load; use sensible TTL and pool sizing. |
| **Unauthenticated dashboard** | With `dashboard.enabled: true`, anyone who can reach the prefix can mutate breadcrumb data unless the app restricts access. |
| **Large JSON uploads** | Import is capped by `dashboard.import_max_bytes`; keep a reasonable limit in production. |

## Mitigations

- Keep Symfony, Doctrine, and this bundle updated; run `composer audit` regularly.
- Restrict who can manage breadcrumb entities (admin UI, migrations, fixtures).
- When using the dashboard, require authentication and appropriate roles on `dashboard.path_prefix`.
- Enable CSRF protection (`framework.csrf_protection: true` or SecurityBundle).
- Use a dedicated cache pool with appropriate TTL for production.
- Prefer least-privilege DB credentials for the application.

## Secrets and cryptography

The bundle does **not** handle end-user passwords or API keys. Do not store secrets in breadcrumb labels or translation JSON.

## Logging

Avoid logging full request bodies or sensitive route parameters. If you add custom logging around breadcrumbs, redact personal data per your policy.

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

For responsible disclosure, see [`.github/SECURITY.md`](../.github/SECURITY.md).
