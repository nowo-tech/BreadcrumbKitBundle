# Contributing

Thank you for helping improve **Breadcrumb Kit Bundle**.


## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

## Workflow

1. Fork the repository and create a feature branch from `main`.
2. Install dependencies: `composer install` (or `make install` if you use Docker).
3. Make your changes and add or update tests near the behavior you touch.
4. Run quality checks before opening a pull request:

```bash
composer cs-check
composer phpstan
composer test
```

With Docker and the root `Makefile`:

```bash
make qa
make test-coverage
```

5. Open a pull request using the repository template; describe the change and how you tested it.

## Standards

- PHP: PSR-12, `declare(strict_types=1);`, English PHPDoc on public APIs.
- Follow existing patterns for services, configuration, and Twig integration.
- Update `docs/CHANGELOG.md` for user-visible changes.

## Reporting issues

Use [GitHub Issues](https://github.com/nowo-tech/BreadcrumbKitBundle/issues) with the appropriate template (bug, feature, support).

## Coverage exclusions

PHPUnit excludes paths that are thin wiring or require a full HTTP/Doctrine stack; the reported **Lines** percentage applies to the remaining `src/` code. Excluded from coverage (see `phpunit.xml.dist`):

- `src/Controller/Dashboard/` — HTTP CRUD controllers (protect in the app; manual or functional tests recommended).
- `src/Form/Dashboard/` and entity form types — Symfony Form definitions.
- `src/Repository/` — Doctrine query builders (integration tests optional).
- Dashboard Twig globals/link helpers and the Web Profiler polyfill.

Target: **~100%** on included code; CI enforces a **99%** floor via `coverage-check`.
## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks/commit-msg` (or sets `core.hooksPath` to `.githooks`). Run it once per clone before your first commit.
If CI fails because trailers are already on the remote, see [GITHUB_CI.md](GITHUB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.
