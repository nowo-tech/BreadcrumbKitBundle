# Demo application with FrankenPHP

This bundle ships a FrankenPHP demo aligned with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle):

| Demo | Symfony | PHP | Default port |
|------|---------|-----|--------------|
| `demo/symfony8/` | **8.1** | **8.5** | **8021** |

Use it to validate compatibility with the current Symfony 8 maintenance line. The FrankenPHP image uses the **newest PHP minor** available for Symfony 8 demos (REQ-DEMO-010): currently **PHP 8.5**.

## Contents

- [Overview](#overview)
- [Quick start](#quick-start)
- [Development vs production](#development-vs-production)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Troubleshooting](#troubleshooting)

## Overview

- **Demo UI**: templates under `demo/symfony8/templates/` use the same shell as **DashboardMenuBundle** demos (Bootstrap 5.3, Bootstrap Icons, `app-header` gradient navbar, `app-layout` with light gray `app-sidebar` / `app-main` / `app-aside`, `app-footer`) so all Nowo bundle demos feel consistent.
- **Compose project name**: `breadcrumb-kit-bundle-demo-symfony-8` (`docker-compose.yml` → `name:`).
- **FrankenPHP** serves the app from `demo/symfony8` with the parent bundle mounted at **`/var/breadcrumb-kit-bundle`** (Composer `path` repository), same layout as [DashboardMenuBundle](https://github.com/nowo-tech/dashboard-menu-bundle) / Twig Inspector demos: `../..` → `/var/<bundle-slug>-bundle` in `docker-compose.yml`, and `composer.json` uses `{ "type": "path", "url": "/var/…" }` **without** extra `options` (Composer defaults).
- **MySQL 8** runs in a separate container; **no DB port is published to the host** (only `expose:`). The app uses hostname `mysql` on the Docker network.
- **Caddyfiles**: `docker/frankenphp/Caddyfile` (**worker** mode) and `Caddyfile.dev` (**classic** / no worker). The entrypoint selects which file is active via **`FRANKENPHP_MODE`** (`worker` by default; set `classic` for per-request PHP / easier hot-reload). Independent of `APP_ENV`.
- **Developer UX** (with `APP_ENV=dev`, `APP_DEBUG=1`, Composer **dev** dependencies installed): **Symfony Web Debug Toolbar** + **Web Profiler** (`config/packages/web_profiler.yaml`, routes in `config/routes/web_profiler.yaml`), **`symfony/asset` + `symfony/stopwatch`** (in the demo `composer.json` so Twig can use `asset()` and the profiler can time requests), **`assets:install`** in the `Makefile` / Docker entrypoint for `public/bundles/`, and **`config/packages/dev/framework.yaml`** (`profiler.collect: true`). **[nowo-tech/twig-inspector-bundle](https://github.com/nowo-tech/TwigInspectorBundle)** (profiler panel + `/_template/…` route in `config/routes.yaml`). Twig Inspector: cookie / **Ctrl+Shift+T** by default; see `config/packages/dev/nowo_twig_inspector.yaml`.

Default HTTP **PORT** (host) is **8021** (see `demo/symfony8/.env.example`).

Public routes use the **`/{_locale}/…`** prefix (`en`, `es`). **`/`** redirects to `/en/` (default locale).

### Demo use-case map

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

Fixture source: `demo/symfony8/src/DataFixtures/BreadcrumbDemoFixtures.php`.

## Quick start

From `demo/symfony8/`:

```bash
cp .env.example .env   # first time only
make up
```

Default port: **8021**.

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
| Symfony / Twig | Toolbar, profiler, `twig.cache: false` | Default caching |
| OPcache | `docker/php-dev.ini` → `opcache.revalidate_freq=0` | Image defaults |
| FrankenPHP | Controlled by **`FRANKENPHP_MODE`** (see below), not by `APP_ENV` | Same |

Compose demos run with `APP_ENV=dev` and default **`FRANKENPHP_MODE=worker`** so the worker Caddyfile is exercised even while Symfony stays in debug mode. Set `FRANKENPHP_MODE=classic` for hot-reload-friendly classic PHP.

## Switching classic vs worker (`FRANKENPHP_MODE`)

Demos select the FrankenPHP runtime via **`FRANKENPHP_MODE`** in `.env` / `.env.example` (not a Dockerfile `ENV`):

| Value | Behaviour |
| --- | --- |
| **`worker`** (default) | Keep the worker Caddyfile (`php_server { worker ... }`) |
| **`classic`** | Entrypoint copies `Caddyfile.dev` (plain `php_server`, hot-reload friendly) |

Compose passes `FRANKENPHP_MODE=${FRANKENPHP_MODE:-worker}` into the PHP service. After changing `.env`, run `docker compose up -d` (or `make up`) so the container is **recreated** — a plain `restart` does not reload env. No image rebuild is required.

## Troubleshooting

- **Empty breadcrumb after changing fixtures or routes**: the loader may cache the item list per collection (`nowo_breadcrumb_kit.cache.pool`). In the demo, `config/packages/dev/nowo_breadcrumb_kit.yaml` disables that pool in `dev`. If you use another environment or pool, run `php bin/console cache:clear` (or `make cache-clear`) after `doctrine:fixtures:load`.
- **Web Debug Toolbar missing**: check `APP_ENV=dev`, `APP_DEBUG=1` (Compose sets both) and that dependencies were installed **with dev** (`composer install` without `--no-dev`). Run `php bin/console assets:install public` to publish `public/bundles/webprofiler`. A local `.env` with `APP_ENV=prod` must **not** override the `php` service env in Compose; if you run Symfony outside Docker, set `APP_DEBUG=1` in your `.env`.
- **Composer / path bundle**: ensure the repository root is mounted at `/var/breadcrumb-kit-bundle` (see `docker-compose.yml` volumes). Run `make update-bundle` after editing bundle code.
- **Database**: if schema is out of date, from the demo directory run `make down`, remove `.data/mysql` if you need a clean MySQL volume, then `make up` again.
- **Port in use**: change `PORT` in `.env` and ensure `DEFAULT_URI` matches (used by Symfony routing defaults).

For a longer, generic guide (reusable across Nowo bundles), see [DashboardMenuBundle `docs/DEMO-FRANKENPHP.md`](https://github.com/nowo-tech/dashboard-menu-bundle/blob/main/docs/DEMO-FRANKENPHP.md).
