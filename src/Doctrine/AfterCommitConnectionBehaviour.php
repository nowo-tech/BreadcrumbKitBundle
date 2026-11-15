<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Throwable;

/**
 * The behaviour of the driver connections of {@see AfterCommitMiddleware}, shared by {@see AfterCommitConnection}
 * (doctrine/dbal 4) and {@see AfterCommitConnectionDbal3} (doctrine/dbal 3): they differ only in the return type of
 * `exec()`, which PHP cannot declare once for both.
 *
 * @internal
 */
trait AfterCommitConnectionBehaviour
{
    /** Savepoint statements: verb and name. */
    private const SAVEPOINT_PATTERN = '/^\s*(SAVEPOINT|SAVE\s+TRAN(?:SACTION)?|RELEASE\s+SAVEPOINT|ROLLBACK\s+TO\s+SAVEPOINT|ROLLBACK\s+TRAN(?:SACTION)?)\s+([A-Za-z0-9_]+)\s*;?\s*$/i';

    /** @var list<TransactionObserverInterface> */
    private readonly array $observers;

    /**
     * @param Connection                   $connection   wrapped driver connection
     * @param TransactionObserverInterface ...$observers told about the transactions, in this order
     */
    public function __construct(
        Connection $connection,
        TransactionObserverInterface ...$observers,
    ) {
        parent::__construct($connection);
        $this->observers = array_values($observers);
    }

    /**
     * Starts the transaction, then tells the observers.
     */
    public function beginTransaction(): void
    {
        parent::beginTransaction();
        $this->notify(static fn (TransactionObserverInterface $observer, self $connection) => $observer->transactionStarted($connection));
    }

    /**
     * Commits, then lets the observers apply what the transaction held (discard it when the commit fails).
     */
    public function commit(): void
    {
        try {
            parent::commit();
        } catch (Throwable $e) {
            try {
                $this->ended(false);
            } finally {
                // The commit failure wins over an observer failure.
                throw $e;
            }
        }
        $this->ended(true);
    }

    /**
     * Rolls back, then lets the observers discard what the transaction held.
     */
    public function rollBack(): void
    {
        try {
            parent::rollBack();
        } finally {
            $this->ended(false);
        }
    }

    private function ended(bool $committed): void
    {
        $this->notify(static fn (TransactionObserverInterface $observer, self $connection) => $observer->transactionEnded($connection, $committed));
    }

    /**
     * Tells every observer, each in isolation (as in InstanceSettingsBundle and LegalKitBundle): one that throws does
     * not keep the others from being told; the first exception is rethrown once all were notified.
     *
     * @param callable(TransactionObserverInterface, self): mixed $notification
     */
    private function notify(callable $notification): void
    {
        $failure = null;
        foreach ($this->observers as $observer) {
            try {
                $notification($observer, $this);
            } catch (Throwable $e) {
                $failure ??= $e;
            }
        }
        if ($failure instanceof Throwable) {
            throw $failure;
        }
    }

    /**
     * Reports a successful savepoint statement (issued through `exec()`) to the observers.
     */
    private function afterExec(string $sql): void
    {
        if (1 === preg_match(self::SAVEPOINT_PATTERN, $sql, $match)) {
            $verb = strtoupper($match[1]);
            $name = $match[2];
            $this->notify(static fn (TransactionObserverInterface $observer, self $connection) => match (true) {
                str_starts_with($verb, 'RELEASE') => $observer->savepointReleased($connection, $name),
                str_starts_with($verb, 'ROLLBACK') => $observer->savepointRolledBack($connection, $name),
                default => $observer->savepointCreated($connection, $name),
            });
        }
    }
}
