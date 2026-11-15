<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

/**
 * Driver connection of {@see AfterCommitMiddleware}: reports the real transaction and its savepoints to the
 * observers (the breadcrumb change outbox; DBAL nests transactions with savepoints issued as SQL through
 * {@see self::exec()}: `SAVEPOINT`, `RELEASE SAVEPOINT`, `ROLLBACK TO SAVEPOINT`, and SQL Server's `SAVE TRANSACTION` /
 * `ROLLBACK TRANSACTION`). A commit that fails counts as a rollback.
 *
 * The doctrine/dbal 3 form ({@see AfterCommitConnection} is the doctrine/dbal 4 one); only loaded on doctrine/dbal 3.
 *
 * @internal
 */
final class AfterCommitConnectionDbal3 extends AbstractConnectionMiddleware
{
    use AfterCommitConnectionBehaviour;

    /**
     * Executes the statement; a successful savepoint statement is reported to the observers.
     */
    public function exec(string $sql): int
    {
        $result = parent::exec($sql);
        $this->afterExec($sql);

        return $result;
    }
}
