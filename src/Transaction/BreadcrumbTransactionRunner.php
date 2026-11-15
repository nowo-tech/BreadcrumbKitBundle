<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Transaction;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCacheInvalidator;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use Nowo\TransactionContract\TransactionRunnerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * 2.20 (Beacon spec 239): the runner of every bundle write (dashboard CRUD, import), registered as
 * `nowo_breadcrumb_kit.transaction_runner`. The bundle never runs its writes through a runner the application binds to
 * nowo-tech/transaction-contract's {@see TransactionRunnerInterface}: such a runner drives the application's entity
 * manager (it would commit the application's pending work with a breadcrumb write and reset that manager after a
 * breadcrumb failure).
 *
 * One attempt, one transaction (a savepoint inside a transaction the caller opened), the bundle's manager flushed inside
 * it and committed by the runner: the bundle never flushes inside a transaction it did not open. After any failure no
 * transaction of its own stays open: nowo-tech/transaction-doctrine rolls back to the entry level and closes the
 * connection only when that rollback fails (after a MySQL / MariaDB deadlock inside a nested flush the server dropped the
 * savepoints and `ROLLBACK TO SAVEPOINT` fails with 1305). A transient failure (deadlock, lock wait timeout,
 * serialization failure, lost savepoint) surfaces as {@see TransientStorageFailure} with a Retry-After hint: 503 on the
 * bundle's routes.
 *
 * - **Own entity manager** (`doctrine.entity_manager` names a manager that is not the application's default): the
 *   manager maps the breadcrumb entities only, so transaction-doctrine's ORM runner drives it; after a failure it
 *   forgets what the unit loaded or wrote, and a manager a failed flush closed is replaced through the registry.
 * - **Shared manager** (the default: the manager that maps the entities is the application's): the unit runs in a
 *   connection-level transaction, because transaction-doctrine's ORM runner would close and reset (or clear) that
 *   manager after a storage failure, detaching the application's entities behind its back. After a failure only the
 *   breadcrumb rows the unit added, wrote, changed or scheduled for deletion are forgotten (and the breadcrumb rows that
 *   point at them); every entity of the application stays managed. A failed flush closes the manager (the ORM does), and
 *   so does the runner when a flush of the failed unit had written entities of the application (the rollback undid
 *   them, audit lesson QN-M1): it stays closed for the rest of the request, so the application's next flush fails
 *   loudly. A closed manager is refused with a transient failure, never with `EntityManagerClosed`.
 *
 * Side effects follow the commit: the collection-changed events and the cache invalidation of
 * {@see BreadcrumbCacheInvalidator} are released once the OUTERMOST transaction committed (never inside a transaction
 * of the application, never after a rollback), and a failing listener cannot undo the commit.
 *
 * {@see self::run()} makes one attempt (HTTP writes: the client retries after the 503). {@see self::runRetrying()}
 * replays a transient failure a few times with full-jitter backoff, only when it is the outermost transaction: the
 * import, which must therefore be replayable.
 *
 * Stateless apart from the managers it is given: one instance serves every request of a long-running worker.
 */
final readonly class BreadcrumbTransactionRunner implements TransactionRunnerInterface
{
    /** The service id the bundle services are wired to. */
    public const SERVICE_ID = 'nowo_breadcrumb_kit.transaction_runner';

    /** Attempts of {@see self::runRetrying()} (the first one included). */
    public const RETRY_ATTEMPTS = 3;

    /** Upper bound of the first backoff, in milliseconds; it doubles per attempt (full jitter: 0 to the bound). */
    public const BACKOFF_BASE_MILLISECONDS = 50;

    /** Upper bound of any backoff, in milliseconds. */
    public const BACKOFF_CAP_MILLISECONDS = 1000;

    /**
     * @param EntityManagerInterface|BreadcrumbEntityManagerResetter $entityManager           the bundle's manager (or its resolver, asked per call)
     * @param ManagerRegistry|null                                   $registry                replaces an own manager a failed unit closed
     * @param string|null                                            $managerName             the registry name of the bundle's manager (`doctrine.entity_manager`); null: the shared manager
     * @param int|null                                               $retryAfterSeconds       Retry-After hint of the TransientStorageFailure; null for none
     * @param int                                                    $backoffBaseMilliseconds upper bound of the first backoff of {@see self::runRetrying()} (0 = no wait)
     * @param BreadcrumbCacheInvalidator|null                        $afterCommit             releases the events and the cache invalidation once the unit committed
     * @param LoggerInterface                                        $logger                  warns when a shared manager is closed because a failed unit had flushed the application's changes
     */
    public function __construct(
        private EntityManagerInterface|BreadcrumbEntityManagerResetter $entityManager,
        private ?ManagerRegistry $registry = null,
        private ?string $managerName = null,
        private ?int $retryAfterSeconds = 1,
        private int $backoffBaseMilliseconds = self::BACKOFF_BASE_MILLISECONDS,
        private ?BreadcrumbCacheInvalidator $afterCommit = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function run(callable $operation): mixed
    {
        $manager = $this->manager();
        if ($this->isOwnManager()) {
            // The bundle's own manager: transaction-doctrine's ORM runner. The application's manager is not involved.
            $result = DoctrineTransactionRunner::forEntityManager($manager, $this->registry, $this->managerName, $this->retryAfterSeconds)->run($operation);
        } else {
            // The application's manager (shared): a connection-level transaction, and only the breadcrumb rows the unit
            // touched are forgotten after a failure, unless the unit flushed the application's own changes.
            $result = $this->sharedUnit($manager, static function () use ($operation, $manager): mixed {
                $result = $operation();
                $manager->flush();

                return $result;
            });
        }

        // After the commit of the outermost transaction (a no-op inside a transaction of the application).
        $this->afterCommit?->dispatchPending($manager->getConnection());

        return $result;
    }

    /**
     * Same as {@see self::run()}, but a transient failure of the OUTERMOST transaction is replayed up to `$attempts`
     * times in all, after a full-jitter backoff. Inside a transaction the caller opened it is not replayed: the storage
     * may have rolled back the caller's work as well (a MySQL deadlock rolls back the whole transaction), so the failure
     * goes to the caller. The operation must be replayable: it loads what it changes under a lock, and its side effects
     * wait until this method returned.
     *
     * @template T
     *
     * @param callable(): T $operation the unit of work; replayable
     * @param int           $attempts  attempts in all (at least one)
     *
     * @return T what the operation returned
     *
     * @throws TransientStorageFailure when the last attempt failed transiently, or a nested one did
     */
    public function runRetrying(callable $operation, int $attempts = self::RETRY_ATTEMPTS): mixed
    {
        $attempts = max(1, $attempts);
        for ($attempt = 1;; ++$attempt) {
            $outermost = !$this->manager()->getConnection()->isTransactionActive();
            try {
                return $this->run($operation);
            } catch (TransientStorageFailure $failure) {
                if (!$outermost || $attempt >= $attempts) {
                    throw $failure;
                }
                $this->backoff($attempt);
            }
        }
    }

    /**
     * The bundle's manager of this call. An own manager a failed unit closed is replaced first (a shared one is not: it
     * stays closed, loudly, until the request boundary).
     */
    public function manager(): EntityManagerInterface
    {
        if ($this->entityManager instanceof BreadcrumbEntityManagerResetter) {
            if ($this->isOwnManager()) {
                $this->entityManager->resetIfClosed();
            }

            return $this->entityManager->entityManager();
        }

        return $this->entityManager;
    }

    /** Whether the bundle's manager is its own (a registered manager that is not the application's default). */
    private function isOwnManager(): bool
    {
        return $this->registry instanceof ManagerRegistry && null !== $this->managerName && $this->registry->getDefaultManagerName() !== $this->managerName;
    }

    /**
     * A unit on the shared manager's connection. Refuses a closed manager with a transient failure. After a failure with
     * the manager still open:
     *
     * - when a flush inside the unit wrote the application's entities, the database rolled them back but Doctrine keeps
     *   them as written: the manager is closed, so the application's next flush fails loudly instead of silently writing
     *   nothing (audit lesson QN-M1);
     * - otherwise only the breadcrumb rows the unit added, wrote, changed or scheduled for deletion are forgotten.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function sharedUnit(EntityManagerInterface $manager, callable $operation): mixed
    {
        if (!$manager->isOpen()) {
            throw new TransientStorageFailure($this->retryAfterSeconds, 'The entity manager was closed by an earlier failure of this request; it is replaced at the next request.');
        }
        $before = $this->breadcrumbRows($manager);
        $observer = new BreadcrumbFlushObserver();
        $events = $manager->getEventManager();
        $events->addEventListener([Events::onFlush], $observer);
        try {
            return DoctrineTransactionRunner::forConnection($manager->getConnection(), $this->retryAfterSeconds)->run($operation);
        } catch (Throwable $failure) {
            // A manager the ORM closed in a failed flush stays closed: loud for the application; replaced at the boundary.
            if (!$this->isClosed($manager)) {
                if ($observer->hostWritten) {
                    $manager->close();
                    $this->logger->warning('Breadcrumb Kit: a failed write rolled back changes of the application that the shared entity manager had flushed; the manager is closed until the next request.', ['bundle' => 'NowoBreadcrumbKitBundle', 'exception_class' => $failure::class]);
                } else {
                    $this->forgetBreadcrumbRows($manager, $before);
                }
            }

            throw $failure;
        } finally {
            $events->removeEventListener([Events::onFlush], $observer);
        }
    }

    /** Whether the manager is closed (read again after a failure: the ORM closes it in a failed flush). */
    private function isClosed(EntityManagerInterface $manager): bool
    {
        return !$manager->isOpen();
    }

    /**
     * Sleeps a random time between 0 and the attempt's bound (full jitter: concurrent losers do not retry in lockstep).
     */
    private function backoff(int $attempt): void
    {
        $bound = min(self::BACKOFF_CAP_MILLISECONDS, $this->backoffBaseMilliseconds << ($attempt - 1));
        if ($bound > 0) {
            usleep(random_int(0, $bound) * 1000);
        }
    }

    /**
     * The breadcrumb rows the manager holds, with the data it knows them by (before a unit of work).
     *
     * @return array<int, array<string, mixed>> object id => original data
     */
    private function breadcrumbRows(EntityManagerInterface $manager): array
    {
        $unitOfWork = $manager->getUnitOfWork();
        $rows = [];
        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            if (BreadcrumbFlushObserver::isBreadcrumbEntity($class)) {
                foreach ($entities as $entity) {
                    $rows[spl_object_id($entity)] = $unitOfWork->getOriginalEntityData($entity);
                }
            }
        }

        return $rows;
    }

    /**
     * After a failed unit: detaches the breadcrumb rows it added, wrote or changed (their database state was rolled
     * back); a row the caller held and the unit did not change stays managed, and so does every entity of the
     * application.
     *
     * @param array<int, array<string, mixed>> $before {@see self::breadcrumbRows()} before the unit
     */
    private function forgetBreadcrumbRows(EntityManagerInterface $manager, array $before): void
    {
        $unitOfWork = $manager->getUnitOfWork();
        // A row the unit scheduled for deletion is managed again, then forgotten (a later flush of the application must
        // not carry out the deletion of a failed unit).
        foreach ($unitOfWork->getScheduledEntityDeletions() as $removed) {
            if (BreadcrumbFlushObserver::isBreadcrumbEntity($removed::class)) {
                $manager->persist($removed);
                $manager->detach($removed);
            }
        }
        $rows = $unitOfWork->getScheduledEntityInsertions();
        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            if (BreadcrumbFlushObserver::isBreadcrumbEntity($class)) {
                foreach ($entities as $entity) {
                    $rows[] = $entity;
                }
            }
        }
        $stale = [];
        $kept = [];
        foreach ($rows as $entity) {
            if (!BreadcrumbFlushObserver::isBreadcrumbEntity($entity::class) || !$manager->contains($entity)) {
                continue;
            }
            $id = spl_object_id($entity);
            if (!isset($before[$id]) || $before[$id] !== $unitOfWork->getOriginalEntityData($entity) || $this->changed($manager, $entity)) {
                $stale[$id] = $entity;
            } else {
                $kept[$id] = $entity;
            }
        }
        // A kept row that points at a stale one goes too (it would drag it back as a new entity on the next flush).
        do {
            $more = false;
            foreach ($kept as $id => $entity) {
                $metadata = $manager->getClassMetadata($entity::class);
                foreach ($metadata->associationMappings as $field => $mapping) {
                    $target = $mapping->isToOne() ? $metadata->getFieldValue($entity, $field) : null;
                    if (\is_object($target) && isset($stale[spl_object_id($target)])) {
                        $stale[$id] = $entity;
                        unset($kept[$id]);
                        $more = true;

                        break;
                    }
                }
            }
        } while ($more);
        foreach ($stale as $entity) {
            $manager->detach($entity);
        }
    }

    /**
     * Whether the row has changes the manager would write (the ORM's own comparison with the data it knows it by).
     */
    private function changed(EntityManagerInterface $manager, object $entity): bool
    {
        $unitOfWork = $manager->getUnitOfWork();
        $unitOfWork->computeChangeSet($manager->getClassMetadata($entity::class), $entity);

        return [] !== $unitOfWork->getEntityChangeSet($entity);
    }
}
