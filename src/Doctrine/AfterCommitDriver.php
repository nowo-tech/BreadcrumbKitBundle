<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Driver of {@see AfterCommitMiddleware}: wraps each connection in a {@see AfterCommitConnection}.
 *
 * @internal
 */
final class AfterCommitDriver extends AbstractDriverMiddleware
{
    /** @var list<TransactionObserverInterface> */
    private readonly array $observers;

    /**
     * @param Driver                       $driver       wrapped driver
     * @param TransactionObserverInterface ...$observers told about the transactions, in this order
     */
    public function __construct(
        Driver $driver,
        TransactionObserverInterface ...$observers,
    ) {
        parent::__construct($driver);
        $this->observers = array_values($observers);
    }

    /**
     * Connects and wraps the connection.
     */
    public function connect(array $params): Connection
    {
        $class = self::connectionClass();

        return new $class(parent::connect($params), ...$this->observers);
    }

    /**
     * The connection class for the installed doctrine/dbal: `exec()` returns `int` in doctrine/dbal 3 and `int|string`
     * in 4, and a subclass cannot declare both.
     *
     * @return class-string<AfterCommitConnection|AfterCommitConnectionDbal3>
     */
    private static function connectionClass(): string
    {
        $returnType = (new ReflectionMethod(AbstractConnectionMiddleware::class, 'exec'))->getReturnType();

        return $returnType instanceof ReflectionNamedType && 'int' === $returnType->getName() ? AfterCommitConnectionDbal3::class : AfterCommitConnection::class;
    }
}
