<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Doctrine;

use Nowo\BreadcrumbKitBundle\Doctrine\BreadcrumbChangeOutbox;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbCollectionChangedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(BreadcrumbChangeOutbox::class)]
final class BreadcrumbChangeOutboxTest extends TestCase
{
    #[Test]
    public function withoutATransactionEventsAreCommittedAtOnce(): void
    {
        $outbox = new BreadcrumbChangeOutbox();
        $outbox->hold();
        $outbox->hold($event = new BreadcrumbCollectionChangedEvent(1, 'a', ''));

        self::assertSame(1, $outbox->countCommitted());
        self::assertSame([$event], $outbox->takeCommitted());
        self::assertSame(0, $outbox->countCommitted());
    }

    #[Test]
    public function savepointsCommitIntoTheirParentOrDropTheirEvents(): void
    {
        $outbox = new BreadcrumbChangeOutbox();
        $driver = new stdClass();
        $outbox->savepointCreated($driver, 'IGNORED'); // no transaction: ignored

        $outbox->transactionStarted($driver);
        $outbox->hold($outer = new BreadcrumbCollectionChangedEvent(2, 'outer', ''));
        $outbox->savepointCreated($driver, 'DOCTRINE_2');
        $outbox->hold($released = new BreadcrumbCollectionChangedEvent(3, 'released', ''));
        $outbox->savepointReleased($driver, 'doctrine_2');
        $outbox->savepointCreated($driver, 'DOCTRINE_2');
        $outbox->hold(new BreadcrumbCollectionChangedEvent(4, 'rolled-back', ''));
        $outbox->savepointCreated($driver, 'DOCTRINE_3');
        $outbox->hold(new BreadcrumbCollectionChangedEvent(5, 'inner-of-rolled-back', ''));
        $outbox->savepointRolledBack($driver, 'DOCTRINE_2');
        $outbox->savepointRolledBack($driver, 'UNKNOWN');
        self::assertSame(2, $outbox->countHeld());

        $outbox->transactionEnded($driver, true);
        self::assertSame(0, $outbox->countHeld());
        self::assertSame([$outer, $released], $outbox->takeCommitted());
    }

    #[Test]
    public function aReusedSavepointNameClosesTheOpenOneFirst(): void
    {
        $outbox = new BreadcrumbChangeOutbox();
        $driver = new stdClass();
        $outbox->transactionStarted($driver);
        $outbox->savepointCreated($driver, 'SP');
        $outbox->hold($first = new BreadcrumbCollectionChangedEvent(6, 'first', ''));
        $outbox->savepointCreated($driver, 'SP');
        $outbox->hold(new BreadcrumbCollectionChangedEvent(7, 'second', ''));
        $outbox->savepointRolledBack($driver, 'SP');
        $outbox->transactionEnded($driver, true);

        self::assertSame([$first], $outbox->takeCommitted());
    }

    #[Test]
    public function rollbackAndResetDropEverything(): void
    {
        $outbox = new BreadcrumbChangeOutbox();
        $driver = new stdClass();
        $outbox->transactionStarted($driver);
        $outbox->hold(new BreadcrumbCollectionChangedEvent(8, 'a', ''));
        $outbox->transactionEnded($driver, false);
        self::assertSame([], $outbox->takeCommitted());

        $outbox->hold(new BreadcrumbCollectionChangedEvent(9, 'b', ''));
        $outbox->transactionStarted($driver);
        $outbox->hold(new BreadcrumbCollectionChangedEvent(10, 'c', ''));
        $outbox->reset();
        self::assertSame(0, $outbox->countCommitted() + $outbox->countHeld());
        $outbox->transactionEnded($driver, true);
        self::assertSame([], $outbox->takeCommitted());
    }
}
