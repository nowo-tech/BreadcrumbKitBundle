# Demo applications with FrankenPHP

This bundle ships two FrankenPHP demos, aligned with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle):

| Demo | Symfony | PHP | Default port |
|------|---------|-----|--------------|
| `demo/symfony7/` | 7.4 | 8.2 | **8020** |
| `demo/symfony8/` | **8.1** | **8.4** | **8021** |

Both apps share the same fixtures, routes, and UI shell; use Symfony 8 to validate compatibility with the current Symfony 8 maintenance line.

## Contents

- [Overview](#overview)
- [Quick start](#quick-start)
- [Development vs production](#development-vs-production)
- [Troubleshooting](#troubleshooting)

## Overview

- **Demo UI**: templates under `demo/symfony7/templates/` and `demo/symfony8/templates/` use the same shell as **DashboardMenuBundle** demos (Bootstrap 5.3, Bootstrap Icons, `app-header` gradient navbar, `app-layout` with light gray `app-sidebar` / `app-main` / `app-aside`, `app-footer`) so all Nowo bundle demos feel consistent.
- **Compose project names**: `breadcrumb-kit-bundle-demo-symfony-7` and `breadcrumb-kit-bundle-demo-symfony-8` (`docker-compose.yml` → `name:`).
- **FrankenPHP** serves the app from `demo/symfony7` with the parent bundle mounted at **`/var/breadcrumb-kit-bundle`** (Composer `path` repository), same layout as [DashboardMenuBundle](https://github.com/nowo-tech/dashboard-menu-bundle) / Twig Inspector demos: `../..` → `/var/<bundle-slug>-bundle` in `docker-compose.yml`, and `composer.json` uses `{ "type": "path", "url": "/var/…" }` **without** extra `options` (Composer defaults).
- **MySQL 8** runs in a separate container; **no DB port is published to the host** (only `expose:`). The app uses hostname `mysql` on the Docker network.
- **Caddyfiles**: `docker/frankenphp/Caddyfile` (production, **worker** mode) and `Caddyfile.dev` (development, **no worker**). The image entrypoint copies `Caddyfile.dev` over the active file when `APP_ENV=dev`.
- **Developer UX** (with `APP_ENV=dev`, `APP_DEBUG=1`, Composer **dev** dependencies installed): **Symfony Web Debug Toolbar** + **Web Profiler** (`config/packages/web_profiler.yaml`, routes in `config/routes/web_profiler.yaml`), **`symfony/asset` + `symfony/stopwatch`** (in the demo `composer.json` so Twig can use `asset()` and the profiler can time requests), **`assets:install`** in the `Makefile` / Docker entrypoint for `public/bundles/`, and **`config/packages/dev/framework.yaml`** (`profiler.collect: true`). **[nowo-tech/twig-inspector-bundle](https://github.com/nowo-tech/TwigInspectorBundle)** (profiler panel + `/_template/…` route in `config/routes.yaml`). Twig Inspector: cookie / **Ctrl+Shift+T** by default; see `config/packages/dev/nowo_twig_inspector.yaml`.

Default HTTP **PORT** (host) is **8020** (see `demo/symfony7/.env.example`).

Public routes use the **`/{_locale}/…`** prefix (`en`, `es`). **`/`** redirects to `/en/` (default locale).

### Demo use-case map (Symfony 7 app)

| Feature | Where to see it |
|--------|------------------|
| `breadcrumb_render()` + **application override** of `@NowoBreadcrumbKitBundle/breadcrumb.html.twig` (class `breadcrumb-app-override`) | Any page using the default block in `templates/base.html.twig` |
| **Translations** on labels (`en` / `es`) + **default_locale** fallback | Switch language in the header; compare breadcrumb labels on the same URL |
| **Collection styling**: `separatorIcon`, CSS classes, `responsiveConfig.breakpoint` | Public pages (`default` collection) — inspect markup / `data-breadcrumb-breakpoint` |
| **`homeIcon` on collection** | Set in fixtures for documentation; the bundle default Twig does not render it (override the template if you need it) |
| **Per-item `icon`** | Home segment (first crumb) |
| **Parent chain** + **`dynamicParamKeys`** (`id` on product) | `/en/shop` → `/en/product/42` |
| **`link_enabled = false`** (middle segment) | `/en/product/42` — “Shop” / “Tienda” is not a link |
| **Same route, different `static_route_params`** (best match wins) | `/en/topics/sales` vs `/en/topics/support` |
| **Second collection** + `breadcrumb_render('admin')` | `/en/admin`, `/en/admin/users` (admin layout) |
| **Deep trail (8 crumbs)** + responsive scroll (`breadcrumb--many`, `data-breadcrumb-count`) | `/en/demo/deep` … `/en/demo/deep/s1/s2/s3/s4/s5/s6` |
| **Admin deep trail (6 crumbs)** | `/en/admin/long/w1` … `/en/admin/long/w1/w2/w3/w4/w5` |
| **`breadcrumb_trail()`** custom markup | `/en/demo/breadcrumb-trail` |
| **`breadcrumb_render('default', 'breadcrumb/custom_fragment.html.twig')`** | `/en/demo/custom-template` |
| **No matching `BreadcrumbItem`** → empty trail | `/en/demo/no-match` |
| **PSR-6 item-list cache** (`nowo_breadcrumb_kit.cache`) | Enabled like in production apps; not visually distinct — see [USAGE.md](USAGE.md#caching) |
| **Dashboard CRUD** (collections + items + styles) | `http://localhost:<PORT>/breadcrumb-kit-admin/` (`nowo_breadcrumb_kit.dashboard.path_prefix`; demos use `framework.csrf_protection: true` without SecurityBundle) |

Fixture source: `demo/symfony7/src/DataFixtures/BreadcrumbDemoFixtures.php` (same file in `demo/symfony8/`).

## Quick start

**Symfony 7** — from `demo/symfony7/`:

```bash
cp .env.example .env   # first time only
make up
```

**Symfony 8.1** — from `demo/symfony8/`:

```bash
cp .env.example .env
make up
```

Default ports: **8020** (symfony7), **8021** (symfony8).

The Makefile prints:

`Demo started at: http://localhost:<PORT>`

Other useful targets: `make down`, `make update-bundle`, `make test` (runs the **bundle** PHPUnit from `/var/breadcrumb-kit-bundle`), `make verify`.

Aggregate targets from the bundle root:

```bash
make -C demo update-bundle-all
make -C demo release-check
```

## Development vs production

| Aspect | Development (`APP_ENV=dev`) | Production (`APP_ENV=prod`) |
|--------|---------------------------|------------------------------|
| FrankenPHP | Classic `php_server` (see `Caddyfile.dev`) | `php_server { worker /app/public/index.php 2 }` |
| Twig | `config/packages/dev/twig.yaml` sets `cache: false` | Default caching |
| OPcache | `docker/php-dev.ini` → `opcache.revalidate_freq=0` | Image defaults |

**FrankenPHP worker mode:** the bundle is exercised in production-style demos with workers enabled in `Caddyfile`. Use `APP_ENV=prod` (and rebuild/restart the container with the prod Caddyfile) to validate worker behaviour end-to-end.

## Troubleshooting

- **Breadcrumb vacío tras cambiar fixtures o rutas**: el loader puede cachear la lista de ítems por colección (`nowo_breadcrumb_kit.cache.pool`). En la demo, `config/packages/dev/nowo_breadcrumb_kit.yaml` desactiva ese pool en `dev`. Si usas otro entorno o pool, ejecuta `php bin/console cache:clear` (o `make cache-clear`) tras `doctrine:fixtures:load`.
- **No aparece la Web Debug Toolbar**: comprueba `APP_ENV=dev`, `APP_DEBUG=1` (en Docker Compose van fijados) y que instalaste dependencias **con dev** (`composer install` sin `--no-dev`). Ejecuta `php bin/console assets:install public` para publicar `public/bundles/webprofiler`. Un `.env` local con `APP_ENV=prod` **no** debe pisar las variables del servicio `php` en Compose; si ejecutas Symfony fuera de Docker, alinea `APP_DEBUG=1` en tu `.env`.
- **Composer / path bundle**: ensure the repository root is mounted at `/var/breadcrumb-kit-bundle` (see `docker-compose.yml` volumes). Run `make update-bundle` after editing bundle code.
- **Database**: if schema is out of date, from the demo directory run `make down`, remove `.data/mysql` if you need a clean MySQL volume, then `make up` again.
- **Port in use**: change `PORT` in `.env` and ensure `DEFAULT_URI` matches (used by Symfony routing defaults).

For a longer, generic guide (reusable across Nowo bundles), see [DashboardMenuBundle `docs/DEMO-FRANKENPHP.md`](https://github.com/nowo-tech/dashboard-menu-bundle/blob/main/docs/DEMO-FRANKENPHP.md).
