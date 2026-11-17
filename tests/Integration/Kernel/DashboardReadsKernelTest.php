<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Controller\Dashboard\ImportExportController;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REQ-TEST-003: the hand-written dashboard controllers (lists, partial and full forms, confirmation pages, export, import and
 * their guard branches) through a real kernel and SQLite, so they count in the coverage gate instead of being excluded.
 */
final class DashboardReadsKernelTest extends TestCase
{
    private ?EntityManagerTestKernel $kernel = null;

    /** @var list<string> */
    private array $files = [];

    private EntityManagerInterface $manager;

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

    public function testCollectionListSearchAndPagination(): void
    {
        $this->boot(['web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs', 'pagination' => ['enabled' => true, 'per_page' => 1]]]);
        $this->seed();

        $first = $this->get('/admin/breadcrumbs/collections');
        self::assertSame(200, $first->getStatusCode());
        self::assertStringContainsString('main', (string) $first->getContent());

        $beyond = $this->get('/admin/breadcrumbs/collections?page=99&q=main');
        self::assertSame(200, $beyond->getStatusCode(), 'a page past the end is clamped');

        $none = $this->get('/admin/breadcrumbs/collections?q=nothing-matches');
        self::assertSame(200, $none->getStatusCode());
    }

    public function testCollectionListWithoutPagination(): void
    {
        $this->boot(['web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs', 'pagination' => ['enabled' => false]]]);
        $this->seed();

        $response = $this->get('/admin/breadcrumbs/collections');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('second', (string) $response->getContent());
    }

    public function testCollectionFormsPartialsAndFullPages(): void
    {
        $this->boot();
        [$collection] = $this->seed();
        $id = (int) $collection->getId();
        $base = '/admin/breadcrumbs/collections';

        self::assertSame(200, $this->get($base.'/new?_partial=1')->getStatusCode());
        self::assertSame(200, $this->get($base.'/new')->getStatusCode());
        self::assertSame(200, $this->get($base.'/'.$id.'/edit?_partial=1')->getStatusCode());
        self::assertSame(200, $this->get($base.'/'.$id.'/edit')->getStatusCode());
        $xhr = Request::create($base.'/'.$id.'/edit', 'GET');
        $xhr->headers->set('X-Requested-With', 'XMLHttpRequest');
        self::assertSame(200, $this->handle($xhr)->getStatusCode());
        $xhrNew = Request::create($base.'/new', 'GET');
        $xhrNew->headers->set('X-Requested-With', 'XMLHttpRequest');
        self::assertSame(200, $this->handle($xhrNew)->getStatusCode());

        self::assertSame(404, $this->get($base.'/9999/edit')->getStatusCode());
        self::assertSame(404, $this->get($base.'/9999/edit?_partial=1')->getStatusCode());
    }

    public function testInvalidCollectionFormsAnswer422(): void
    {
        $this->boot();
        [$collection] = $this->seed();
        $base = '/admin/breadcrumbs/collections';

        $new = $this->post($base.'/new', ['breadcrumb_collection' => ['code' => str_repeat('c', 65), 'contextKey' => '']]);
        self::assertSame(422, $new->getStatusCode());

        $edit = $this->post($base.'/'.$collection->getId().'/edit', ['breadcrumb_collection' => ['code' => str_repeat('c', 65), 'contextKey' => '']]);
        self::assertSame(422, $edit->getStatusCode());
    }

    public function testABlankRequiredFieldAnswers422WithTheFormErrorNot500(): void
    {
        $this->boot();
        [$collection, , $item] = $this->seed();
        $base = '/admin/breadcrumbs/collections';

        $new = $this->post($base.'/new', ['breadcrumb_collection' => ['code' => '', 'contextKey' => '']]);
        self::assertSame(422, $new->getStatusCode(), substr((string) $new->getContent(), 0, 300));
        self::assertStringContainsString('should not be blank', (string) $new->getContent());

        $edit = $this->post($base.'/'.$collection->getId().'/edit', ['breadcrumb_collection' => ['code' => '', 'contextKey' => '']]);
        self::assertSame(422, $edit->getStatusCode());

        $itemBase = $base.'/'.$collection->getId().'/items';
        $newItem = $this->post($itemBase.'/new', ['breadcrumb_item' => ['routeName' => '', 'label' => 'x']]);
        self::assertSame(422, $newItem->getStatusCode(), substr((string) $newItem->getContent(), 0, 300));
        self::assertStringContainsString('<li id="breadcrumb_item_routeName_error1">', (string) $newItem->getContent(), 'the form error is rendered');

        $editItem = $this->post($itemBase.'/'.$item->getId().'/edit', ['breadcrumb_item' => ['routeName' => '']]);
        self::assertSame(422, $editItem->getStatusCode());
    }

    public function testASameHostRefererIsHonouredAfterASave(): void
    {
        $this->boot();
        [$collection] = $this->seed();
        $base = '/admin/breadcrumbs/collections';

        $same = Request::create('http://localhost'.$base.'/new', 'POST', ['breadcrumb_collection' => ['code' => 'viaref', 'contextKey' => '', '_token' => 'accepted'], '_token' => 'accepted']);
        $same->headers->set('Referer', 'http://localhost/somewhere?x=1');
        $response = $this->handle($same);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('http://localhost/somewhere?x=1', $response->headers->get('Location'));

        $foreign = Request::create('http://localhost'.$base.'/'.$collection->getId().'/edit', 'POST', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '', '_token' => 'accepted'], '_token' => 'accepted']);
        $foreign->headers->set('Referer', 'http://evil.example/steal');
        $redirect = $this->handle($foreign);
        self::assertSame(302, $redirect->getStatusCode());
        self::assertStringNotContainsString('evil.example', (string) $redirect->headers->get('Location'));
    }

    public function testCollectionExportDownloadsJsonAndMissingIs404(): void
    {
        $this->boot();
        [$collection] = $this->seed();

        $response = $this->get('/admin/breadcrumbs/collections/'.$collection->getId().'/export');
        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('breadcrumb-main-export.json', (string) $response->headers->get('Content-Disposition'));
        $decoded = json_decode($this->body($response), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('1', $decoded['breadcrumbKitExport']);

        $withContext = $this->get('/admin/breadcrumbs/collections/'.$this->collectionId('shop', 'tenant 1').'/export');
        self::assertStringContainsString('breadcrumb-shop-tenant_1-export.json', (string) $withContext->headers->get('Content-Disposition'));

        self::assertSame(404, $this->get('/admin/breadcrumbs/collections/9999/export')->getStatusCode());
    }

    public function testCollectionDeleteConfirmationInvalidTokenAndMissing(): void
    {
        $this->boot();
        [$collection] = $this->seed();
        $base = '/admin/breadcrumbs/collections/'.$collection->getId().'/delete';

        self::assertSame(200, $this->get($base)->getStatusCode(), 'the no-JS confirmation page');

        $rejected = $this->handle(Request::create($base, 'POST', ['confirm' => '1', '_token' => 'rejected']));
        self::assertSame(302, $rejected->getStatusCode());
        self::assertSame(3, (int) $this->manager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'), 'nothing is deleted with an invalid token');

        self::assertSame(404, $this->get('/admin/breadcrumbs/collections/9999/delete')->getStatusCode());
    }

    public function testDeleteGoesBackToTheListPageTheUserCameFrom(): void
    {
        $this->boot();
        [$collection] = $this->seed();
        $request = Request::create('http://localhost/admin/breadcrumbs/collections/'.$collection->getId().'/delete', 'POST', ['confirm' => '1', '_token' => 'accepted']);
        $request->headers->set('Referer', 'http://localhost/admin/breadcrumbs/collections?q=main');

        $response = $this->handle($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('http://localhost/admin/breadcrumbs/collections?q=main', $response->headers->get('Location'));
    }

    public function testItemListSearchAndPagination(): void
    {
        $this->boot(['web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs', 'pagination' => ['enabled' => true, 'per_page' => 1]]]);
        [$collection] = $this->seed();
        $base = '/admin/breadcrumbs/collections/'.$collection->getId().'/items';

        self::assertSame(200, $this->get($base)->getStatusCode());
        self::assertSame(200, $this->get($base.'?q=home&page=2')->getStatusCode());
        self::assertSame(200, $this->get($base.'?q=zzzz')->getStatusCode());
        self::assertSame(404, $this->get('/admin/breadcrumbs/collections/9999/items')->getStatusCode());
    }

    public function testItemListWithoutPagination(): void
    {
        $this->boot(['web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs', 'pagination' => ['enabled' => false]]]);
        [$collection] = $this->seed();

        $response = $this->get('/admin/breadcrumbs/collections/'.$collection->getId().'/items');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('app_home', (string) $response->getContent());
    }

    public function testItemFormsPartialsFullPagesAndGuards(): void
    {
        $this->boot();
        [$collection, , $item, , $foreignItem] = $this->seed();
        $base = '/admin/breadcrumbs/collections/'.$collection->getId().'/items';
        $itemId = (int) $item->getId();

        self::assertSame(200, $this->get($base.'/new?_partial=1')->getStatusCode());
        self::assertSame(200, $this->get($base.'/new')->getStatusCode());
        self::assertSame(200, $this->get($base.'/'.$itemId.'/edit?_partial=1')->getStatusCode());
        $page = $this->get($base.'/'.$itemId.'/edit');
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('XX: Extra', (string) $page->getContent(), 'translations of locales that are not tabs are listed');

        self::assertSame(404, $this->get($base.'/9999/edit')->getStatusCode());
        self::assertSame(404, $this->get($base.'/'.$foreignItem->getId().'/edit')->getStatusCode(), 'an item of another collection');
        self::assertSame(404, $this->get('/admin/breadcrumbs/collections/9999/items/new')->getStatusCode());
    }

    public function testInvalidItemFormsAnswer422AndCreateRedirectsToTheReferer(): void
    {
        $this->boot();
        [$collection, , $item] = $this->seed();
        $base = '/admin/breadcrumbs/collections/'.$collection->getId().'/items';

        self::assertSame(422, $this->post($base.'/new', ['breadcrumb_item' => ['routeName' => str_repeat('r', 256), 'label' => 'x']])->getStatusCode());
        self::assertSame(422, $this->post($base.'/'.$item->getId().'/edit', ['breadcrumb_item' => ['routeName' => str_repeat('r', 256)]])->getStatusCode());

        $request = Request::create('http://localhost'.$base.'/new', 'POST', ['breadcrumb_item' => ['routeName' => 'blank_tr', 'label' => 'B', 'linkEnabled' => '1', 'translations' => ['en' => ''], '_token' => 'accepted'], '_token' => 'accepted']);
        $request->headers->set('Referer', 'http://localhost/back');
        $created = $this->handle($request);
        self::assertSame(302, $created->getStatusCode(), (string) $created->getContent());
        self::assertSame('http://localhost/back', $created->headers->get('Location'));
    }

    public function testItemDeleteConfirmationInvalidTokenAndGuards(): void
    {
        $this->boot();
        [$collection, , $item, , $foreignItem] = $this->seed();
        $base = '/admin/breadcrumbs/collections/'.$collection->getId().'/items';

        self::assertSame(200, $this->get($base.'/'.$item->getId().'/delete')->getStatusCode());
        $rejected = $this->handle(Request::create($base.'/'.$item->getId().'/delete', 'POST', ['confirm' => '1', '_token' => 'rejected']));
        self::assertSame(302, $rejected->getStatusCode());
        self::assertSame(2, (int) $this->manager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item WHERE collection_id = '.$collection->getId()));

        self::assertSame(404, $this->get($base.'/'.$foreignItem->getId().'/delete')->getStatusCode());
        self::assertSame(404, $this->get($base.'/9999/delete')->getStatusCode());
    }

    public function testExportAllAndTheImportPages(): void
    {
        $this->boot();
        $this->seed();

        $export = $this->get('/admin/breadcrumbs/export');
        self::assertSame('breadcrumb-kit-export.json', preg_replace('/^attachment; filename="(.*)"$/', '$1', (string) $export->headers->get('Content-Disposition')));
        self::assertArrayHasKey('collections', json_decode($this->body($export), true, 512, \JSON_THROW_ON_ERROR));

        self::assertSame(200, $this->get('/admin/breadcrumbs/import')->getStatusCode());
        self::assertSame(200, $this->get('/admin/breadcrumbs/import?_partial=1')->getStatusCode());
    }

    public function testImportCoversEveryOutcome(): void
    {
        $this->boot(['web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs', 'import_max_bytes' => 1024]]);
        $this->seed();
        $valid = json_encode(['collections' => [['collection' => ['code' => 'imported', 'contextKey' => ''], 'items' => [['routeName' => 'imp_home', 'label' => 'Imp']]]]], \JSON_THROW_ON_ERROR);

        $ok = $this->import($valid, 'skip_existing');
        self::assertSame(302, $ok->getStatusCode(), (string) $ok->getContent());
        self::assertSame(1, (int) $this->manager->getConnection()->fetchOne("SELECT COUNT(*) FROM dashboard_breadcrumb_collection WHERE code = 'imported'"));

        $replace = $this->import($valid, 'replace');
        self::assertSame(302, $replace->getStatusCode());

        self::assertSame(422, $this->import($valid, 'surprise')->getStatusCode(), 'an unknown strategy is not a valid choice');

        $failing = $this->import(json_encode(['collections' => [['collection' => ['contextKey' => ''], 'items' => []]]], \JSON_THROW_ON_ERROR), 'skip_existing');
        self::assertSame(302, $failing->getStatusCode());

        self::assertSame(200, $this->import(str_repeat(' ', 2048).'{}', 'skip_existing', true)->getStatusCode(), 'a file over the limit shows the form again');
        self::assertSame(200, $this->import(str_repeat(' ', 2048).'{}', 'skip_existing', false)->getStatusCode());
        self::assertSame(200, $this->import('{not json', 'skip_existing')->getStatusCode(), 'invalid JSON shows the form again');
        self::assertSame(302, $this->import('"just a string"', 'skip_existing')->getStatusCode(), 'a scalar root is refused');
        self::assertSame(302, $this->import('[1,2]', 'skip_existing')->getStatusCode(), 'a list root is refused');
        self::assertSame(302, $this->import('{"foo":1}', 'skip_existing')->getStatusCode(), 'an unknown shape is refused');
        self::assertSame(302, $this->import('{"collection":{"code":"single","contextKey":""},"items":[]}', 'skip_existing')->getStatusCode());

        $noFile = $this->handle($this->importRequest(null, 'skip_existing'));
        self::assertSame(422, $noFile->getStatusCode(), 'a submission without a file is invalid');
        $modalNoFile = $this->handle($this->importRequest(null, 'skip_existing', ['_modal' => '1']));
        self::assertSame(422, $modalNoFile->getStatusCode());
    }

    /**
     * @param array<string, string> $extra
     */
    private function importRequest(?string $json, string $strategy, array $extra = []): Request
    {
        $files = [];
        if (null !== $json) {
            $path = sys_get_temp_dir().'/breadcrumb-import-'.getmypid().'-'.hash('xxh128', $json).'.json';
            file_put_contents($path, $json);
            $this->files[] = $path;
            $files = ['import_breadcrumb' => ['file' => new UploadedFile($path, 'import.json', 'application/json', null, true)]];
        }

        return Request::create('/admin/breadcrumbs/import', 'POST', ['import_breadcrumb' => ['strategy' => $strategy, '_token' => 'accepted'], '_token' => 'accepted'] + $extra, [], $files);
    }

    private function import(string $json, string $strategy, bool $modal = false): Response
    {
        return $this->handle($this->importRequest($json, $strategy, $modal ? ['_modal' => '1'] : []));
    }

    /**
     * @return list<BreadcrumbCollection|BreadcrumbItem>
     */
    private function seed(): array
    {
        $main = (new BreadcrumbCollection())->setCode('main');
        $shop = (new BreadcrumbCollection())->setCode('shop')->setContextKey('tenant 1')->setName('Shop');
        $second = (new BreadcrumbCollection())->setCode('second');
        $home = (new BreadcrumbItem())->setCollection($main)->setRouteName('app_home')->setLabel('Home')->setTranslations(['en' => 'Home', 'xx' => 'Extra']);
        $about = (new BreadcrumbItem())->setCollection($main)->setRouteName('app_about')->setLabel('About');
        $foreign = (new BreadcrumbItem())->setCollection($second)->setRouteName('app_other')->setLabel('Other');
        foreach ([$main, $shop, $second, $home, $about, $foreign] as $entity) {
            $this->manager->persist($entity);
        }
        $this->manager->flush();
        $this->manager->clear();

        return [$main, $second, $home, $about, $foreign];
    }

    private function collectionId(string $code, string $contextKey): int
    {
        return (int) $this->manager->getConnection()->fetchOne('SELECT id FROM dashboard_breadcrumb_collection WHERE code = ? AND context_key = ?', [$code, $contextKey]);
    }

    private function body(Response $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    private function get(string $uri): Response
    {
        return $this->handle(Request::create($uri, 'GET'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function post(string $uri, array $data): Response
    {
        foreach ($data as $name => $fields) {
            $data[$name] = \is_array($fields) ? $fields + ['_token' => 'accepted'] : $fields;
        }
        $data += ['_token' => 'accepted'];

        return $this->handle(Request::create($uri, 'POST', $data));
    }

    private function handle(Request $request): Response
    {
        \assert(null !== $this->kernel);

        return $this->kernel->handle($request);
    }

    /**
     * @param array<string, mixed> $breadcrumbKit
     */
    private function boot(array $breadcrumbKit = []): void
    {
        $file = \sprintf('%s/nowo_breadcrumb_kit_reads_%d_%s.sqlite', sys_get_temp_dir(), (int) getmypid(), hash('xxh128', (string) $this->name().serialize($breadcrumbKit)));
        if (is_file($file)) {
            unlink($file);
        }
        $this->files[] = $file;
        $this->kernel = new EntityManagerTestKernel(['dbal' => ['url' => 'sqlite:///'.$file], 'orm' => ['auto_mapping' => true]], $breadcrumbKit, false, true);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $this->manager = $manager;
    }

    public function testADuplicateKeyOnEditShowsTheFormAgain(): void
    {
        $this->boot();
        [$main, $second] = $this->seed();

        $response = $this->post('/admin/breadcrumbs/collections/'.$main->getId().'/edit', ['breadcrumb_collection' => ['code' => 'second', 'contextKey' => '']]);

        self::assertSame(200, $response->getStatusCode(), 'the form again, with the "duplicate" flash');
        self::assertSame('main', $this->manager->getConnection()->fetchOne('SELECT code FROM dashboard_breadcrumb_collection WHERE id = '.$main->getId()));
        self::assertNotNull($second->getId());
    }

    public function testAStorageFailureThatIsNotADuplicateIsNotSwallowed(): void
    {
        $this->boot();
        [$main] = $this->seed();
        $this->manager->getConnection()->executeStatement("CREATE TRIGGER no_insert BEFORE INSERT ON dashboard_breadcrumb_collection BEGIN SELECT RAISE(ABORT, 'storage down'); END");
        $this->manager->getConnection()->executeStatement("CREATE TRIGGER no_update BEFORE UPDATE ON dashboard_breadcrumb_collection BEGIN SELECT RAISE(ABORT, 'storage down'); END");

        $created = $this->post('/admin/breadcrumbs/collections/new', ['breadcrumb_collection' => ['code' => 'fresh', 'contextKey' => '']]);
        self::assertSame(500, $created->getStatusCode());

        $edited = $this->post('/admin/breadcrumbs/collections/'.$main->getId().'/edit', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '', 'name' => 'Renamed']]);
        self::assertSame(500, $edited->getStatusCode());
        self::assertSame(0, $this->manager->getConnection()->getTransactionNestingLevel());
    }

    public function testEditingACollectionDeletedMeanwhileAnswers404FromTheLock(): void
    {
        $this->boot();
        [$main] = $this->seed();
        $id = (int) $main->getId();
        // Another request deleted the row after this process loaded it (the identity map still holds it).
        self::assertNotNull($this->manager->find(BreadcrumbCollection::class, $id));
        $this->manager->getConnection()->executeStatement('DELETE FROM dashboard_breadcrumb_item');
        $this->manager->getConnection()->executeStatement('DELETE FROM dashboard_breadcrumb_collection WHERE id = '.$id);

        $response = $this->post('/admin/breadcrumbs/collections/'.$id.'/edit', ['breadcrumb_collection' => ['code' => 'main', 'contextKey' => '', 'name' => 'Late']]);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testTheCollectionFormOffersTheConfiguredInlineEditAccessKeys(): void
    {
        $this->boot(['web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs'], 'inline_edit' => ['access_services' => ['admin' => 'router', 'editor' => 'router']]]);

        $response = $this->get('/admin/breadcrumbs/collections/new');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('editor', (string) $response->getContent());
    }

    public function testBlankTranslationsAreDroppedOnSave(): void
    {
        $this->boot(['web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs'], 'locales' => ['en', 'es']]);
        [$main] = $this->seed();
        $base = '/admin/breadcrumbs/collections/'.$main->getId().'/items';

        $mixed = $this->post($base.'/new', ['breadcrumb_item' => ['routeName' => 'mixed', 'linkEnabled' => '1', 'translations' => ['en' => 'Hello', 'es' => '']]]);
        self::assertSame(302, $mixed->getStatusCode(), (string) $mixed->getContent());
        self::assertSame('{"en":"Hello"}', $this->manager->getConnection()->fetchOne("SELECT translations FROM dashboard_breadcrumb_item WHERE routeName = 'mixed'"));

        $blank = $this->post($base.'/new', ['breadcrumb_item' => ['routeName' => 'blank', 'linkEnabled' => '1', 'translations' => ['en' => '', 'es' => '']]]);
        self::assertSame(302, $blank->getStatusCode(), (string) $blank->getContent());
        self::assertNull($this->manager->getConnection()->fetchOne("SELECT translations FROM dashboard_breadcrumb_item WHERE routeName = 'blank'"));
    }

    /**
     * The form only offers the two strategies, so a different value never reaches the controller through HTTP; the
     * controller still falls back to skip_existing (defence in depth), checked here with a form that returns one.
     */
    public function testAStrategyOutsideTheKnownOnesFallsBackToSkipExisting(): void
    {
        $this->boot();
        $this->seed();
        $path = sys_get_temp_dir().'/breadcrumb-fallback-'.getmypid().'.json';
        file_put_contents($path, '{"collections":[{"collection":{"code":"fallback","contextKey":""},"items":[]}]}');
        $this->files[] = $path;

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['file' => new UploadedFile($path, 'import.json', 'application/json', null, true), 'strategy' => 'unknown']);
        $factory = $this->createMock(FormFactoryInterface::class);
        $factory->method('create')->willReturn($form);

        $container = $this->kernel?->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $controller = $container->get(ImportExportController::class);
        self::assertInstanceOf(ImportExportController::class, $controller);
        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $request = Request::create('/admin/breadcrumbs/import', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);
        $controller->setContainer(new ServiceLocator([
            'form.factory' => static fn (): FormFactoryInterface => $factory,
            'router' => static fn (): mixed => $container->get('router'),
            'request_stack' => static fn (): mixed => $requestStack,
        ]));

        $response = $controller->import($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(1, (int) $this->manager->getConnection()->fetchOne("SELECT COUNT(*) FROM dashboard_breadcrumb_collection WHERE code = 'fallback'"));
    }
}
