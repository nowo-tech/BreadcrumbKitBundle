<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Controller\Dashboard\ItemCrudController;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbTransactionRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 2.20 (Beacon spec 239): the dashboard writes through a real kernel and the bundle's runner, on the shared (host)
 * manager and on a dedicated one: create, duplicate key, edit and delete of a row that is gone, items under a locked
 * collection.
 */
final class DashboardWritesKernelTest extends TestCase
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

    /**
     * @return iterable<string, array{bool}>
     */
    public static function managerModes(): iterable
    {
        yield 'shared manager' => [false];
        yield 'dedicated manager' => [true];
    }

    #[DataProvider('managerModes')]
    public function testCollectionsAreCreatedEditedAndDeleted(bool $dedicated): void
    {
        $manager = $this->boot($dedicated);

        $created = $this->post('/admin/breadcrumbs/collections/new', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '']]);
        self::assertSame(302, $created->getStatusCode(), (string) $created->getContent());
        $id = (int) $manager->getConnection()->fetchOne('SELECT id FROM dashboard_breadcrumb_collection');
        self::assertGreaterThan(0, $id);

        $edited = $this->post('/admin/breadcrumbs/collections/'.$id.'/edit', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '', 'name' => 'Main']]);
        self::assertSame(302, $edited->getStatusCode());
        self::assertSame('Main', $manager->getConnection()->fetchOne('SELECT name FROM dashboard_breadcrumb_collection'));

        $deleted = $this->post('/admin/breadcrumbs/collections/'.$id.'/delete', ['confirm' => '1']);
        self::assertSame(302, $deleted->getStatusCode());
        self::assertSame(0, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
        self::assertSame(0, $manager->getConnection()->getTransactionNestingLevel());
    }

    #[DataProvider('managerModes')]
    public function testADuplicateKeyAnswersTheFormAndTheNextRequestStillWrites(bool $dedicated): void
    {
        $manager = $this->boot($dedicated);
        $this->post('/admin/breadcrumbs/collections/new', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '']]);

        $duplicate = $this->post('/admin/breadcrumbs/collections/new', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '']]);
        self::assertSame(200, $duplicate->getStatusCode(), 'the form again, with the "duplicate" flash');
        self::assertSame(1, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
        self::assertSame(0, $manager->getConnection()->getTransactionNestingLevel());

        $next = $this->post('/admin/breadcrumbs/collections/new', ['breadcrumb_collection' => ['code' => 'second', 'contextKey' => '']]);
        self::assertSame(302, $next->getStatusCode(), 'the worker is not left with a closed manager');
        self::assertSame(2, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
    }

    #[DataProvider('managerModes')]
    public function testEditingACollectionThatWasDeletedMeanwhileIs404(bool $dedicated): void
    {
        $manager = $this->boot($dedicated);
        $this->post('/admin/breadcrumbs/collections/new', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '']]);
        $id = (int) $manager->getConnection()->fetchOne('SELECT id FROM dashboard_breadcrumb_collection');
        $collections = $this->container()->get('doctrine')->getRepository(BreadcrumbCollection::class);
        $loaded      = $collections->find($id);
        self::assertInstanceOf(BreadcrumbCollection::class, $loaded);
        // Another request deletes the row after this one loaded it (the identity map still holds it).
        $manager->getConnection()->executeStatement('DELETE FROM dashboard_breadcrumb_collection');

        $response = $this->post('/admin/breadcrumbs/collections/'.$id.'/edit', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '', 'name' => 'Late']]);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(0, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
    }

    #[DataProvider('managerModes')]
    public function testItemsAreWrittenUnderTheLockedCollection(bool $dedicated): void
    {
        $manager = $this->boot($dedicated);
        $this->post('/admin/breadcrumbs/collections/new', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '']]);
        $collectionId = (int) $manager->getConnection()->fetchOne('SELECT id FROM dashboard_breadcrumb_collection');
        $base         = '/admin/breadcrumbs/collections/'.$collectionId.'/items';

        $created = $this->post($base.'/new', ['breadcrumb_item' => ['routeName' => 'home', 'label' => 'Home', 'linkEnabled' => '1']]);
        self::assertSame(302, $created->getStatusCode(), (string) $created->getContent());
        $rootId = (int) $manager->getConnection()->fetchOne('SELECT id FROM dashboard_breadcrumb_item');

        $child = $this->post($base.'/new', ['breadcrumb_item' => ['routeName' => 'child', 'label' => 'Child', 'linkEnabled' => '1', 'parent' => (string) $rootId]]);
        self::assertSame(302, $child->getStatusCode(), (string) $child->getContent());
        self::assertSame(2, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item'));

        $childId = (int) $manager->getConnection()->fetchOne("SELECT id FROM dashboard_breadcrumb_item WHERE routeName = 'child'");
        $edited  = $this->post($base.'/'.$childId.'/edit', ['breadcrumb_item' => ['routeName' => 'child2', 'label' => 'Child', 'linkEnabled' => '1', 'parent' => (string) $rootId]]);
        self::assertSame(302, $edited->getStatusCode(), (string) $edited->getContent());
        self::assertSame('child2', $manager->getConnection()->fetchOne("SELECT routeName FROM dashboard_breadcrumb_item WHERE id = $childId"));

        $deleted = $this->post($base.'/'.$childId.'/delete', ['confirm' => '1']);
        self::assertSame(302, $deleted->getStatusCode());
        self::assertSame(1, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item'));
    }

    #[DataProvider('managerModes')]
    public function testTheItemGuardRefusesWhatAConcurrentRequestChanged(bool $dedicated): void
    {
        $manager    = $this->boot($dedicated);
        $collection = (new BreadcrumbCollection())->setCode('main');
        $root       = (new BreadcrumbItem())->setCollection($collection)->setRouteName('root');
        $child      = (new BreadcrumbItem())->setCollection($collection)->setParent($root)->setRouteName('child');
        foreach ([$collection, $root, $child] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        $controller = $this->container()->get(ItemCrudController::class);
        self::assertInstanceOf(ItemCrudController::class, $controller);
        $runner = $this->container()->get(BreadcrumbTransactionRunner::class);
        self::assertInstanceOf(BreadcrumbTransactionRunner::class, $runner);
        $guard = new ReflectionMethod($controller, 'lockCollectionAndParent');

        // The form picked `child` as the parent of `root`: a cycle.
        $root->setParent($child);
        $failure = null;
        try {
            $runner->run(static fn (): mixed => $guard->invoke($controller, $root, $collection));
        } catch (ConflictHttpException $failure) {
        }
        self::assertInstanceOf(ConflictHttpException::class, $failure);
        $root->setParent(null);

        // The item was deleted meanwhile.
        $manager->getConnection()->executeStatement('UPDATE dashboard_breadcrumb_item SET parent_id = NULL');
        $manager->getConnection()->executeStatement("DELETE FROM dashboard_breadcrumb_item WHERE routeName = 'child'");
        $failure = null;
        try {
            $runner->run(static fn (): mixed => $guard->invoke($controller, $child, $collection));
        } catch (NotFoundHttpException $failure) {
        }
        self::assertInstanceOf(NotFoundHttpException::class, $failure);

        // The collection was deleted meanwhile.
        $manager->getConnection()->executeStatement('DELETE FROM dashboard_breadcrumb_item');
        $manager->getConnection()->executeStatement('DELETE FROM dashboard_breadcrumb_collection');
        $failure = null;
        try {
            $runner->run(static fn (): mixed => $guard->invoke($controller, $root, $collection));
        } catch (NotFoundHttpException $failure) {
        }
        self::assertInstanceOf(NotFoundHttpException::class, $failure);
        self::assertSame(0, $manager->getConnection()->getTransactionNestingLevel());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function post(string $uri, array $data): \Symfony\Component\HttpFoundation\Response
    {
        \assert(null !== $this->kernel);

        // The CSRF token of every form is accepted (see EntityManagerTestKernel), but it must be present.
        foreach ($data as $name => $fields) {
            $data[$name] = \is_array($fields) ? $fields + ['_token' => 'accepted'] : $fields;
        }
        $data += ['_token' => 'accepted'];

        return $this->kernel->handle(Request::create($uri, 'POST', $data));
    }

    private function boot(bool $dedicated): EntityManagerInterface
    {
        $doctrine = $dedicated
            ? [
                'dbal' => [
                    'default_connection' => 'default',
                    'connections' => [
                        'default' => ['url' => 'sqlite:///'.$this->database('host')],
                        'breadcrumb' => ['url' => 'sqlite:///'.$this->database('bundle')],
                    ],
                ],
                'orm' => ['default_entity_manager' => 'default', 'entity_managers' => [
                    'default' => ['connection' => 'default', 'auto_mapping' => true, 'mappings' => ['HostApp' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Nowo\\BreadcrumbKitBundle\\Tests\\Integration\\Kernel\\Entity',
                    ]]],
                    'breadcrumb' => ['connection' => 'breadcrumb'],
                ]],
            ]
            : [
                'dbal' => ['url' => 'sqlite:///'.$this->database('shared')],
                'orm' => ['auto_mapping' => true],
            ];
        $this->kernel = new EntityManagerTestKernel($doctrine, $dedicated ? ['doctrine' => ['entity_manager' => 'breadcrumb']] : [], false, true);
        $this->kernel->boot();
        $registry = $this->container()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager($dedicated ? 'breadcrumb' : 'default');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());

        return $manager;
    }

    private function container(): ContainerInterface
    {
        \assert(null !== $this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function database(string $name): string
    {
        $file = \sprintf('%s/nowo_breadcrumb_kit_writes_%s_%d.sqlite', sys_get_temp_dir(), $name, (int) getmypid());
        if (is_file($file)) {
            unlink($file);
        }
        $this->files[] = $file;

        return $file;
    }
}
