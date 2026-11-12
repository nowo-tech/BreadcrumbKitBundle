<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Controller\Dashboard\CollectionCrudController;
use Nowo\BreadcrumbKitBundle\Controller\Dashboard\ItemCrudController;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbCollectionChangedEvent;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoaderInterface;
use Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel\Entity\HostNote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * `doctrine.entity_manager` (2.16, architecture bar rule 6) through a real kernel: a standard host keeps the manager
 * that maps the entities; a DDD host maps them on a dedicated manager only (also with `auto_mapping: true` on the
 * default and without `default_entity_manager`); dashboard controllers, the importer and the recovery reset use it;
 * the host's manager is never flushed, cleared or reset.
 */
final class DedicatedEntityManagerKernelTest extends TestCase
{
    private ?EntityManagerTestKernel $kernel = null;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testStandardHostKeepsTheDefaultManager(): void
    {
        $this->boot([
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['auto_mapping' => true],
        ]);
        $default = $this->registry()->getManager();

        self::assertSame($default, $this->registry()->getManagerForClass(BreadcrumbCollection::class));
        self::assertSame($default, $this->container()->get('nowo_breadcrumb_kit.entity_manager'));
        self::assertInstanceOf(BreadcrumbLoader::class, $this->container()->get(BreadcrumbLoaderInterface::class));
    }

    public function testDedicatedManagerIsUsedEverywhereAndTheHostManagerIsNeverFlushed(): void
    {
        $this->boot($this->dedicatedDoctrine(), ['doctrine' => ['entity_manager' => 'breadcrumb']]);
        [$host, $bundle] = $this->managersWithSchema();

        self::assertSame('default', $this->container()->getParameter('doctrine.default_entity_manager'));
        self::assertTrue($host->getMetadataFactory()->isTransient(BreadcrumbCollection::class), 'not mapped on the auto-mapped host manager');
        self::assertSame($bundle, $this->registry()->getManagerForClass(BreadcrumbCollection::class));
        foreach ([CollectionCrudController::class, ItemCrudController::class, BreadcrumbImporter::class] as $service) {
            $object = $this->container()->get($service);
            self::assertInstanceOf($service, $object);
            self::assertSame($bundle, (new ReflectionProperty($service, 'entityManager'))->getValue($object), $service.' uses the bundle manager');
        }

        $events = [];
        $dispatcher = $this->container()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(BreadcrumbCollectionChangedEvent::class, static function (BreadcrumbCollectionChangedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $note = new HostNote('pending host change');
        $host->persist($note);

        $importer = $this->container()->get(BreadcrumbImporter::class);
        self::assertInstanceOf(BreadcrumbImporter::class, $importer);
        $result = $importer->import([
            'collection' => ['code' => 'main'],
            'items' => [['routeName' => 'home', 'label' => 'Home']],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame(1, (int) $bundle->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item'));
        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_note'), 'the host manager is never flushed');
        self::assertTrue($host->contains($note));
        self::assertNotSame([], $events);
        self::assertSame('main', $events[0]->collectionCode);

        // LI-BC-M1 through the container: the middleware sits on the bundle connection; a rolled-back import is silent.
        $events = [];
        $bundle->getConnection()->beginTransaction();
        $importer->import(['collection' => ['code' => 'rolled-back'], 'items' => [['routeName' => 'home', 'label' => 'Home']]]);
        $bundle->getConnection()->rollBack();
        $bundle->clear();
        $importer->import(['collection' => ['code' => 'after'], 'items' => [['routeName' => 'home', 'label' => 'Home']]]);
        self::assertSame(['after'], array_values(array_unique(array_map(static fn (BreadcrumbCollectionChangedEvent $e): string => $e->collectionCode, $events))));
    }

    /**
     * DoctrineBundle 3.4 regression: `auto_discover_entities` (on by default for a manager without `mappings` and
     * `auto_mapping`) gives the host manager's chain a default attribute driver, which claims every `#[ORM\Entity]`
     * class, so `getManagerForClass()` answered the host manager and the repositories queried its database. The
     * explicit catch-all default driver reproduces it on every DoctrineBundle version.
     */
    #[DataProvider('hostManagersWithADefaultDriver')]
    public function testDedicatedManagerWinsOverADefaultDriverOfTheHostManager(bool $explicitDefaultDriver): void
    {
        $doctrine = $this->dedicatedDoctrine();
        unset($doctrine['orm']['entity_managers']['default']['auto_mapping'], $doctrine['orm']['entity_managers']['default']['mappings']);
        $this->boot($doctrine, ['doctrine' => ['entity_manager' => 'breadcrumb']], $explicitDefaultDriver);
        $bundle = $this->registry()->getManager('breadcrumb');

        // Neither manager is initialized yet: DoctrineBundle 3.4 asks the metadata drivers.
        self::assertSame($bundle, $this->registry()->getManagerForClass(BreadcrumbCollection::class));
        self::assertSame($bundle, $this->registry()->getManagerForClass(BreadcrumbItem::class));

        [$host, $bundle] = $this->managersWithSchema();
        self::assertTrue($host->getMetadataFactory()->isTransient(BreadcrumbCollection::class), 'not mapped on the host manager');
        self::assertSame($bundle, $this->registry()->getManagerForClass(BreadcrumbCollection::class));

        $importer = $this->container()->get(BreadcrumbImporter::class);
        self::assertInstanceOf(BreadcrumbImporter::class, $importer);
        self::assertSame(1, $importer->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'home', 'label' => 'Home']]])['created']);
        self::assertSame(1, (int) $bundle->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item'), 'written to the bundle database');
        $repository = $this->container()->get(BreadcrumbCollectionRepository::class);
        self::assertInstanceOf(BreadcrumbCollectionRepository::class, $repository);
        self::assertInstanceOf(BreadcrumbCollection::class, $repository->findOneBy(['code' => 'main']), 'the repository reads the bundle database');
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function hostManagersWithADefaultDriver(): iterable
    {
        yield 'host manager without mappings (DoctrineBundle 3.4 auto-discovery)' => [false];
        yield 'host manager with a catch-all default driver' => [true];
    }

    public function testHostWithoutADefaultManagerNameKeepsItsOwnDefault(): void
    {
        $this->boot($this->dedicatedDoctrine('main', false), ['doctrine' => ['entity_manager' => 'breadcrumb']]);

        self::assertSame(['main', 'breadcrumb'], array_keys((array) $this->container()->getParameter('doctrine.entity_managers')));
        self::assertSame('main', $this->container()->getParameter('doctrine.default_entity_manager'));
        $main = $this->registry()->getManager('main');
        self::assertInstanceOf(EntityManagerInterface::class, $main);
        self::assertTrue($main->getMetadataFactory()->isTransient(BreadcrumbCollection::class));
        self::assertSame($this->registry()->getManager('breadcrumb'), $this->registry()->getManagerForClass(BreadcrumbCollection::class));
    }

    public function testUnknownManagerFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_breadcrumb_kit.doctrine.entity_manager is "reports"');

        $this->boot($this->dedicatedDoctrine(), ['doctrine' => ['entity_manager' => 'reports']]);
    }

    /**
     * Kept-alive kernel, no services resetter: only the bundle's closed manager is reset; the host keeps its entities.
     */
    public function testRecoveryResetsOnlyTheBundleManager(): void
    {
        $this->boot($this->dedicatedDoctrine(), ['doctrine' => ['entity_manager' => 'breadcrumb']]);
        [$host, $bundle] = $this->managersWithSchema();
        $note = new HostNote('managed by the host');
        $host->persist($note);
        $host->flush();

        $bundle->close();
        $resetter = $this->container()->get(BreadcrumbEntityManagerResetter::class);
        self::assertInstanceOf(BreadcrumbEntityManagerResetter::class, $resetter);
        $resetter->resetIfClosed();

        self::assertTrue($bundle->isOpen());
        self::assertTrue($host->contains($note));

        $importer = $this->container()->get(BreadcrumbImporter::class);
        self::assertInstanceOf(BreadcrumbImporter::class, $importer);
        $baseline = 0;
        for ($i = 0; $i < 200; ++$i) {
            $importer->import(['collection' => ['code' => 'c'.$i], 'items' => [['routeName' => 'home', 'label' => 'Home']]]);
            $bundle->clear();
            if (10 === $i) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $baseline, 'memory stays flat');
        self::assertTrue($host->contains($note));
    }

    /**
     * @return array{EntityManagerInterface, EntityManagerInterface}
     */
    private function managersWithSchema(): array
    {
        $host = $this->registry()->getManager('default');
        $bundle = $this->registry()->getManager('breadcrumb');
        self::assertInstanceOf(EntityManagerInterface::class, $host);
        self::assertInstanceOf(EntityManagerInterface::class, $bundle);
        (new SchemaTool($host))->createSchema($host->getMetadataFactory()->getAllMetadata());
        (new SchemaTool($bundle))->createSchema($bundle->getMetadataFactory()->getAllMetadata());

        return [$host, $bundle];
    }

    /**
     * @param array<string, mixed> $doctrine
     * @param array<string, mixed> $breadcrumbKit
     */
    private function boot(array $doctrine, array $breadcrumbKit = [], bool $hostDefaultDriver = false): void
    {
        $this->kernel = new EntityManagerTestKernel($doctrine, $breadcrumbKit, $hostDefaultDriver);
        $this->kernel->boot();
    }

    private function container(): ContainerInterface
    {
        self::assertInstanceOf(EntityManagerTestKernel::class, $this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function registry(): ManagerRegistry
    {
        $registry = $this->container()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        return $registry;
    }

    /**
     * @return array<string, mixed>
     */
    private function dedicatedDoctrine(string $hostManager = 'default', bool $withDefaultName = true): array
    {
        $orm = ['entity_managers' => [
            $hostManager => [
                'connection' => 'default',
                'auto_mapping' => true,
                'mappings' => ['HostApp' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => __DIR__.'/Entity',
                    'prefix' => 'Nowo\\BreadcrumbKitBundle\\Tests\\Integration\\Kernel\\Entity',
                ]],
            ],
            'breadcrumb' => ['connection' => 'breadcrumb'],
        ]];
        if ($withDefaultName) {
            $orm = ['default_entity_manager' => $hostManager] + $orm;
        }

        return [
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => ['url' => 'sqlite:///'.$this->database('host')],
                    'breadcrumb' => ['url' => 'sqlite:///'.$this->database('bundle')],
                ],
            ],
            'orm' => $orm,
        ];
    }

    private function database(string $name): string
    {
        $file = \sprintf('%s/nowo_breadcrumb_kit_em_%s_%d.sqlite', sys_get_temp_dir(), $name, (int) getmypid());
        if (is_file($file)) {
            unlink($file);
        }
        $this->files[] = $file;

        return $file;
    }
}
