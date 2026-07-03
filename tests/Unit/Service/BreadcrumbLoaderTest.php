<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Profiler\BreadcrumbProfilerRecorder;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class BreadcrumbLoaderTest extends TestCase
{
    public function testLoadTrailViewBuildsChainFromBestRouteMatch(): void
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $collection->setContextKey('');
        $collection->setName('Main');

        $root = new BreadcrumbItem();
        $root->setCollection($collection);
        $root->setRouteName('app_home');
        $root->setStaticRouteParams([]);
        $root->setLabel('Home');
        $root->setLinkEnabled(true);

        $leaf = new BreadcrumbItem();
        $leaf->setCollection($collection);
        $leaf->setParent($root);
        $leaf->setRouteName('product_show');
        $leaf->setStaticRouteParams(['id' => '5']);
        $leaf->setLabel('Product');
        $leaf->setLinkEnabled(true);

        $ref = new \ReflectionProperty(BreadcrumbItem::class, 'id');
        $ref->setValue($root, 1);
        $ref->setValue($leaf, 2);

        $refC = new \ReflectionProperty(BreadcrumbCollection::class, 'id');
        $refC->setValue($collection, 10);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->with('default', '')->willReturn($collection);

        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->with($collection)->willReturn([$root, $leaf]);

        $urlResolver = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $urlResolver->expects(self::exactly(2))
            ->method('resolve')
            ->willReturnCallback(static fn (string $routeName): array => 'app_home' === $routeName ? ['/', []] : ['/p', []]);

        $request = Request::create('/product/5');
        $request->attributes->set('_route', 'product_show');
        $request->attributes->set('_route_params', ['id' => 5]);

        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $urlResolver,
            $stack,
            'en',
            null,
            60,
        );

        $view = $loader->loadTrailView('default', '');
        self::assertCount(2, $view->nodes);
        self::assertSame('Home', $view->nodes[0]->label);
        self::assertSame('/', $view->nodes[0]->url);
        self::assertFalse($view->nodes[0]->current);
        self::assertSame('Product', $view->nodes[1]->label);
        self::assertNull($view->nodes[1]->url);
        self::assertTrue($view->nodes[1]->current);
    }

    public function testLoadTrailViewReturnsEmptyWhenNoCollection(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $urlResolver = $this->createMock(BreadcrumbUrlResolverInterface::class);

        $request = Request::create('/');
        $request->attributes->set('_route', 'x');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader($colRepo, $itemRepo, $urlResolver, $stack, 'en', null, 60);
        $view = $loader->loadTrailView('missing', '');

        self::assertSame([], $view->nodes);
    }

    public function testPicksMoreSpecificStaticParams(): void
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $collection->setContextKey('');

        $generic = new BreadcrumbItem();
        $generic->setCollection($collection);
        $generic->setRouteName('catalog');
        $generic->setStaticRouteParams([]);
        $generic->setLabel('Catalog');
        $generic->setLinkEnabled(true);

        $specific = new BreadcrumbItem();
        $specific->setCollection($collection);
        $specific->setRouteName('catalog');
        $specific->setStaticRouteParams(['section' => 'books']);
        $specific->setLabel('Books');
        $specific->setLinkEnabled(true);

        $ref = new \ReflectionProperty(BreadcrumbItem::class, 'id');
        $ref->setValue($generic, 1);
        $ref->setValue($specific, 2);

        $refC = new \ReflectionProperty(BreadcrumbCollection::class, 'id');
        $refC->setValue($collection, 1);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$generic, $specific]);

        $urlResolver = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $urlResolver->method('resolve')->willReturn(['/c', []]);

        $request = Request::create('/catalog/books');
        $request->attributes->set('_route', 'catalog');
        $request->attributes->set('_route_params', ['section' => 'books']);

        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader($colRepo, $itemRepo, $urlResolver, $stack, 'en', null, 60);
        $view = $loader->loadTrailView('default', '');

        self::assertCount(1, $view->nodes);
        self::assertSame('Books', $view->nodes[0]->label);
    }

    public function testLoadTrailViewReturnsEmptyNodesWhenNoRequest(): void
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $collection->setContextKey('');
        $collection->setHomeIcon('home-icon');

        $refC = new \ReflectionProperty(BreadcrumbCollection::class, 'id');
        $refC->setValue($collection, 1);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            new RequestStack(),
            'en',
            null,
            60,
        );

        $view = $loader->loadTrailView('default', '');

        self::assertSame([], $view->nodes);
        self::assertSame('home-icon', $view->homeIcon);
    }

    public function testLoadTrailViewReturnsEmptyWhenNoRouteOnRequest(): void
    {
        $collection = $this->collectionWithId(1);
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);

        $request = Request::create('/');
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
        );

        self::assertSame([], $loader->loadTrailView('default', '')->nodes);
    }

    public function testLoadTrailViewUsesTranslationForLocale(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'page', null);
        $item->setTranslations(['es' => 'Página ES', 'en' => 'Page EN']);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $urlResolver = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $urlResolver->method('resolve')->willReturn([null, []]);

        $request = Request::create('/page');
        $request->setLocale('es');
        $request->attributes->set('_route', 'page');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader($colRepo, $itemRepo, $urlResolver, $stack, 'en', null, 60);
        $view = $loader->loadTrailView('default', '');

        self::assertSame('Página ES', $view->nodes[0]->label);
    }

    public function testLoadTrailViewFallsBackToDefaultLocaleTranslation(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'page', 'Plain');
        $item->setTranslations(['en' => 'Page EN']);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $urlResolver = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $urlResolver->method('resolve')->willReturn([null, []]);

        $request = Request::create('/page');
        $request->setLocale('fr');
        $request->attributes->set('_route', 'page');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader($colRepo, $itemRepo, $urlResolver, $stack, 'en', null, 60);
        $view = $loader->loadTrailView('default', '');

        self::assertSame('Page EN', $view->nodes[0]->label);
    }

    public function testLoadTrailViewUsesCacheHitAndSkipsRepository(): void
    {
        $collection = $this->collectionWithId(10);
        $cachedRows = [[
            'id' => 1,
            'parent_id' => null,
            'route_name' => 'home',
            'static_params' => [],
            'dynamic_keys' => null,
            'link_enabled' => false,
            'label' => 'Cached',
            'translations' => [],
            'icon' => null,
        ]];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($cachedRows);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())->method('getItem')->willReturn($cacheItem);
        $cachePool->expects(self::never())->method('save');

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);

        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->expects(self::never())->method('findAllForCollection');

        $request = Request::create('/');
        $request->attributes->set('_route', 'home');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            $cachePool,
            120,
        );

        $view = $loader->loadTrailView('default', '');

        self::assertSame('Cached', $view->nodes[0]->label);
    }

    public function testLoadTrailViewPopulatesCacheOnMiss(): void
    {
        $collection = $this->collectionWithId(10);
        $item = $this->itemWithId(1, $collection, null, 'home', 'Fresh');
        $item->setLinkEnabled(false);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->expects(self::once())->method('set')->with(self::isArray());
        $cacheItem->expects(self::once())->method('expiresAfter')->with(30);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::exactly(2))->method('getItem')->willReturn($cacheItem);
        $cachePool->expects(self::once())->method('save')->with($cacheItem);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $request = Request::create('/');
        $request->attributes->set('_route', 'home');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            $cachePool,
            30,
        );

        self::assertSame('Fresh', $loader->loadTrailView('default', '')->nodes[0]->label);
    }

    public function testFindMatchingItemForCurrentRequestReturnsEntity(): void
    {
        $collection = $this->collectionWithId(1);
        $entity = $this->itemWithId(42, $collection, null, 'page', 'Page');

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$entity]);
        $itemRepo->expects(self::once())->method('find')->with(42)->willReturn($entity);

        $request = Request::create('/page');
        $request->attributes->set('_route', 'page');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
        );

        self::assertSame($entity, $loader->findMatchingItemForCurrentRequest('default', ''));
    }

    public function testFindMatchingItemForCurrentRequestReturnsNullWithoutRequest(): void
    {
        $loader = new BreadcrumbLoader(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            new RequestStack(),
            'en',
            null,
            60,
        );

        self::assertNull($loader->findMatchingItemForCurrentRequest('default'));
    }

    public function testProfilerRecorderIsCalledOnLoad(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'home', 'Home');
        $item->setLinkEnabled(false);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $request = Request::create('/');
        $request->attributes->set('_route', 'home');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $profiler = new BreadcrumbProfilerRecorder($stack, debug: true);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
            $profiler,
        );

        $loader->loadTrailView('default', '');

        /** @var list<array<string, mixed>> $log */
        $log = $request->attributes->get(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE);
        self::assertCount(1, $log);
        self::assertSame('ok', $log[0]['status']);
        self::assertSame('home', $log[0]['matchedItemRoute']);
    }

    public function testLoadTrailViewReturnsEmptyWhenNoItemMatch(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'other', 'Other');

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $request = Request::create('/');
        $request->attributes->set('_route', 'home');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $profiler = new BreadcrumbProfilerRecorder($stack, debug: true);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
            $profiler,
        );

        self::assertSame([], $loader->loadTrailView('default', '')->nodes);

        /** @var list<array<string, mixed>> $log */
        $log = $request->attributes->get(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE);
        self::assertSame('no_item_match', $log[0]['status']);
    }

    public function testLoadTrailViewHidesSingleRootOnCurrentPageWhenConfigured(): void
    {
        $collection = $this->collectionWithId(1);
        $collection->setHomeIcon('⌂');
        $item = $this->itemWithId(1, $collection, null, 'app_home', 'Home');
        $item->setLinkEnabled(true);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $urlResolver = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $urlResolver->method('resolve')->willReturn(['/', []]);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $urlResolver,
            $stack,
            'en',
            null,
            60,
            null,
            true,
        );

        $view = $loader->loadTrailView('default', '');

        self::assertSame([], $view->nodes);
        self::assertSame('⌂', $view->homeIcon);
        self::assertTrue($view->homeIconReplacesLabel);
    }

    public function testLoadTrailViewKeepsSingleRootWhenHideDisabled(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'app_home', 'Home');
        $item->setLinkEnabled(false);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
            null,
            false,
        );

        self::assertCount(1, $loader->loadTrailView('default', '')->nodes);
    }

    private function collectionWithId(int $id): BreadcrumbCollection
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $collection->setContextKey('');

        $ref = new \ReflectionProperty(BreadcrumbCollection::class, 'id');
        $ref->setValue($collection, $id);

        return $collection;
    }

    private function itemWithId(
        int $id,
        BreadcrumbCollection $collection,
        ?BreadcrumbItem $parent,
        string $route,
        ?string $label,
    ): BreadcrumbItem {
        $item = new BreadcrumbItem();
        $item->setCollection($collection);
        $item->setParent($parent);
        $item->setRouteName($route);
        $item->setLabel($label);

        $ref = new \ReflectionProperty(BreadcrumbItem::class, 'id');
        $ref->setValue($item, $id);

        return $item;
    }
}
