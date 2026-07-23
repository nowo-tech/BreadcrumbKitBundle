<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbInlineEditAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\DataCollector\BreadcrumbDataCollector;
use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonObjectTransformer;
use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonStringListTransformer;
use Nowo\BreadcrumbKitBundle\Profiler\BreadcrumbProfilerRecorder;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CoverageCompletionTest extends TestCase
{
    public function testDataCollectorNormalizesNonArrayTrailsAttribute(): void
    {
        $request = Request::create('/');
        $request->attributes->set(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE, 'invalid');

        $collector = new BreadcrumbDataCollector();
        $collector->collect($request, new Response());

        self::assertSame([], $collector->getTrails());
    }

    public function testProfilerRecorderResetsNonArrayLog(): void
    {
        $request = Request::create('/');
        $request->attributes->set(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE, 'invalid');
        $stack = new RequestStack();
        $stack->push($request);

        (new BreadcrumbProfilerRecorder($stack, debug: true))->record(
            'default',
            '',
            new BreadcrumbTrailView([]),
            'ok',
        );

        $log = $request->attributes->get(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE);
        self::assertIsArray($log);
        self::assertCount(1, $log);
    }

    public function testTwigPathsPassUsesNativeLoaderDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/tmp');
        $loaderDef = new Definition();
        $container->setDefinition('twig.loader.native', $loaderDef);

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($loaderDef->getMethodCalls());
    }

    public function testUrlResolverMergesDynamicKeyNotInPathVariables(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('search', ['q' => 'books'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/search?q=books');

        $route = new Route('/search');
        $collection = new RouteCollection();
        $collection->add('search', $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $request = Request::create('/search?q=books');
        $request->attributes->set('_route_params', ['q' => 'books']);
        $stack = new RequestStack();
        $stack->push($request);

        [$url, $params] = (new BreadcrumbUrlResolver($urlGenerator, $stack, $router))
            ->resolve('search', [], ['q']);

        self::assertSame('/search?q=books', $url);
        self::assertSame(['q' => 'books'], $params);
    }

    public function testJsonObjectTransformerRejectsScalarJson(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Se esperaba un objeto JSON');

        (new JsonObjectTransformer())->reverseTransform('"hello"');
    }

    public function testJsonObjectTransformerReturnsEmptyStringWhenJsonEncodeFails(): void
    {
        $bad = ['bad' => "\xB1\x31"];
        self::assertSame('', (new JsonObjectTransformer())->transform($bad));
    }

    public function testJsonStringListTransformerRejectsScalarJson(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Se esperaba un array JSON');

        (new JsonStringListTransformer())->reverseTransform('"hello"');
    }

    public function testJsonStringListTransformerReturnsEmptyStringWhenJsonEncodeFails(): void
    {
        $bad = ["\xB1\x31"];
        self::assertSame('', (new JsonStringListTransformer())->transform($bad));
    }

    public function testExtensionLoadUsesEmptyLayoutFallbackAndCachePool(): void
    {
        $container = new ContainerBuilder();
        $container->register('cache.app', Definition::class)->setPublic(true);

        (new BreadcrumbKitExtension())->load([
            [
                'dashboard' => ['layout_template' => '   '],
                'cache' => ['pool' => 'cache.app', 'ttl' => 90],
            ],
        ], $container);

        self::assertSame(
            '@NowoBreadcrumbKitBundle/dashboard/layout.html.twig',
            $container->getParameter('nowo_breadcrumb_kit.dashboard.layout_template'),
        );
        $cachePool = $container->getDefinition(BreadcrumbLoader::class)->getArgument('$cachePool');
        self::assertInstanceOf(Reference::class, $cachePool);
        self::assertSame('cache.app', (string) $cachePool);
    }

    public function testLoaderSkipsInvalidCachePayload(): void
    {
        $collection = $this->collectionWithId(1);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);

        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->expects(self::once())->method('findAllForCollection')->willReturn([]);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn(['invalid' => 'rows']);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturn($cacheItem);

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
            60,
        );

        self::assertSame([], $loader->loadTrailView('default', '')->nodes);
    }

    public function testLoaderFindMatchingItemReturnsNullForMissingCollectionId(): void
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            new RequestStack(),
            'en',
            null,
            60,
        );

        self::assertNull($loader->findMatchingItemForCurrentRequest('default'));
    }

    public function testLoaderFindMatchingItemReturnsNullWhenRouteMissing(): void
    {
        $collection = $this->collectionWithId(1);
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);

        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        $loader = new BreadcrumbLoader(
            $colRepo,
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
        );

        self::assertNull($loader->findMatchingItemForCurrentRequest('default'));
    }

    public function testLoaderFindMatchingItemReturnsNullForInvalidMatchedId(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(0, $collection, null, 'home', 'Home');

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
            null,
            60,
        );

        self::assertNull($loader->findMatchingItemForCurrentRequest('default'));
    }

    public function testLoaderStaticParamsMismatchReturnsEmptyTrail(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'page', 'Page');
        $item->setStaticRouteParams(['id' => '1']);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $request = Request::create('/page/2');
        $request->attributes->set('_route', 'page');
        $request->attributes->set('_route_params', ['id' => 2]);
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

        self::assertSame([], $loader->loadTrailView('default', '')->nodes);
    }

    public function testLoaderUsesDynamicKeysFromRow(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'search', 'Search');
        $item->setLinkEnabled(true);
        // Dirty DB/JSON-like payload: castDynamicKeys keeps only strings.
        (new \ReflectionProperty($item, 'dynamicParamKeys'))->setValue($item, ['q', 5, '']);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $urlResolver = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $urlResolver->expects(self::once())
            ->method('resolve')
            ->with('search', [], self::callback(static fn (array $keys): bool => \in_array('q', $keys, true)))
            ->willReturn(['/search?q=x', ['q' => 'x']]);

        $request = Request::create('/search?q=x');
        $request->attributes->set('_route', 'search');
        $request->attributes->set('_route_params', ['q' => 'x']);
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

        self::assertSame('Search', $loader->loadTrailView('default', '')->nodes[0]->label);
    }

    public function testImporterHandlesCollectionBlockErrorsAndRichRows(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new BreadcrumbImporter(
            $colRepo,
            $this->createMock(BreadcrumbItemRepository::class),
            $em,
            $this->createMock(TranslatorInterface::class),
        );

        $result = $importer->import([
            'collections' => [
                ['collection' => 'not-array', 'items' => []],
                [
                    'collection' => [
                        'code' => 'demo',
                        'contextKey' => 123,
                        'responsiveConfig' => 'invalid',
                    ],
                    'items' => [
                        ['routeName' => 'home', 'staticRouteParams' => ['id' => '1', 2 => 'x'], 'dynamicParamKeys' => ['', 'slug'], 'translations' => ['en' => 'Home']],
                        ['routeName' => ''],
                        'not-array',
                    ],
                ],
            ],
        ]);

        self::assertSame(1, $result['created']);
        self::assertNotEmpty($result['errors']);
    }

    public function testImporterReportsMissingCollectionCodeInSingleImport(): void
    {
        $importer = new BreadcrumbImporter(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(TranslatorInterface::class),
        );

        $result = $importer->import([
            'collection' => ['code' => ''],
            'items' => [],
        ]);

        self::assertNotEmpty($result['errors']);
    }

    public function testInlineEditResolverReturnsEmptyWhenCollectionHasNoId(): void
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $collection->setInlineEditAccessKey('admin');

        $checker = new class implements BreadcrumbInlineEditAccessCheckerInterface {
            public function canUseInlineBreadcrumbEditor(Request $request, ?UserInterface $user): bool
            {
                return true;
            }
        };

        $locator = new class($checker) implements ContainerInterface {
            public function __construct(private readonly object $checker)
            {
            }

            public function get(string $id): object
            {
                return $this->checker;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $request = Request::create('/?edit=1');
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
        );

        $resolver = new BreadcrumbInlineEditResolver(
            $stack,
            $this->collectionRepo($collection),
            $loader,
            $locator,
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(TranslatorInterface::class),
            null,
            'edit',
            true,
        );

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testInlineEditResolverHandlesQueryParamEdgeCasesAndAnonymousToken(): void
    {
        $collection = $this->collectionWithId(1);
        $collection->setInlineEditAccessKey('admin');

        $checker = new class implements BreadcrumbInlineEditAccessCheckerInterface {
            public function canUseInlineBreadcrumbEditor(Request $request, ?UserInterface $user): bool
            {
                return $user instanceof UserInterface;
            }
        };

        $locator = new class($checker) implements ContainerInterface {
            public function __construct(private readonly object $checker)
            {
            }

            public function get(string $id): object
            {
                return $this->checker;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $stack = new RequestStack();
        $loader = new BreadcrumbLoader(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
        );

        $resolver = new BreadcrumbInlineEditResolver(
            $stack,
            $this->collectionRepo($collection),
            $loader,
            $locator,
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(TranslatorInterface::class),
            null,
            'edit',
            true,
        );

        $missingParamRequest = Request::create('/');
        self::assertFalse($this->invokeIsQueryParamTruthy($resolver, $missingParamRequest, 'edit'));

        $boolQuery = new InputBag();
        $parameters = new \ReflectionProperty(InputBag::class, 'parameters');
        $parameters->setValue($boolQuery, ['edit' => true]);
        $boolRequest = Request::create('/');
        $queryProperty = new \ReflectionProperty(Request::class, 'query');
        $queryProperty->setValue($boolRequest, $boolQuery);
        self::assertTrue($this->invokeIsQueryParamTruthy($resolver, $boolRequest, 'edit'));

        $request2 = Request::create('/?edit=1');
        $stack2 = new RequestStack();
        $stack2->push($request2);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $resolver2 = new BreadcrumbInlineEditResolver(
            $stack2,
            $this->collectionRepo($collection),
            $loader,
            $locator,
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(TranslatorInterface::class),
            $tokenStorage,
            'edit',
            true,
        );

        self::assertFalse($resolver2->resolve('default')->show);
    }

    public function testLoaderFindMatchingItemReturnsNullWhenCollectionMissingOrHasNoId(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);

        $request = Request::create('/');
        $request->attributes->set('_route', 'home');
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

        self::assertNull($loader->findMatchingItemForCurrentRequest('missing'));

        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $colRepo2 = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo2->method('findOneByCodeAndContextKey')->willReturn($collection);

        $loader2 = new BreadcrumbLoader(
            $colRepo2,
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
        );

        self::assertNull($loader2->findMatchingItemForCurrentRequest('default'));
    }

    public function testLoaderFindMatchingItemReturnsNullWhenNoBestMatch(): void
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

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
            null,
            60,
        );

        self::assertNull($loader->findMatchingItemForCurrentRequest('default'));
    }

    public function testLoaderReturnsEmptyWhenCollectionHasNoId(): void
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);

        $request = Request::create('/');
        $request->attributes->set('_route', 'home');
        $request->attributes->set('_route_params', []);
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

    public function testLoaderStaticParamsMismatchWhenRouteParamMissing(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'page', 'Page');
        $item->setStaticRouteParams(['id' => '1', 'slug' => 'x']);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $request = Request::create('/page/1');
        $request->attributes->set('_route', 'page');
        $request->attributes->set('_route_params', ['id' => 1]);
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

        self::assertSame([], $loader->loadTrailView('default', '')->nodes);
    }

    private function collectionRepo(BreadcrumbCollection $collection): BreadcrumbCollectionRepository
    {
        $repo = $this->createMock(BreadcrumbCollectionRepository::class);
        $repo->method('findOneByCodeAndContextKey')->willReturn($collection);

        return $repo;
    }

    private function invokeIsQueryParamTruthy(
        BreadcrumbInlineEditResolver $resolver,
        Request $request,
        string $param,
    ): bool {
        $method = new \ReflectionMethod(BreadcrumbInlineEditResolver::class, 'isQueryParamTruthy');

        return $method->invoke($resolver, $request, $param);
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
