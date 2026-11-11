<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Doctrine\AfterCommitMiddleware;
use Nowo\BreadcrumbKitBundle\Doctrine\BreadcrumbChangeOutbox;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbCollectionChangedEvent;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCacheInvalidator;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 2.16 (rules 4 and 7): one {@see BreadcrumbCollectionChangedEvent} per changed collection, with ids, after the commit
 * — real Doctrine (SQLite) flushes, the importer's transaction and the end of a request.
 */
final class CollectionChangedEventDoctrineTest extends TestCase
{
    private EntityManager $entityManager;
    private BreadcrumbCacheInvalidator $invalidator;
    private BreadcrumbChangeOutbox $outbox;

    /** @var list<array{event: BreadcrumbCollectionChangedEvent, inTransaction: bool}> */
    private array $events = [];

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 2).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }
        // The DBAL middleware tells the outbox about the real transactions (LI-BC-M1).
        $this->outbox = new BreadcrumbChangeOutbox();
        $configuration->setMiddlewares([new AfterCommitMiddleware($this->outbox)]);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $eventManager = new EventManager();
        $this->entityManager = new EntityManager($connection, $configuration, $eventManager);
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(BreadcrumbCollection::class),
            $this->entityManager->getClassMetadata(BreadcrumbItem::class),
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BreadcrumbCollectionChangedEvent::class, function (BreadcrumbCollectionChangedEvent $event): void {
            $this->events[] = ['event' => $event, 'inTransaction' => $this->entityManager->getConnection()->isTransactionActive()];
        });
        $this->invalidator = new BreadcrumbCacheInvalidator(null, $dispatcher, $this->outbox);
        $eventManager->addEventListener([Events::onFlush, Events::postFlush], $this->invalidator);
    }

    public function testCreateUpdateAndDeleteDispatchIdsAfterTheFlush(): void
    {
        $collection = (new BreadcrumbCollection())->setCode('main');
        $this->entityManager->persist($collection);
        $this->entityManager->flush();
        $id = (int) $collection->getId();
        self::assertSame([[$id, 'main', '', false, false]], $this->summary(), 'created collection (id known after the insert)');

        $item = (new BreadcrumbItem())->setCollection($collection)->setRouteName('app_home')->setLabel('Home');
        $this->entityManager->persist($item);
        $this->entityManager->flush();
        $item->setLabel('Start');
        $this->entityManager->flush();
        self::assertSame([[$id, 'main', '', false, false], [$id, 'main', '', false, false], [$id, 'main', '', false, false]], $this->summary());

        $this->events = [];
        $this->entityManager->remove($item);
        $this->entityManager->remove($collection);
        $this->entityManager->flush();
        self::assertSame([[$id, 'main', '', true, false]], $this->summary(), 'one event, flagged deleted');
    }

    public function testInsideATransactionTheEventsWaitForTheCommit(): void
    {
        $this->entityManager->beginTransaction();
        $collection = (new BreadcrumbCollection())->setCode('main');
        $this->entityManager->persist($collection);
        $this->entityManager->flush();
        self::assertSame([], $this->events);
        self::assertSame(0, $this->invalidator->dispatchPending(), 'never inside the transaction');
        $this->entityManager->commit();

        // The host commit made it committed; the next safe point dispatches it (here: the end of the request).
        self::assertSame([], $this->events);
        $this->invalidator->onBoundary();
        self::assertSame([[(int) $collection->getId(), 'main', '', false, false]], $this->summary());
    }

    /**
     * LI-BC-M1: a rolled-back flush is never announced, not even by the next flush outside the transaction.
     */
    public function testRolledBackChangesAreDropped(): void
    {
        $committed = $this->persistCollection('committed');
        $this->events = [];

        $this->entityManager->beginTransaction();
        $this->entityManager->persist((new BreadcrumbCollection())->setCode('rolled-back'));
        $this->entityManager->flush();
        $this->entityManager->rollback();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(BreadcrumbCollection::class, $committed->getId());
        self::assertInstanceOf(BreadcrumbCollection::class, $reloaded);
        $reloaded->setCode('committed-renamed');
        $this->entityManager->flush();

        self::assertSame([[(int) $committed->getId(), 'committed-renamed', '', false, false]], $this->summary());
        $this->invalidator->onBoundary();
        self::assertSame(1, $this->eventCount());
    }

    public function testHostCommitDispatchesAtTheNextFlushOrOnRequest(): void
    {
        $this->entityManager->beginTransaction();
        $first = $this->persistCollection('first');
        $this->entityManager->commit();
        self::assertSame([], $this->events);

        // Any later flush outside a transaction is a safe point; so is an explicit call after the host commit.
        $second = $this->persistCollection('second');
        self::assertSame([[(int) $first->getId(), 'first'], [(int) $second->getId(), 'second']], array_map(static fn (array $r): array => [$r[0], $r[1]], $this->summary()));

        $this->events = [];
        $this->entityManager->beginTransaction();
        $third = $this->persistCollection('third');
        $this->entityManager->commit();
        self::assertSame(1, $this->invalidator->dispatchPending());
        self::assertSame([[(int) $third->getId(), 'third', '', false, false]], $this->summary());
    }

    public function testSavepointRollbackDropsOnlyItsChanges(): void
    {
        $this->entityManager->beginTransaction();
        $kept = $this->persistCollection('kept');
        $this->entityManager->beginTransaction();
        $this->entityManager->persist((new BreadcrumbCollection())->setCode('nested'));
        $this->entityManager->flush();
        $this->entityManager->rollback();
        $this->entityManager->commit();
        $this->invalidator->onBoundary();

        self::assertSame([[(int) $kept->getId(), 'kept', '', false, false]], $this->summary());
    }

    public function testCollectionsOfAFlushThatThrewAreNotReplayed(): void
    {
        $this->persistCollection('taken');
        $this->events = [];

        $this->entityManager->persist((new BreadcrumbCollection())->setCode('taken'));
        try {
            $this->entityManager->flush();
            self::fail('unique constraint expected');
        } catch (UniqueConstraintViolationException) {
        }

        // A fresh manager (the failed flush closed it) on the same connection and listener.
        $eventManager = $this->entityManager->getEventManager();
        self::assertInstanceOf(EventManager::class, $eventManager);
        $fresh = new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration(), $eventManager);
        $other = (new BreadcrumbCollection())->setCode('other');
        $fresh->persist($other);
        $fresh->flush();

        self::assertSame([[(int) $other->getId(), 'other', '', false, false]], $this->summary());
        $this->invalidator->onBoundary();
        self::assertSame(1, $this->eventCount(), 'nothing left over from the failed flush');
    }

    public function testResetDropsHeldAndCommittedEvents(): void
    {
        $this->entityManager->beginTransaction();
        $this->persistCollection('pending');
        $this->entityManager->commit();
        $this->invalidator->reset();
        $this->invalidator->onBoundary();

        self::assertSame([], $this->events);
        self::assertSame(0, $this->outbox->countHeld() + $this->outbox->countCommitted());
        self::assertSame(['onBoundary', -1024], BreadcrumbCacheInvalidator::getSubscribedEvents()['kernel.terminate']);
    }

    public function testListenerFailureAfterTheCommitIsLoggedAndTheOthersStillRun(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = [];
        $dispatcher->addListener(BreadcrumbCollectionChangedEvent::class, static function (BreadcrumbCollectionChangedEvent $event) use (&$seen): void {
            $seen[] = $event->collectionCode;
            if ('boom' === $event->collectionCode) {
                throw new RuntimeException('listener failed');
            }
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Breadcrumb Kit: a listener failed after the commit.');
        $outbox = new BreadcrumbChangeOutbox();
        $invalidator = new BreadcrumbCacheInvalidator(null, $dispatcher, $outbox, null, $logger);

        $outbox->hold(new BreadcrumbCollectionChangedEvent(1, 'boom', ''), new BreadcrumbCollectionChangedEvent(2, 'fine', ''), new BreadcrumbCollectionChangedEvent(1, 'boom', '', true));
        $active = $this->createStub(Connection::class);
        $active->method('isTransactionActive')->willReturn(true);
        self::assertSame(0, $invalidator->dispatchPending($active), 'not while DBAL reports a transaction');

        self::assertSame(2, $invalidator->dispatchPending(), 'one event per collection');
        self::assertSame(['boom', 'fine'], $seen);
    }

    public function testImporterInsideAHostTransactionWaitsForTheHostCommit(): void
    {
        $this->entityManager->beginTransaction();
        $this->importer()->import(['collection' => ['code' => 'nested-import'], 'items' => [['routeName' => 'app_home', 'label' => 'Home']]]);
        self::assertSame([], $this->events, 'the importer commit is a savepoint release inside the host transaction');
        $this->entityManager->rollback();
        $this->invalidator->onBoundary();
        self::assertSame([], $this->events, 'the host rolled back');
    }

    public function testImporterDispatchesAfterItsCommit(): void
    {
        $result = $this->importer()->import([
            'collection' => ['code' => 'imported'],
            'items' => [['routeName' => 'app_home', 'label' => 'Home']],
        ]);

        self::assertSame(1, $result['created']);
        self::assertNotSame([], $this->events);
        foreach ($this->events as $record) {
            self::assertFalse($record['inTransaction'], 'dispatched after the importer commit');
            self::assertSame('imported', $record['event']->collectionCode);
        }
    }

    public function testWithoutDispatcherOrPoolNothingIsCollected(): void
    {
        $invalidator = new BreadcrumbCacheInvalidator();
        $this->entityManager->getEventManager()->addEventListener([Events::onFlush, Events::postFlush], $invalidator);
        $this->entityManager->persist((new BreadcrumbCollection())->setCode('quiet'));
        $this->entityManager->flush();
        $invalidator->dispatchPending();
        self::assertCount(1, $this->events, 'only the dispatcher-backed invalidator reported it');
    }

    private function importer(): BreadcrumbImporter
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new BreadcrumbImporter(
            new BreadcrumbCollectionRepository($registry),
            new BreadcrumbItemRepository($registry),
            $this->entityManager,
            $translator,
            new NullLogger(),
            $this->invalidator,
        );
    }

    private function eventCount(): int
    {
        return \count($this->events);
    }

    private function persistCollection(string $code): BreadcrumbCollection
    {
        $collection = (new BreadcrumbCollection())->setCode($code);
        $this->entityManager->persist($collection);
        $this->entityManager->flush();

        return $collection;
    }

    /**
     * @return list<array{int, string, string, bool, bool}>
     */
    private function summary(): array
    {
        return array_map(static fn (array $record): array => [
            $record['event']->collectionId,
            $record['event']->collectionCode,
            $record['event']->contextKey,
            $record['event']->deleted,
            $record['inTransaction'],
        ], $this->events);
    }
}
