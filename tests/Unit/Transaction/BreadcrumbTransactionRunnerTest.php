<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Transaction;

use ArrayObject;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbCollectionChangedEvent;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel\Entity\HostNote;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbFlushObserver;
use Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbTransactionRunner;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

/**
 * 2.20 (Beacon spec 239): the runner of every bundle write, on the shared (host) manager and on an own manager.
 */
final class BreadcrumbTransactionRunnerTest extends TestCase
{
    public function testARunCommitsFlushesAndReleasesTheEventsAfterTheCommit(): void
    {
        /** @var ArrayObject<int, mixed> $events */
        $events = new ArrayObject();
        $dispatcher = new EventDispatcher();
        $store = new BreadcrumbStore(dispatcher: $dispatcher);
        $dispatcher->addListener(BreadcrumbCollectionChangedEvent::class, static function (BreadcrumbCollectionChangedEvent $event) use ($events, $store): void {
            $events[] = [$event->collectionCode, $store->connection()->isTransactionActive()];
        });

        $store->runner->run(static function () use ($store): void {
            $store->entityManager->persist((new BreadcrumbCollection())->setCode('main'));
        });

        self::assertSame(0, $store->connection()->getTransactionNestingLevel());
        self::assertSame([['main', false]], $events->getArrayCopy(), 'dispatched once, after the commit');
        self::assertSame('1', (string) $store->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
    }

    public function testNothingIsReleasedInsideATransactionOfTheApplication(): void
    {
        /** @var ArrayObject<int, mixed> $events */
        $events = new ArrayObject();
        $dispatcher = new EventDispatcher();
        $store = new BreadcrumbStore(dispatcher: $dispatcher);
        $dispatcher->addListener(BreadcrumbCollectionChangedEvent::class, static function () use ($events): void {
            $events[] = true;
        });

        $store->connection()->beginTransaction();
        $store->runner->run(static function () use ($store): void {
            $store->entityManager->persist((new BreadcrumbCollection())->setCode('main'));
        });
        self::assertSame([], $events->getArrayCopy(), 'the application has not committed yet');
        $store->connection()->rollBack();
        $store->invalidator->onBoundary();
        self::assertSame([], $events->getArrayCopy(), 'a rolled-back write is never announced');
    }

    public function testAFailedUnitForgetsItsBreadcrumbRowsAndKeepsTheApplicationEntities(): void
    {
        $store = new BreadcrumbStore();
        $note = new HostNote('pending');
        $store->entityManager->persist($note);
        $store->entityManager->flush();
        $note->text = 'edited, not flushed';
        $old = (new BreadcrumbCollection())->setCode('old');
        $other = (new BreadcrumbCollection())->setCode('other');
        $otherItem = (new BreadcrumbItem())->setCollection($other)->setRouteName('keep');
        $removed = (new BreadcrumbItem())->setCollection($old)->setRouteName('removed');
        $store->entityManager->persist($old);
        $store->entityManager->persist($other);
        $store->entityManager->persist($otherItem);
        $store->entityManager->persist($removed);
        $store->entityManager->flush();

        $scheduled = new HostNote('scheduled');
        $failure = $this->failureOf(static fn (): mixed => $store->runner->run(static function () use ($store, $old, $removed, $scheduled): void {
            $store->entityManager->persist($scheduled);
            $new = (new BreadcrumbCollection())->setCode('new');
            $store->entityManager->persist($new);
            $store->entityManager->persist((new BreadcrumbItem())->setCollection($new)->setRouteName('child'));
            $old->setName('renamed');
            $store->entityManager->remove($removed);

            throw new RuntimeException('refused');
        }));
        self::assertSame('refused', $failure->getMessage());

        self::assertTrue($store->entityManager->isOpen());
        self::assertSame(0, $store->connection()->getTransactionNestingLevel());
        self::assertTrue($store->entityManager->contains($note), 'the application entity stays managed');
        self::assertSame('edited, not flushed', $note->text, 'and keeps its pending change');
        self::assertTrue($store->entityManager->contains($scheduled), 'an application entity scheduled by the unit is not the bundle\'s to forget');
        self::assertTrue($store->entityManager->contains($otherItem), 'a row the unit did not touch stays managed');
        self::assertFalse($store->entityManager->contains($old), 'a row the unit changed is forgotten');
        self::assertFalse($store->entityManager->contains($removed), 'a row the unit scheduled for deletion is forgotten');
        $store->entityManager->flush();
        self::assertSame('2', (string) $store->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'), 'the failed unit wrote nothing, and its deletion is not carried out later');
        self::assertSame('2', (string) $store->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item'));
        self::assertSame('removed', (string) $store->connection()->fetchOne("SELECT routeName FROM dashboard_breadcrumb_item WHERE routeName = 'removed'"));
    }

    public function testAKeptRowThatPointsAtAForgottenOneGoesToo(): void
    {
        $store = new BreadcrumbStore();
        $collection = (new BreadcrumbCollection())->setCode('main');
        $item = (new BreadcrumbItem())->setCollection($collection)->setRouteName('home');
        $store->entityManager->persist($collection);
        $store->entityManager->persist($item);
        $store->entityManager->flush();

        $this->failureOf(static fn (): mixed => $store->runner->run(static function () use ($collection): void {
            $collection->setName('renamed');

            throw new RuntimeException('refused');
        }));

        self::assertFalse($store->entityManager->contains($collection));
        self::assertFalse($store->entityManager->contains($item), 'it points at the forgotten collection: a later flush would drag it back');
    }

    public function testAFailedUnitThatFlushedTheApplicationsChangesClosesTheManager(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
        $store = new BreadcrumbStore();
        $runner = new BreadcrumbTransactionRunner($store->entityManager, null, null, 1, 0, null, $logger);
        $note = new HostNote('pending');
        $store->entityManager->persist($note);

        $this->failureOf(static fn (): mixed => $runner->run(static function () use ($store): void {
            $store->entityManager->flush();

            throw new RuntimeException('later failure');
        }));

        self::assertFalse($store->entityManager->isOpen(), 'the rolled-back host row would otherwise stay managed as written (lost silently)');
        self::assertCount(1, $logger->messages);
        $refusal = $this->failureOf(static fn (): mixed => $runner->run(static fn (): bool => true));
        self::assertInstanceOf(TransientStorageFailure::class, $refusal);
        self::assertSame(1, $refusal->retryAfterSeconds());
    }

    public function testAFailedFlushLeavesTheManagerClosedAndTheNextUnitIsRefused(): void
    {
        $store = new BreadcrumbStore();
        $store->entityManager->persist((new BreadcrumbCollection())->setCode('main'));
        $store->entityManager->flush();

        self::assertInstanceOf(UniqueConstraintViolationException::class, $this->failureOf(static fn (): mixed => $store->runner->run(static function () use ($store): BreadcrumbCollection {
            $duplicate = (new BreadcrumbCollection())->setCode('main');
            $store->entityManager->persist($duplicate);

            return $duplicate;
        })));

        self::assertFalse($store->entityManager->isOpen());
        self::assertSame(0, $store->connection()->getTransactionNestingLevel());
        $this->expectException(TransientStorageFailure::class);
        $store->runner->run(static fn (): bool => true);
    }

    public function testASharedManagerIsNeverReplacedByTheRunner(): void
    {
        $store = new BreadcrumbStore();
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getDefaultManagerName')->willReturn('default');
        $registry->method('getManager')->willReturn($store->entityManager);
        $registry->expects(self::never())->method('resetManager');
        $resolver = new BreadcrumbEntityManagerResetter($registry, 'default');
        $runner = new BreadcrumbTransactionRunner($resolver, $registry, 'default');

        self::assertSame($store->entityManager, $runner->manager());
        $store->entityManager->close();
        self::assertSame($store->entityManager, $runner->manager(), 'a closed shared manager is not touched');
    }

    public function testAnOwnManagerIsDrivenByTheOrmRunnerAndReplacedAfterAStorageFailure(): void
    {
        $store = new BreadcrumbStore();
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getDefaultManagerName')->willReturn('default');
        $registry->method('getManager')->with('breadcrumb')->willReturn($store->entityManager);
        $registry->expects(self::once())->method('resetManager')->with('breadcrumb');
        $resolver = new BreadcrumbEntityManagerResetter($registry, 'breadcrumb');
        $runner = new BreadcrumbTransactionRunner($resolver, $registry, 'breadcrumb', 1, 0, $store->invalidator);

        $runner->run(static fn (): bool => true);
        $store->entityManager->persist((new BreadcrumbCollection())->setCode('main'));
        $store->entityManager->flush();

        self::assertInstanceOf(UniqueConstraintViolationException::class, $this->failureOf(static fn (): mixed => $runner->run(static function () use ($store): BreadcrumbCollection {
            $duplicate = (new BreadcrumbCollection())->setCode('main');
            $store->entityManager->persist($duplicate);

            return $duplicate;
        })));
        self::assertSame(0, $store->connection()->getTransactionNestingLevel());
    }

    public function testAnOwnManagerThatWasClosedIsReplacedBeforeTheUnit(): void
    {
        $store = new BreadcrumbStore();
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getDefaultManagerName')->willReturn('default');
        $registry->method('getManager')->with('breadcrumb')->willReturn($store->entityManager);
        $registry->expects(self::once())->method('resetManager')->with('breadcrumb');
        $runner = new BreadcrumbTransactionRunner(new BreadcrumbEntityManagerResetter($registry, 'breadcrumb'), $registry, 'breadcrumb');
        $store->entityManager->close();

        $runner->manager();
    }

    public function testRunRetryingReplaysATransientFailureOfTheOutermostTransaction(): void
    {
        $store = new BreadcrumbStore();
        $attempts = new ArrayObject(['n' => 0]);

        $store->runner->runRetrying(static function () use ($attempts): void {
            if (++$attempts['n'] < 3) {
                throw new TransientStorageFailure(1, 'deadlock');
            }
        });

        self::assertSame(3, $attempts['n']);
    }

    public function testRunRetryingGivesUpAfterTheLastAttempt(): void
    {
        $store = new BreadcrumbStore();
        $attempts = new ArrayObject(['n' => 0]);

        self::assertInstanceOf(TransientStorageFailure::class, $this->failureOf(static fn (): mixed => $store->runner->runRetrying(static function () use ($attempts): void {
            ++$attempts['n'];

            throw new TransientStorageFailure(1, 'deadlock');
        }, 2)));
        self::assertSame(2, $attempts['n']);
    }

    public function testRunRetryingDoesNotReplayInsideATransactionOfTheApplication(): void
    {
        $store = new BreadcrumbStore();
        $attempts = new ArrayObject(['n' => 0]);
        $store->connection()->beginTransaction();

        $failure = $this->failureOf(static fn (): mixed => $store->runner->runRetrying(static function () use ($attempts): void {
            ++$attempts['n'];

            throw new TransientStorageFailure(1, 'deadlock');
        }));
        self::assertInstanceOf(TransientStorageFailure::class, $failure);
        self::assertSame(1, $attempts['n']);
        self::assertSame(1, $store->connection()->getTransactionNestingLevel(), 'the application\'s own transaction is left to it');
    }

    public function testTheBackoffWaitsBetweenAttempts(): void
    {
        $store = new BreadcrumbStore();
        $runner = new BreadcrumbTransactionRunner($store->entityManager, null, null, 1, 2);
        $start = microtime(true);
        $count = 0;

        $runner->runRetrying(static function () use (&$count): void {
            if (++$count < 2) {
                throw new TransientStorageFailure(1, 'deadlock');
            }
        });

        self::assertGreaterThanOrEqual(0.0, microtime(true) - $start);
        self::assertSame(2, $count);
    }

    public function testTheObserverIsRemovedAfterTheUnit(): void
    {
        $store = new BreadcrumbStore();
        $store->runner->run(static fn (): bool => true);

        self::assertFalse($store->entityManager->getEventManager()->hasListeners(Events::onFlush) && [] !== array_filter(
            $store->entityManager->getEventManager()->getListeners(Events::onFlush),
            static fn (object $listener): bool => $listener instanceof BreadcrumbFlushObserver,
        ));
    }

    /**
     * The failure of an operation (the runner's closures that always throw make the analyser treat the code after them as
     * unreachable, so the tests read the failure through this helper).
     */
    private function failureOf(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $failure) {
            return $failure;
        }

        self::fail('The operation must fail.');
    }
}
