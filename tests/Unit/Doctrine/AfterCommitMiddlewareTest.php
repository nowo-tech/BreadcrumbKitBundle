<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\DriverManager;
use Nowo\BreadcrumbKitBundle\Doctrine\AfterCommitConnection;
use Nowo\BreadcrumbKitBundle\Doctrine\AfterCommitConnectionDbal3;
use Nowo\BreadcrumbKitBundle\Doctrine\AfterCommitMiddleware;
use Nowo\BreadcrumbKitBundle\Doctrine\TransactionObserverInterface;
use Nowo\BreadcrumbKitBundle\Tests\Support\NestWithSavepoints;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * The DBAL middleware reports the real transaction and its savepoints to its observers (1.10).
 */
final class AfterCommitMiddlewareTest extends TestCase
{
    public function testRealTransactionsAndSavepointsAreReported(): void
    {
        $observer = $this->observer();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], (new Configuration())->setMiddlewares([new AfterCommitMiddleware($observer)]));
        NestWithSavepoints::enable($connection);

        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->rollBack();
        $connection->beginTransaction();
        $connection->commit();
        $connection->commit();
        $connection->beginTransaction();
        $connection->rollBack();

        self::assertSame([
            'started',
            'savepoint DOCTRINE_2',
            'rolled back to DOCTRINE_2',
            'savepoint DOCTRINE_2',
            'released DOCTRINE_2',
            'ended committed',
            'started',
            'ended rolled back',
        ], $observer->log);
    }

    public function testSqlServerSavepointStatementsAndAFailedCommit(): void
    {
        $observer = $this->observer();
        $driver = $this->createStub(Connection::class);
        $driver->method('exec')->willReturn(0);
        $driver->method('commit')->willThrowException(new RuntimeException('deadlock'));
        $connection = $this->connection($driver, $observer);

        $connection->beginTransaction();
        self::assertSame(0, $connection->exec('SAVE TRANSACTION DOCTRINE_2'));
        $connection->exec('ROLLBACK TRANSACTION DOCTRINE_2');
        $connection->exec('SELECT 1');
        try {
            $connection->commit();
            self::fail('Expected the commit failure.');
        } catch (RuntimeException $e) {
            self::assertSame('deadlock', $e->getMessage());
        }

        self::assertSame(['started', 'savepoint DOCTRINE_2', 'rolled back to DOCTRINE_2', 'ended rolled back'], $observer->log);
    }

    public function testAThrowingObserverDoesNotHideACommitFailure(): void
    {
        $failing = new class implements TransactionObserverInterface {
            public function transactionStarted(object $connection): void
            {
            }

            public function transactionEnded(object $connection, bool $committed): void
            {
                throw new RuntimeException('observer');
            }

            public function savepointCreated(object $connection, string $name): void
            {
            }

            public function savepointReleased(object $connection, string $name): void
            {
            }

            public function savepointRolledBack(object $connection, string $name): void
            {
            }
        };
        $driver = $this->createStub(Connection::class);
        $driver->method('commit')->willThrowException(new RuntimeException('deadlock'));
        $connection = $this->connection($driver, $failing);

        $this->expectExceptionMessage('deadlock');
        $connection->commit();
    }

    /**
     * The driver connection the middleware builds for the installed doctrine/dbal (`exec()` returns `int` on 3).
     */
    private function connection(Connection $wrapped, TransactionObserverInterface $observer): Connection
    {
        $class = 'int' === (string) (new ReflectionMethod(AbstractConnectionMiddleware::class, 'exec'))->getReturnType() ? AfterCommitConnectionDbal3::class : AfterCommitConnection::class;

        return new $class($wrapped, $observer);
    }

    /**
     * @return object{log: list<string>}&TransactionObserverInterface
     */
    private function observer(): TransactionObserverInterface
    {
        return new class implements TransactionObserverInterface {
            /** @var list<string> */
            public array $log = [];

            public function transactionStarted(object $connection): void
            {
                $this->log[] = 'started';
            }

            public function transactionEnded(object $connection, bool $committed): void
            {
                $this->log[] = $committed ? 'ended committed' : 'ended rolled back';
            }

            public function savepointCreated(object $connection, string $name): void
            {
                $this->log[] = 'savepoint '.$name;
            }

            public function savepointReleased(object $connection, string $name): void
            {
                $this->log[] = 'released '.$name;
            }

            public function savepointRolledBack(object $connection, string $name): void
            {
                $this->log[] = 'rolled back to '.$name;
            }
        };
    }
}
