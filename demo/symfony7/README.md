# Breadcrumb Kit Bundle — Symfony 7 demo

FrankenPHP + MySQL demo that mounts the parent bundle at `/var/breadcrumb-kit-bundle` (path repository).

The demo **web UI** follows the same Bootstrap 5 shell as [DashboardMenuBundle](https://github.com/nowo-tech/dashboard-menu-bundle) demos (navbar, sidebar, main, aside, footer).

## Requirements

- Docker / Docker Compose

## Start

```bash
cp .env.example .env   # if you do not have .env yet
make up
```

The Makefile ends with:

`Demo started at: http://localhost:<PORT>`

Default **PORT** is `8020` (see `.env.example`).

## Useful targets

| Target | Description |
|--------|-------------|
| `make down` | Stop containers |
| `make update-bundle` | `composer update` the bundle + clear cache |
| `make test` | Run the **bundle** PHPUnit from `/var/breadcrumb-kit-bundle` |
| `make verify` | Lint container + quick HTTP check |

## Documentation

- FrankenPHP setup (dev vs production worker): [../../docs/DEMO-FRANKENPHP.md](../../docs/DEMO-FRANKENPHP.md)

FrankenPHP worker mode: supported in production Caddyfile (`php_server { worker ... }`); development uses `Caddyfile.dev` without workers.
