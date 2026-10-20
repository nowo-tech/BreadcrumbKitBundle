<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCacheInvalidator;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Real Doctrine (SQLite) flushes must drop the cached item list of the affected collections, and a
 * "replace" import must be atomic.
 */
final class CacheInvalidationDoctrineTest extends TestCase
{
    private EntityManager $entityManager;
    private ArrayAdapter $pool;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 2).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $this->eventManager = new EventManager();
        $this->entityManager = new EntityManager($connection, $configuration, $this->eventManager);
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(BreadcrumbCollection::class),
            $this->entityManager->getClassMetadata(BreadcrumbItem::class),
        ]);

        $this->pool = new ArrayAdapter();
        $this->eventManager->addEventListener([Events::onFlush, Events::postFlush], new BreadcrumbCacheInvalidator($this->pool));
    }

    public function testItemInsertUpdateAndDeleteInvalidateTheCollectionCache(): void
    {
        $collection = $this->persistCollection('main');
        $item = $this->persistItem($collection, 'app_home', 'Home');

        $this->prime($collection);
        $item->setLabel('Start');
        $this->entityManager->flush();
        self::assertFalse($this->isCached($collection), 'item update');

        $this->prime($collection);
        $this->persistItem($collection, 'app_about', 'About');
        self::assertFalse($this->isCached($collection), 'item insert');

        $this->prime($collection);
        $this->entityManager->remove($item);
        $this->entityManager->flush();
        self::assertFalse($this->isCached($collection), 'item delete');
    }

    public function testCollectionUpdateAndDeleteInvalidateItsCache(): void
    {
        $collection = $this->persistCollection('main');
        $other = $this->persistCollection('other');

        $this->prime($collection);
        $this->prime($other);
        $collection->setName('Renamed');
        $this->entityManager->flush();
        self::assertFalse($this->isCached($collection));
        self::assertTrue($this->isCached($other), 'untouched collections keep their cache');

        $id = (int) $collection->getId();
        $this->prime($collection);
        $this->entityManager->remove($collection);
        $this->entityManager->flush();
        self::assertFalse($this->pool->getItem(BreadcrumbLoader::cacheKey($id))->isHit());
    }

    public function testMovingAnItemInvalidatesBothCollections(): void
    {
        $from = $this->persistCollection('from');
        $to = $this->persistCollection('to');
        $item = $this->persistItem($from, 'app_home', 'Home');

        $this->prime($from);
        $this->prime($to);
        $item->setCollection($to);
        $this->entityManager->flush();

        self::assertFalse($this->isCached($from));
        self::assertFalse($this->isCached($to));
    }

    public function testReplaceImportInvalidatesCache(): void
    {
        $collection = $this->persistCollection('main');
        $this->persistItem($collection, 'app_old', 'Old');
        $this->prime($collection);

        $result = $this->importer()->import([
            'collection' => ['code' => 'main'],
            'items' => [['routeName' => 'app_new', 'label' => 'New']],
        ], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame(1, $result['updated']);
        self::assertFalse($this->isCached($collection));
    }

    public function testFailedReplaceImportRollsBackDeletedItems(): void
    {
        $collection = $this->persistCollection('main');
        $this->persistItem($collection, 'app_old', 'Old');
        $this->entityManager->clear();

        $this->eventManager->addEventListener(Events::prePersist, new class {
            public function prePersist(PrePersistEventArgs $args): void
            {
                $entity = $args->getObject();
                if ($entity instanceof BreadcrumbItem && 'app_boom' === $entity->getRouteName()) {
                    throw new RuntimeException('boom');
                }
            }
        });

        try {
            $this->importer()->import([
                'collection' => ['code' => 'main'],
                'items' => [['routeName' => 'app_boom']],
            ], BreadcrumbImporter::STRATEGY_REPLACE);
            self::fail('The import must fail.');
        } catch (RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
            self::assertFalse($this->entityManager->isOpen());
        }

        $routes = $this->entityManager->getConnection()->fetchFirstColumn('SELECT routeName FROM dashboard_breadcrumb_item');
        self::assertSame(['app_old'], $routes, 'old items must survive a failed replace import');
    }

    public function testNoPoolIsANoOp(): void
    {
        $invalidator = new BreadcrumbCacheInvalidator();
        $this->eventManager->addEventListener([Events::onFlush, Events::postFlush], $invalidator);

        $this->persistCollection('main');
        $invalidator->invalidateCollection(1);
        $invalidator->reset();

        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
    }

    private function importer(): BreadcrumbImporter
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new BreadcrumbImporter(
            new BreadcrumbCollectionRepository($registry),
            new BreadcrumbItemRepository($registry),
            $this->entityManager,
            $translator,
        );
    }

    private function persistCollection(string $code): BreadcrumbCollection
    {
        $collection = (new BreadcrumbCollection())->setCode($code);
        $this->entityManager->persist($collection);
        $this->entityManager->flush();

        return $collection;
    }

    private function persistItem(BreadcrumbCollection $collection, string $route, string $label): BreadcrumbItem
    {
        $item = new BreadcrumbItem();
        $item->setCollection($collection);
        $item->setRouteName($route);
        $item->setLabel($label);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    private function prime(BreadcrumbCollection $collection): void
    {
        $item = $this->pool->getItem(BreadcrumbLoader::cacheKey((int) $collection->getId()));
        $this->pool->save($item->set([['stale' => true]]));
        self::assertTrue($this->isCached($collection));
    }

    private function isCached(BreadcrumbCollection $collection): bool
    {
        return $this->pool->getItem(BreadcrumbLoader::cacheKey((int) $collection->getId()))->isHit();
    }
}
