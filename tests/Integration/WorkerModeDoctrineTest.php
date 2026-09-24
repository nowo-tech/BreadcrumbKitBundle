<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use PHPUnit\Framework\TestCase;

/**
 * Consecutive requests on the same EntityManager with no kernel reset (FrankenPHP worker mode, scenario B).
 */
final class WorkerModeDoctrineTest extends TestCase
{
    private Configuration $configuration;
    private Connection $connection;
    private WorkerModeManagerRegistry $registry;

    protected function setUp(): void
    {
        $this->configuration = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 2).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $this->configuration->enableNativeLazyObjects(true);
        }
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $this->configuration);
        $this->registry = new WorkerModeManagerRegistry(
            fn (): EntityManager => new EntityManager($this->connection, $this->configuration),
        );

        $entityManager = $this->registry->current();
        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(BreadcrumbCollection::class),
            $entityManager->getClassMetadata(BreadcrumbItem::class),
        ]);
    }

    public function testEditsFromAnotherWorkerAreVisibleOnTheNextRequest(): void
    {
        $collection = (new BreadcrumbCollection())->setCode('main');
        $collection->setName('Before');
        $item = new BreadcrumbItem();
        $item->setCollection($collection);
        $item->setRouteName('app_home');
        $item->setLabel('Home');
        $this->registry->current()->persist($collection);
        $this->registry->current()->persist($item);
        $this->registry->current()->flush();

        $collections = new BreadcrumbCollectionRepository($this->registry);
        $items = new BreadcrumbItemRepository($this->registry);

        $loaded = $collections->findOneByCodeAndContextKey('main');
        self::assertInstanceOf(BreadcrumbCollection::class, $loaded);
        self::assertSame('Before', $loaded->getName());
        self::assertSame('Home', $items->findAllForCollection($loaded)[0]->getLabel());

        $this->connection->executeStatement("UPDATE dashboard_breadcrumb_collection SET name = 'After'");
        $this->connection->executeStatement("UPDATE dashboard_breadcrumb_item SET label = 'Start'");

        $reloaded = $collections->findOneByCodeAndContextKey('main');
        self::assertInstanceOf(BreadcrumbCollection::class, $reloaded);
        self::assertSame('After', $reloaded->getName());
        self::assertSame('Start', $items->findAllForCollection($reloaded)[0]->getLabel());
        self::assertNull($collections->findOneByCodeAndContextKey('main', 'missing'));
    }

    public function testCaughtUniqueViolationDoesNotLeaveTheManagerClosedForTheNextRequest(): void
    {
        $this->registry->current()->persist((new BreadcrumbCollection())->setCode('dup'));
        $this->registry->current()->flush();

        $first = $this->registry->current();
        $first->persist((new BreadcrumbCollection())->setCode('dup'));

        try {
            $first->flush();
            self::fail('A duplicate code/context must violate the unique constraint.');
        } catch (UniqueConstraintViolationException) {
        }
        self::assertFalse($first->isOpen());

        $resetter = new BreadcrumbEntityManagerResetter($this->registry);
        $resetter->resetIfClosed();

        $next = $this->registry->current();
        self::assertNotSame($first, $next);
        self::assertTrue($next->isOpen());

        $next->persist((new BreadcrumbCollection())->setCode('other'));
        $next->flush();
        self::assertSame(2, (new BreadcrumbCollectionRepository($this->registry))->count([]));

        $resetter->resetIfClosed();
        self::assertSame($next, $this->registry->current());
    }
}

final class WorkerModeManagerRegistry implements ManagerRegistry
{
    private EntityManager $entityManager;

    /**
     * @param \Closure(): EntityManager $factory
     */
    public function __construct(private readonly \Closure $factory)
    {
        $this->entityManager = ($this->factory)();
    }

    public function current(): EntityManager
    {
        return $this->entityManager;
    }

    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getConnection(?string $name = null): Connection
    {
        return $this->entityManager->getConnection();
    }

    public function getConnections(): array
    {
        return ['default' => $this->entityManager->getConnection()];
    }

    public function getConnectionNames(): array
    {
        return ['default' => 'doctrine.dbal.default_connection'];
    }

    public function getDefaultManagerName(): string
    {
        return 'default';
    }

    public function getManager(?string $name = null): ObjectManager
    {
        return $this->entityManager;
    }

    public function getManagers(): array
    {
        return ['default' => $this->entityManager];
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        $this->entityManager = ($this->factory)();

        return $this->entityManager;
    }

    public function getManagerNames(): array
    {
        return ['default' => 'doctrine.orm.default_entity_manager'];
    }

    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        return $this->entityManager->getRepository($persistentObject);
    }

    public function getManagerForClass(string $class): EntityManager
    {
        return $this->entityManager;
    }
}
