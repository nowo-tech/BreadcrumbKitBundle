# Concurrency

How Breadcrumb Kit stays correct when the same data is written at the same time (nowo-tech REQ-RUNTIME-003): the write
paths, the conflict answer and its codes, the host options and the residual risks. The mechanics (the runner, the lock
order, what happens to your entity manager) are in [HIGH-LOAD](HIGH-LOAD.md).

## Table of contents

- [Write paths](#write-paths)
- [Conflict codes](#conflict-codes)
- [What is not a conflict](#what-is-not-a-conflict)
- [Host options](#host-options)
- [Workers and entity managers](#workers-and-entity-managers)
- [Tests and CI](#tests-and-ci)
- [Residual risks](#residual-risks)

## Write paths

The rendering side (`breadcrumb_trail()`, the loader, the profiler, the PSR-6 cache) only reads. The bundle writes in the
dashboard and in the import, all of it administration.

| Path | Transaction | Concurrency control | Conflict answer |
| --- | --- | --- | --- |
| Collection create (dashboard) | One unit of `BreadcrumbTransactionRunner` (a savepoint inside yours) | Unique `code` + `context_key`, caught where the form handles it (flash message, the form is shown again) | 409 + `CONFLICT_001` + `Retry-After` on a lock conflict; a duplicate key is not a conflict |
| Collection edit (dashboard) | One unit | Collection row `SELECT … FOR UPDATE` first (a concurrent delete answers 404); same unique key and catch | As above |
| Collection delete (dashboard) | One unit | Collection row `FOR UPDATE`, then its items | 409 + `CONFLICT_001` |
| Item create, edit, delete, move (dashboard) | One unit | Collection row `FOR UPDATE`, then the item row; the parent chain is read again with `FOR SHARE` (a cycle, or a parent deleted or moved, answers a domain 409) | 409 + `CONFLICT_001`; tree change: domain 409, no code |
| Import (dashboard upload, `nowo:breadcrumb-kit:import`) | One unit **per collection**, replayed up to 3 times (full-jitter backoff) when it is the outermost transaction | Key claimed with `INSERT` in a savepoint (the loser reads the winner's row); then collection row `FOR UPDATE`; old items read with locking reads; one lock at a time (opposite-order imports cannot deadlock) | 409 + `CONFLICT_001`; `CONFLICT_002` when an earlier collection of the same file is committed |
| Cache drop and `BreadcrumbCollectionChangedEvent` | After the outermost commit; dropped again after the commit | A failing listener or cache backend is logged and cannot undo the commit | None (never fails the write) |

Every write runs on `nowo_breadcrumb_kit.transaction_runner` (nowo-tech/transaction-doctrine; the only `flush()` in
`src/`). A single flush per unit; side effects follow the outermost commit; after a failure no transaction of the bundle
stays open.

## Conflict codes

Since **2.23.0** (nowo-tech/transaction-doctrine ^1.5). A `TransientStorageFailure` (or a DBAL `RetryableException` the
runner did not wrap) reaching the kernel on a bundle route is answered by `StorageFailureSubscriber` with the platform
conflict answer:

| Code | When | Status | `Retry-After` | JSON `error` | Client should |
| --- | --- | --- | --- | --- | --- |
| `CONFLICT_001` | Deadlock (MySQL / MariaDB 1213, PostgreSQL 40P01), lock wait timeout (1205, 55P03), serialization failure (MariaDB 1020, PostgreSQL 40001); nothing of the request committed | 409 | `1` (or the policy's) | `concurrency_conflict` | Retry |
| `CONFLICT_002` | The same, after part of the request was committed: a later collection of a multi-collection import (`ConcurrencyConflict::$committed`), or a lost savepoint with no deadlock behind it (outcome unknown) | 409 | none | `concurrency_conflict_partial` | Read again, do not repeat blindly |

- JSON, XHR and `/api/` requests: `{"error": "…", "code": "CONFLICT_00n", "message": "…"}`.
- HTML: the "try again" page with `data-conflict-code="CONFLICT_00n"` (transaction-doctrine's template, extending UiKit's
  error page, when `NowoTransactionDoctrineBundle` is registered; a plain English page otherwise).
- Up to 2.22 the answer was 503 through your error page, with no code. JSON and API shapes of the bundle keep their keys
  (the bundle has no JSON API of its own: the dashboard answers HTML).

## What is not a conflict

- **Unique violations.** The only unique key is `code` + `context_key` of a collection. The dashboard forms catch it where
  it is an expected "only one" race (a flash message, the form is shown again); the importer claims the key with an
  `INSERT` in a savepoint and reads the winner's row. Any other unique violation is a real error (500). Until 2.22
  `StorageFailureSubscriber` answered it 409; it no longer maps it.
- **A changed item tree** (a parent deleted, moved to another collection or made a descendant of the item by a concurrent
  edit) is a domain answer: 409 through your error page, no `CONFLICT_*` code. Retrying the same submission gives the same
  answer; reloading fixes it.
- **A collection or item deleted meanwhile** answers 404.
- **Connection loss** is not mapped: it is a real error.

## Host options

```yaml
nowo_breadcrumb_kit:
    conflict_response:
        status: ~          # null (default): nowo_transaction_doctrine.conflict_response.status when that bundle is
                           # registered, else 409. 409 or 503 forces it for Breadcrumb Kit.
        error_page: false  # true: an HttpException with the status and Retry-After through your error page, no code in
                           # the body (the answer of 2.20 to 2.22).
```

- **409 or 503:** `status: 503` keeps the previous status with the code and `Retry-After` (for a load balancer that
  retries 503 only).
- **Your own shape:** tag a `ConflictResponseFormatterInterface` (nowo-tech/transaction-doctrine) to shape the answer, or
  set `error_page: true`.
- **Your controllers** that call the bundle services map `TransientStorageFailure` themselves, or use transaction-doctrine's
  responder.
- **Database settings:** `doctrine.dbal.use_savepoints: true` on DBAL 3; `innodb_lock_wait_timeout` / `lock_timeout` of
  5-10 s for the web workers (see [HIGH-LOAD](HIGH-LOAD.md#database-settings)).

## Workers and entity managers

Stateless services: FrankenPHP worker mode with the kernel **not** reset works. The bundle's own manager (a registry name
that is not your default) is reset only at its boundaries; the shared manager is never cleared, closed or reset except
when a failed unit had flushed your pending entities (then it stays closed for the rest of the request, loudly). Standard
and DDD hosts: see [HIGH-LOAD](HIGH-LOAD.md#your-entity-manager-is-yours).

## Tests and CI

- `tests/Database` (suite `database`, real servers): a lock wait timeout, two processes replacing one collection, two
  processes creating the same keys, opposite-order imports without deadlock, and (2.23) the conflict answer of a real
  lock wait timeout on every server.
- `tests/Unit/EventSubscriber/StorageFailureSubscriberTest.php`, `tests/Unit/DependencyInjection/Compiler/ConflictResponsePassTest.php`,
  `tests/Integration/Service/ImporterCommittedConflictTest.php`: the mapping to `CONFLICT_001` and `CONFLICT_002`, the 409 / 503
  option, the error page mode, the wiring.
- CI job `Load readiness`: MySQL 8.4, MariaDB 11.4 and PostgreSQL 16, DBAL 4 and DBAL 3, `--fail-on-skipped`; required on
  `main`.

## Residual risks

- **Last write wins** on the same field of the same collection or item form: no optimistic version column (it would need a
  migration). Edits of different fields merge.
- **Case and trailing spaces in keys:** on a case-insensitive or PAD SPACE collation, `Main` and `main` collide on the unique
  key (the import reports `import.error.collection_key_conflict`, the form shows its duplicate message). See
  [HIGH-LOAD](HIGH-LOAD.md#what-is-still-last-write-wins).
- **A multi-collection import is not atomic:** each collection commits on its own, so a conflict mid-file leaves the first
  collections stored (`CONFLICT_002`). Importing again is safe for `replace` and `skip_existing`.
- **A transient failure inside your transaction** is not replayed (the server may have rolled your work back too): retry
  your whole transaction.
- **SQLite** has no row locks; a writing transaction locks the whole database (development only).
