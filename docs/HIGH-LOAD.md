# High load

The write-path table, the conflict codes, the host options and the residual risks are in [CONCURRENCY.md](CONCURRENCY.md); this page keeps the mechanics.

Since 2.20.0 (Beacon spec 239) the bundle is built for many concurrent requests on long-running workers: FrankenPHP worker
mode with the kernel **not** reset between requests, RoadRunner, Messenger consumers.

## Table of contents

- [What the bundle writes](#what-the-bundle-writes)
- [One unit of work per write](#one-unit-of-work-per-write)
- [Your entity manager is yours](#your-entity-manager-is-yours)
- [Lock conflicts: 409 and the conflict codes](#lock-conflicts-409-and-the-conflict-codes)
- [Races that no longer happen](#races-that-no-longer-happen)
- [One lock order](#one-lock-order)
- [What is still last-write-wins](#what-is-still-last-write-wins)
- [Database settings](#database-settings)
- [Workers](#workers)
- [Tests](#tests)

## What the bundle writes

The rendering side (`breadcrumb_trail()`, `breadcrumb_render()`, the loader, the profiler, the PSR-6 cache) only **reads**
and keeps no per-request state in its services (see [the worker audit](FRANKENPHP-WORKER-AUDIT.md)).

The bundle writes in three places, all of them administration (low volume, but concurrent with other administrators and
with imports):

- the **dashboard**: create, edit and delete collections and items;
- the **import** (`BreadcrumbImporter`: dashboard upload and `nowo:breadcrumb-kit:import`);
- the **cache**: dropping the cached item list of a changed collection.

It writes no files and keeps nothing in sessions.

## One unit of work per write

Every bundle write runs on the bundle's own runner, `nowo_breadcrumb_kit.transaction_runner`
(`Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbTransactionRunner`, built on nowo-tech/transaction-doctrine and
implementing nowo-tech/transaction-contract's `TransactionRunnerInterface`):

- one transaction per unit of work (a savepoint inside a transaction your code opened). The bundle's entity manager is
  flushed inside it and committed by the runner. The bundle never flushes inside a transaction it did not open;
- after any failure no transaction of the bundle stays open. The levels it opened are rolled back, and the connection is
  closed only when that rollback fails (after a MySQL / MariaDB deadlock inside a nested flush the server drops the
  savepoints, so `ROLLBACK TO SAVEPOINT` fails with 1305). The worker's next request never inherits a nested connection;
- side effects follow the commit: `BreadcrumbCollectionChangedEvent` and the second drop of the cache entry (below) are
  released once the OUTERMOST transaction committed, never inside a transaction of your application and never after a
  rollback. A listener or a cache backend that fails cannot undo the commit: it is logged;
- the import is replayed after a deadlock or lock wait, up to three attempts with full-jitter backoff, and only when it is
  the outermost transaction. Dashboard writes make one attempt and answer 409 (the user retries).

The dashboard takes the **collection row** first (`SELECT … FOR UPDATE`, none on SQLite, where a writing transaction locks
the whole database): item writes, edits, deletes and imports of one collection queue on it.

## Your entity manager is yours

The bundle writes through the manager that maps its entities: the one named by `nowo_breadcrumb_kit.doctrine.entity_manager`
(a manager that is not your default one is the bundle's own), else the one `auto_mapping` gave them (your default manager).

- **Own manager** (`doctrine.entity_manager` is a manager that is not the default): transaction-doctrine's ORM runner
  drives it. After a failure it forgets what the unit loaded or wrote, and a manager a failed flush closed is replaced
  through the registry. Your manager is never involved: no bundle write flushes, clears, closes or resets it
  (REQ-ARCH-001 rule 6).
- **Shared manager** (the default; the entities live on your default manager): the unit runs in a connection-level
  transaction, because transaction-doctrine's ORM runner would close and reset (or clear) your manager after a storage
  failure, detaching your entities behind your back. After a failure only the breadcrumb rows the unit added, wrote,
  changed or scheduled for deletion are forgotten (and the breadcrumb rows that point at them); every entity of your
  application stays managed and keeps its pending changes. A unit still **flushes what you left pending in the shared
  manager** (a bundle flush is a flush of that manager); if a unit that did so then fails, the database rolled your rows
  back but Doctrine keeps them as written, so the runner **closes** the manager: it stays closed for the rest of the
  request (your next flush fails loudly, a bundle write answers the conflict status) and the bundle replaces it at the start of the next
  main request. Nothing replaces the shared manager in the middle of a request, not even the dashboard's duplicate-key
  answer or the exception listener (before 2.20 both did): the form is rendered from the form data, and a page that needs
  the database after a failed write is yours to handle (the manager is closed on purpose).

The bundle never runs its writes through a runner your application binds to `TransactionRunnerInterface`
(DoctrineDeadlockRetryBundle's adapter, your own): such a runner drives your manager.

The connection is shared: when your code opens a transaction around a bundle call, the bundle's unit nests in it as a
savepoint, and a bundle failure rolls back to that savepoint only. A transient failure inside your transaction is not
replayed by the bundle (the server may have rolled back your work too): it reaches you as `TransientStorageFailure`;
retry your whole transaction.

## Lock conflicts: 409 and the conflict codes

Since 2.23.0 (REQ-RUNTIME-003) the answers, the codes and the host options are in [CONCURRENCY.md](CONCURRENCY.md). In
short: on the bundle's routes (a `nowo_breadcrumb_kit_*` route or a controller of the bundle, whatever the dashboard path
prefix), `StorageFailureSubscriber` (`kernel.exception`, priority -64) answers a `TransientStorageFailure` or a DBAL
`RetryableException` (deadlock, lock wait timeout, serialization failure) with **409** + `Retry-After` + `CONFLICT_001`
(`CONFLICT_002` and no `Retry-After` when part of the request was committed). Up to 2.22 it answered 503; set
`nowo_breadcrumb_kit.conflict_response.status: 503` to keep it.

| Failure | Answer |
|---------|--------|
| `TransientStorageFailure`, or a DBAL `RetryableException` | `409` + `Retry-After` + `CONFLICT_001` (503 with `conflict_response.status: 503`) |
| An item whose parent was deleted, moved to another collection or made its own descendant by a concurrent edit | `409 Conflict` through your error page, no `CONFLICT_*` code (a domain answer: reloading, not retrying, fixes it) |
| A collection or an item deleted by a concurrent request, then edited | `404 Not Found` |
| A unique key refused a concurrent write | Not mapped (before 2.23: 409): the forms and the importer resolve the expected race; any other violation is a real error (500) |

The storage error stays the previous exception, for your logs: no SQL or parameter reaches the client. The duplicate
`code` + `context key` of the "new" and "edit" forms keeps its form message (the form is shown again).

Your own controllers that call the bundle services map `TransientStorageFailure` themselves (or enable
DoctrineDeadlockRetryBundle's HTTP mapping, or nowo-tech/transaction-doctrine's responder).

## Races that no longer happen

| Before 2.20 | Since 2.20 |
|-------------|------------|
| Two imports of one collection (strategy `replace`) both deleted the old items and both inserted theirs: the collection kept two trees (or a deadlock answered a raw driver error, left the connection nested and closed the manager) | The collection row is locked first, the items are read under it: the imports queue and the last one wins with ONE tree |
| Two imports creating the same collection: the loser failed with a unique violation (and a closed manager) | The key is **claimed** with a plain `INSERT` in a savepoint; the loser learns that the key exists without a failed flush, locks the row, and replaces (or skips, with `skip_existing`) |
| A lock wait or deadlock in an import answered a raw driver error | `TransientStorageFailure`: replayed, then 409 (503 before 2.23) |
| An item created under a collection deleted meanwhile failed on the foreign key (500) | 404 |
| Two admins re-parenting two items under each other stored a cycle | The parent chain is read again with locking reads (`FOR SHARE`) under the collection lock, also inside a transaction of your application: 409 |
| A reader could cache the OLD rows between the flush (which dropped the cache entry) and the commit, and keep a stale trail until the next change | The entry is dropped again after the commit |
| A cache backend that threw dropped the whole write (the flush's `postFlush` threw) | The failure is logged; the write stands |
| A collection created by an import with no items announced no event | `BreadcrumbCollectionChangedEvent` follows the commit |
| `findOneByCodeAndContextKey()` used `LIMIT 1` before comparing | Keys are compared with `===` after the read, with no `LIMIT` before it (see the next section) |

The import runs one unit per collection, so a file with several collections never holds two collection locks at once: two
imports of the same collections in opposite orders cannot deadlock.

## One lock order

Collection row (dashboard item writes, edits, deletes, imports), then that collection's item rows. No unit takes an item
row and then a collection row. The claim insert of an import is the first statement of its unit, and every read of the unit
follows the lock. On MySQL's `REPEATABLE READ` the first plain read of a transaction fixes its snapshot, so the bundle's
reads that decide a write after the lock are **locking reads** (`FOR UPDATE` for the old items of a replaced collection,
`FOR SHARE` for the parent chain): they see the latest committed rows even when the unit is nested in a transaction of your
application that already read something. The one plain read of an import is the key hint, which the lock re-checks (the row
must still have the import's `code` and `context_key`, else the key is claimed again).

## What is still last-write-wins

- Two administrators saving the **same collection or item form**: the ORM writes the columns each form changed, so edits of
  different fields merge, and edits of the same field keep the last save. There is no optimistic version column (it would
  need a migration; this release adds none).
- Keys that differ only in letter case or trailing spaces: the bundle compares `code` and `context_key` with `===`. If the
  columns use a case-insensitive collation (the default of MySQL 8, MariaDB and SQL Server: `Main` and `main` collide) or a PAD
  SPACE one (MariaDB, MySQL 5.7, SQL Server: trailing spaces are ignored; MySQL 8's `utf8mb4_0900_ai_ci` is NO PAD), they collide
  on the unique key: the import reports `import.error.collection_key_conflict`, and a form shows its duplicate message. For exact keys use a
  binary NO PAD collation on `code` and `context_key` (see [UPGRADING](UPGRADING.md#to-2200): optional migration).

## Database settings

- **DBAL 3:** set `doctrine.dbal.use_savepoints: true` (DBAL 4 always nests with savepoints). Without it a bundle failure
  inside your transaction marks the whole transaction rollback-only.
- **Lock waits:** a unit waits for a locked row as long as the server lets it (`innodb_lock_wait_timeout`, 50 s by
  default on MySQL; `lock_timeout` on PostgreSQL, unlimited by default). Keep it below your request timeout (for example
  5-10 s for the web workers): a timeout becomes a 409, never a stuck worker.
- **Connection poolers:** PgBouncer in transaction pooling mode is fine (the bundle holds no session state between units);
  statement pooling is not supported.

## Workers

A manager a failed unit closed is replaced on the next request (`DashboardEntityManagerSubscriber`) and, for the bundle's own
manager, at every outermost request, message or command boundary (`BreadcrumbOwnEntityManagerSubscriber`). The runner, the
subscriber and the row-lock helper hold no state. For connections and managers left by other code, use
DoctrineDeadlockRetryBundle's worker guard, or DoctrineBundle's `doctrine_ping_connection` /
`doctrine_clear_entity_manager` Messenger middlewares.

## Tests

- `tests/Unit/Transaction`, `tests/Integration/Service/ImporterLoadReadinessTest.php`: the runner on both manager modes and
  the import races replayed deterministically on one connection (the competitor's commit is injected right after the
  importer's own read). SQLite.
- `tests/Integration/Kernel/DashboardWritesKernelTest.php`: the dashboard writes through a real kernel, shared and
  dedicated manager.
- `tests/Database` (suite `database`): a lock wait, two processes replacing one collection, two processes creating the same
  keys. Skipped without `BREADCRUMB_TEST_DATABASE_URL`.
- CI job `Load readiness`: MySQL 8.4, MariaDB 11.4 and PostgreSQL 16, DBAL 4 and DBAL 3, `--fail-on-skipped`.

```bash
BREADCRUMB_TEST_DATABASE_URL='mysql://root@127.0.0.1:3306/breadcrumb?serverVersion=8.4.0' vendor/bin/phpunit --testsuite database
```
