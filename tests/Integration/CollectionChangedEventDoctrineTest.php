<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbCollectionChangedEvent;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCacheInvalidator;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 2.16 (rules 4 and 7): one {@see BreadcrumbCollectionChangedEvent} per changed collection, with ids, after the commit
 * — real Doctrine (SQLite) flushes, the importer's transaction and the end of a request.
 */
final class CollectionChangedEventDoctrineTest extends TestCase
{
    private EntityManager $entityManager;
    private BreadcrumbCacheInvalidator $invalidator;

    /** @var list<array{event: BreadcrumbCollectionChangedEvent, inTransaction: bool}> */
    private array $events = [];

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 2).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }
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
        $this->invalidator = new BreadcrumbCacheInvalidator(null, $dispatcher);
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
        $this->entityManager->commit();
        $this->invalidator->dispatchPendingEvents();
        self::assertSame([[(int) $collection->getId(), 'main', '', false, false]], $this->summary());

        // A transaction that never commits: the events are dropped at the end of the request.
        $this->events = [];
        $this->entityManager->beginTransaction();
        $collection->setCode('renamed');
        $this->entityManager->flush();
        $this->entityManager->rollback();
        self::assertSame([KernelEvents::TERMINATE => ['reset', -1024]], BreadcrumbCacheInvalidator::getSubscribedEvents());
        $this->invalidator->reset();
        $this->invalidator->dispatchPendingEvents();
        self::assertSame([], $this->events);
    }

    public function testImporterDispatchesAfterItsCommit(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $importer = new BreadcrumbImporter(
            new BreadcrumbCollectionRepository($registry),
            new BreadcrumbItemRepository($registry),
            $this->entityManager,
            $translator,
            new NullLogger(),
            $this->invalidator,
        );

        $result = $importer->import([
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
        $invalidator->dispatchPendingEvents();
        self::assertCount(1, $this->events, 'only the dispatcher-backed invalidator reported it');
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
