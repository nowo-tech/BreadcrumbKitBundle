<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Command\GenerateBreadcrumbKitMigrationCommand;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbCollectionType;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbItemType;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * REQ-TEST-003: the repositories and the four console commands, against a real kernel and SQLite (they were excluded from
 * the coverage gate).
 */
require_once __DIR__.'/../../Support/CommandFunctionOverrides.php';

final class DashboardRepositoriesAndCommandsKernelTest extends TestCase
{
    private EntityManagerTestKernel $kernel;

    private EntityManagerInterface $manager;

    private ContainerInterface $container;

    /** @var list<string> */
    private array $cleanup = [];

    protected function setUp(): void
    {
        $this->bootKernel('doctrine.orm.naming_strategy.underscore_number_aware');
    }

    private function bootKernel(string $namingStrategy): void
    {
        $file = \sprintf('%s/nowo_breadcrumb_kit_repo_%d_%s.sqlite', sys_get_temp_dir(), (int) getmypid(), hash('xxh128', $this->name().$namingStrategy));
        if (is_file($file)) {
            unlink($file);
        }
        $this->cleanup[] = $file;
        $this->kernel = new EntityManagerTestKernel(['dbal' => ['url' => 'sqlite:///'.$file], 'orm' => ['auto_mapping' => true, 'naming_strategy' => $namingStrategy]], [], false, true);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $this->container = $container;
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $this->manager = $manager;
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        foreach ($this->cleanup as $path) {
            if (is_dir($path)) {
                foreach (glob($path.'/*') ?: [] as $entry) {
                    unlink($entry);
                }
                rmdir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testTheCollectionRepositoryFindsByExactKeyAndSearchesForTheDashboard(): void
    {
        [$main, $shop] = $this->seed();
        $repository = $this->collections();

        self::assertSame($main->getId(), $repository->findOneByCodeAndContextKey('main')?->getId());
        self::assertSame($shop->getId(), $repository->findOneByCodeAndContextKey('shop', 'tenant_1')?->getId());
        self::assertNull($repository->findOneByCodeAndContextKey('shop', 'other'));
        self::assertNull($repository->findOneByCodeAndContextKey('missing'));

        self::assertCount(3, $repository->findForDashboard());
        self::assertSame(['main'], array_map(static fn (BreadcrumbCollection $c): string => $c->getCode(), $repository->findForDashboard('', 0, 1)));
        self::assertSame(['second'], array_map(static fn (BreadcrumbCollection $c): string => $c->getCode(), $repository->findForDashboard('', 1, 1)));
        self::assertCount(1, $repository->findForDashboard('Shop front'), 'the name is searched');
        self::assertCount(1, $repository->findForDashboard('tenant'), 'the context key is searched');
        self::assertCount(0, $repository->findForDashboard('100%'), 'wildcards are escaped, not interpreted');
        self::assertCount(3, $repository->findForDashboard('', 0, 0), 'a zero limit means no pagination');

        self::assertSame(3, $repository->countForDashboard());
        self::assertSame(1, $repository->countForDashboard('main'));
        self::assertSame(0, $repository->countForDashboard('zzz'));
    }

    public function testTheItemRepositoryListsSearchesAndCounts(): void
    {
        [$main, $shop] = $this->seed();
        $repository = $this->items();

        self::assertCount(2, $repository->findAllForCollection($main));
        self::assertCount(2, $repository->findForDashboardList($main, null));
        self::assertCount(2, $repository->findForDashboardList($main, '   '));
        self::assertSame(['app_home'], array_map(static fn (BreadcrumbItem $i): string => $i->getRouteName(), $repository->findForDashboardList($main, null, 0, 1)));
        self::assertSame(['app_about'], array_map(static fn (BreadcrumbItem $i): string => $i->getRouteName(), $repository->findForDashboardList($main, null, 1, 5)));
        self::assertCount(1, $repository->findForDashboardList($main, null, -5, 0), 'a negative offset is clamped to 0 and a zero limit to 1');
        self::assertCount(1, $repository->findForDashboardList($main, 'ABOUT'), 'the route name is searched case-insensitively');
        self::assertCount(1, $repository->findForDashboardList($main, 'Welcome'), 'the label is searched');
        $firstId = (int) $repository->findForDashboardList($main, null)[0]->getId();
        self::assertCount(1, $repository->findForDashboardList($main, (string) $firstId), 'a numeric search also matches the id');

        self::assertSame(2, $repository->countForDashboardList($main, null));
        self::assertSame(1, $repository->countForDashboardList($main, 'about'));
        self::assertSame(0, $repository->countForDashboardList($shop, 'about'));

        self::assertSame([], $repository->countForCollections([]));
        self::assertSame([(int) $main->getId() => 2], $repository->countForCollections([(int) $main->getId(), (int) $shop->getId()]));
    }

    public function testExportAndImportCommandsRoundTrip(): void
    {
        $this->seed();
        $export = $this->command('nowo_breadcrumb_kit:export');

        self::assertSame(Command::SUCCESS, $export->execute([]));
        self::assertArrayHasKey('collections', json_decode($export->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));

        self::assertSame(Command::SUCCESS, $export->execute(['--code' => 'main']));
        self::assertStringContainsString('"main"', $export->getDisplay());

        self::assertSame(Command::FAILURE, $export->execute(['--code' => 'ghost', '--context-key' => 'x']));
        self::assertStringContainsString('not found', $export->getDisplay());

        $file = sys_get_temp_dir().'/breadcrumb-export-'.getmypid().'/out.json';
        $this->cleanup[] = \dirname($file);
        self::assertSame(Command::SUCCESS, $export->execute(['--file' => $file]));
        self::assertFileExists($file);
        self::assertStringContainsString('Exported to', $export->getDisplay());

        $import = $this->command('nowo_breadcrumb_kit:import');
        $this->manager->getConnection()->executeStatement('DELETE FROM dashboard_breadcrumb_item');
        $this->manager->getConnection()->executeStatement('DELETE FROM dashboard_breadcrumb_collection');
        $this->manager->clear();
        self::assertSame(Command::SUCCESS, $import->execute(['--file' => $file]));
        self::assertSame(3, (int) $this->manager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
        self::assertSame(Command::SUCCESS, $import->execute(['--file' => $file, '--strategy' => 'replace']));
    }

    public function testImportCommandRejectsBadInput(): void
    {
        $import = $this->command('nowo_breadcrumb_kit:import');
        $dir = sys_get_temp_dir().'/breadcrumb-import-cmd-'.getmypid();
        mkdir($dir);
        $this->cleanup[] = $dir;

        self::assertSame(Command::FAILURE, $import->execute([]));
        self::assertStringContainsString('readable', $import->getDisplay());
        self::assertSame(Command::FAILURE, $import->execute(['--file' => $dir.'/missing.json']));

        file_put_contents($dir.'/bad.json', '{nope');
        self::assertSame(Command::FAILURE, $import->execute(['--file' => $dir.'/bad.json']));
        self::assertStringContainsString('Invalid JSON', $import->getDisplay());

        file_put_contents($dir.'/scalar.json', '5');
        self::assertSame(Command::FAILURE, $import->execute(['--file' => $dir.'/scalar.json']));
        self::assertStringContainsString('root must be', $import->getDisplay());

        file_put_contents($dir.'/ok.json', '{"collections":[]}');
        self::assertSame(Command::FAILURE, $import->execute(['--file' => $dir.'/ok.json', '--strategy' => 'bogus']));
        self::assertStringContainsString('strategy must be', $import->getDisplay());

        file_put_contents($dir.'/errors.json', '{"collections":[{"collection":{"contextKey":""},"items":[]}]}');
        self::assertSame(Command::FAILURE, $import->execute(['--file' => $dir.'/errors.json']), 'importer errors make the command fail');
    }

    public function testPreviewCommandPrintsTheTrailOrWarns(): void
    {
        $this->seed();
        $preview = $this->command('nowo_breadcrumb_kit:preview');

        self::assertSame(Command::SUCCESS, $preview->execute(['--collection' => 'main', '--route' => 'app_about', '--path' => '/about', '--locale' => 'en', '--route-params' => '{"id":3}', '--attributes' => '{"x":true,"y":[1],"z":null,"0":1}']));
        self::assertStringContainsString('label', $preview->getDisplay());

        self::assertSame(Command::SUCCESS, $preview->execute(['--collection' => 'ghost']));
        self::assertStringContainsString('Empty trail', $preview->getDisplay());

        self::assertSame(Command::FAILURE, $preview->execute(['--route-params' => '{bad']));
        self::assertStringContainsString('Invalid JSON', $preview->getDisplay());
        self::assertSame(Command::FAILURE, $preview->execute(['--attributes' => '"text"']));
        self::assertStringContainsString('JSON object', $preview->getDisplay());
    }

    public function testMigrationCommandDumpsWritesAndReportsNothingToUpdate(): void
    {
        $migration = $this->command('nowo_breadcrumb_kit:generate-migration');

        self::assertSame(Command::SUCCESS, $migration->execute(['--dump' => true]));
        self::assertStringContainsString('CREATE TABLE', $migration->getDisplay());

        $dir = sys_get_temp_dir().'/breadcrumb-migrations-'.getmypid();
        $this->cleanup[] = $dir;
        self::assertSame(Command::SUCCESS, $migration->execute(['--path' => $dir, '--namespace' => '\\App\\Migrations\\']));
        $files = glob($dir.'/Version*_CreateBreadcrumbKitTablesByConfiguration.php') ?: [];
        self::assertCount(1, $files);
        $content = (string) file_get_contents($files[0]);
        self::assertStringContainsString('namespace App\\Migrations;', $content);
        self::assertStringContainsString('CREATE TABLE', $content);
        self::assertStringContainsString('Table prefix:', $content);

        // The tables exist with every column (the command names them with underscores, as a host with the underscore
        // naming strategy has them), so --update has nothing to alter.
        self::assertSame(Command::SUCCESS, $migration->execute(['--update' => true, '--path' => $dir]));
        self::assertStringContainsString('Nothing to write', $migration->getDisplay());
        self::assertSame(Command::SUCCESS, $migration->execute(['--update' => true, '--dump' => true]));

        // A table without the later columns gets ALTER statements.
        $connection = $this->manager->getConnection();
        $connection->executeStatement('DROP TABLE dashboard_breadcrumb_item');
        $connection->executeStatement('CREATE TABLE dashboard_breadcrumb_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL)');
        self::assertSame(Command::SUCCESS, $migration->execute(['--update' => true, '--dump' => true]));
        self::assertStringContainsString('ADD "path_pattern" ', $migration->getDisplay());
        self::assertStringContainsString('ADD "match_attributes" ', $migration->getDisplay());
        self::assertStringNotContainsString('pathPattern', $migration->getDisplay());
    }

    public function testMigrationUpdateFollowsTheDefaultNamingStrategyColumnNames(): void
    {
        $this->kernel->shutdown();
        $this->bootKernel('doctrine.orm.naming_strategy.default');
        $migration = $this->command('nowo_breadcrumb_kit:generate-migration');

        // The columns exist under the host's names (pathPattern / matchAttributes), so --update has nothing to alter.
        self::assertSame(Command::SUCCESS, $migration->execute(['--update' => true, '--dump' => true]));
        self::assertStringNotContainsString('ADD', $migration->getDisplay());

        $connection = $this->manager->getConnection();
        $connection->executeStatement('DROP TABLE dashboard_breadcrumb_item');
        $connection->executeStatement('CREATE TABLE dashboard_breadcrumb_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL)');
        self::assertSame(Command::SUCCESS, $migration->execute(['--update' => true, '--dump' => true]));
        self::assertStringContainsString('ADD "pathPattern" ', $migration->getDisplay());
        self::assertStringContainsString('ADD "matchAttributes" ', $migration->getDisplay());
        self::assertStringNotContainsString('path_pattern', $migration->getDisplay());
    }

    public function testMigrationUpdateQuotesReservedWordColumnNames(): void
    {
        // A host naming strategy may map a field to a reserved word; the column must be quoted like the table is.
        $meta = $this->manager->getClassMetadata(BreadcrumbItem::class);
        $meta->columnNames['pathPattern'] = 'order';
        $meta->columnNames['matchAttributes'] = 'group';
        $migration = $this->command('nowo_breadcrumb_kit:generate-migration');

        $connection = $this->manager->getConnection();
        $connection->executeStatement('DROP TABLE dashboard_breadcrumb_item');
        $connection->executeStatement('CREATE TABLE dashboard_breadcrumb_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL)');
        self::assertSame(Command::SUCCESS, $migration->execute(['--update' => true, '--dump' => true]));
        self::assertStringContainsString('ADD "order" VARCHAR(512)', $migration->getDisplay());
        self::assertStringContainsString('ADD "group" JSON', $migration->getDisplay());
    }

    private function command(string $name): CommandTester
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        return new CommandTester($application->find($name));
    }

    /**
     * @return list<BreadcrumbCollection>
     */
    private function seed(): array
    {
        $main = (new BreadcrumbCollection())->setCode('main');
        $shop = (new BreadcrumbCollection())->setCode('shop')->setContextKey('tenant_1')->setName('Shop front');
        $second = (new BreadcrumbCollection())->setCode('second');
        $home = (new BreadcrumbItem())->setCollection($main)->setRouteName('app_home')->setLabel('Welcome');
        $about = (new BreadcrumbItem())->setCollection($main)->setRouteName('app_about')->setLabel('About');
        foreach ([$main, $shop, $second, $home, $about] as $entity) {
            $this->manager->persist($entity);
        }
        $this->manager->flush();
        $this->manager->clear();

        return [$main, $shop, $second];
    }

    private function collections(): BreadcrumbCollectionRepository
    {
        $repository = $this->container->get(BreadcrumbCollectionRepository::class);
        self::assertInstanceOf(BreadcrumbCollectionRepository::class, $repository);

        return $repository;
    }

    private function items(): BreadcrumbItemRepository
    {
        $repository = $this->container->get(BreadcrumbItemRepository::class);
        self::assertInstanceOf(BreadcrumbItemRepository::class, $repository);

        return $repository;
    }

    public function testCountForCollectionsIgnoresRowsWithoutAValidCollectionId(): void
    {
        [$main] = $this->seed();
        // SQLite does not enforce the foreign key: an orphan row of collection 0 exists in a damaged database.
        $this->manager->getConnection()->executeStatement("INSERT INTO dashboard_breadcrumb_item (collection_id, route_name, link_enabled) VALUES (0, 'orphan', 1)");

        self::assertSame([(int) $main->getId() => 2], $this->items()->countForCollections([0, (int) $main->getId()]));
    }

    public function testExportCommandFailsCleanlyWhenTheDataCannotBeEncoded(): void
    {
        $this->seed();
        $this->manager->getConnection()->executeStatement("UPDATE dashboard_breadcrumb_item SET label = CAST(x'ff' AS TEXT) WHERE route_name = 'app_home'");
        $export = $this->command('nowo_breadcrumb_kit:export');

        self::assertSame(Command::FAILURE, $export->execute([]));
        self::assertStringContainsString('JSON encode failed', $export->getDisplay());
    }

    public function testImportCommandReportsAFileThatCannotBeRead(): void
    {
        $file = sys_get_temp_dir().'/breadcrumb-'.getmypid().'.unreadable';
        file_put_contents($file, '{}');
        $this->cleanup[] = $file;
        $import = $this->command('nowo_breadcrumb_kit:import');

        self::assertSame(Command::FAILURE, $import->execute(['--file' => $file]));
        self::assertStringContainsString('Could not read file', $import->getDisplay());
    }

    public function testMigrationCommandResolvesARelativePathAndNamesANonDefaultConnection(): void
    {
        $command = new GenerateBreadcrumbKitMigrationCommand($this->manager, 'reporting', 'bk_');
        $tester = new CommandTester($command);
        $relative = 'var-migration-test-'.getmypid();
        $this->cleanup[] = (string) getcwd().'/'.$relative;

        self::assertSame(Command::SUCCESS, $tester->execute(['--path' => $relative]));

        self::assertCount(1, glob((string) getcwd().'/'.$relative.'/Version*.php') ?: []);
        self::assertStringContainsString('doctrine:migrations:migrate --conn=reporting', $tester->getDisplay());
        self::assertStringContainsString('Connection: reporting', $tester->getDisplay());
    }

    public function testTheFormListenersTolerateAFormWithoutData(): void
    {
        $factory = $this->container->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        [$main] = $this->seed();

        $collectionForm = $factory->create(BreadcrumbCollectionType::class, null, ['inline_edit_access_keys' => []]);
        $collectionForm->submit(['code' => 'x', 'contextKey' => '']);
        self::assertTrue($collectionForm->isSubmitted());

        // Without a data class the form carries an array: the listeners leave it alone.
        $arrayForm = $factory->create(BreadcrumbCollectionType::class, null, ['inline_edit_access_keys' => [], 'data_class' => null]);
        $arrayForm->submit(['code' => 'y', 'contextKey' => '']);
        self::assertTrue($arrayForm->isSubmitted());

        $itemForm = $factory->create(BreadcrumbItemType::class, null, ['collection' => $this->manager->find(BreadcrumbCollection::class, $main->getId()), 'exclude_item' => null]);
        self::assertSame('breadcrumb_item', $itemForm->createView()->vars['name']);
    }
}
