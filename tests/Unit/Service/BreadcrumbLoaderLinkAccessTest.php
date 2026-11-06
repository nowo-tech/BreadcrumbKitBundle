<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Closure;
use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbLinkAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * BK-1 (link_access_checker) and BK-2 (parameter mapping) at the loader, with a stub URL resolver.
 */
final class BreadcrumbLoaderLinkAccessTest extends TestCase
{
    public function testWithoutACheckerEveryCrumbLinks(): void
    {
        $view = $this->trail(null);

        self::assertSame('/home', $view->nodes[0]->url);
        self::assertSame('/parent', $view->nodes[1]->url);
    }

    public function testRefusedCrumbKeepsItsLabelAndLosesItsLink(): void
    {
        $checker = $this->checker(static fn (string $route): bool => 'parent' !== $route);

        $view = $this->trail($checker);

        self::assertSame(['Home', 'Parent', 'Leaf'], array_map(static fn ($n): string => $n->label, $view->nodes));
        self::assertSame('/home', $view->nodes[0]->url);
        self::assertNull($view->nodes[1]->url);
        self::assertTrue($view->nodes[1]->linkEnabled);
    }

    public function testCheckerGetsTheMergedParamsAndIsNotAskedAboutCrumbsThatWouldNotLink(): void
    {
        $asked = [];
        $checker = $this->checker(static function (string $route, array $params) use (&$asked): bool {
            $asked[$route] = $params;

            return true;
        });
        $unlinked = (new BreadcrumbItem())->setRouteName('unlinked')->setLabel('Unlinked')->setLinkEnabled(false);
        $broken = (new BreadcrumbItem())->setRouteName('broken')->setLabel('Broken');

        $view = $this->trail($checker, [$unlinked, $broken]);

        self::assertSame(['home', 'parent'], array_keys($asked), 'not the disabled crumb, the unresolvable one or the current page');
        self::assertSame(['merged' => 'home'], $asked['home']);
        self::assertNull($view->nodes[3]->url, 'the crumb whose URL cannot be generated');
    }

    public function testTheAnswerIsNotKeptBetweenTrails(): void
    {
        $allow = true;
        $checker = $this->checker(static function () use (&$allow): bool {
            return $allow;
        });
        $loader = $this->loader($checker, []);

        self::assertSame('/parent', $loader->loadTrailView('main')->nodes[1]->url);
        $allow = false;
        self::assertNull($loader->loadTrailView('main')->nodes[1]->url);
    }

    public function testMappedParamsReachTheResolverAsStaticOnesAndPlainKeysStayPlain(): void
    {
        $calls = [];
        $resolver = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $resolver->method('resolve')->willReturnCallback(static function (string $route, array $static, ?array $keys) use (&$calls): array {
            $calls[$route] = [$static, $keys];

            return ['/'.$route, $static];
        });
        $parent = (new BreadcrumbItem())->setRouteName('parent')->setLabel('Parent')
            ->setDynamicParamKeys(['slug', 'id=@projectId', 'static=@projectId', 'gone=@missing']);
        $parent->setStaticRouteParams(['static' => 'fixed']);

        $this->view($this->loaderWith($resolver, null, [$parent]));

        self::assertSame([['static' => 'fixed', 'id' => 'p1'], ['slug']], $calls['parent'], 'a static value wins, a missing source adds nothing');
    }

    public function testRowsWithoutMappingsCallTheResolverExactlyAsBefore(): void
    {
        $calls = [];
        $resolver = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $resolver->method('resolve')->willReturnCallback(static function (string $route, array $static, ?array $keys) use (&$calls): array {
            $calls[$route] = [$static, $keys];

            return ['/'.$route, $static];
        });
        $plain = (new BreadcrumbItem())->setRouteName('plain')->setLabel('Plain')->setDynamicParamKeys(['id']);
        $none = (new BreadcrumbItem())->setRouteName('none')->setLabel('None');

        $this->view($this->loaderWith($resolver, null, [$plain, $none]));

        self::assertSame([[], ['id']], $calls['plain']);
        self::assertSame([[], null], $calls['none']);
    }

    private function view(BreadcrumbLoader $loader): BreadcrumbTrailView
    {
        return $loader->loadTrailView('main');
    }

    private function checker(callable $answer): BreadcrumbLinkAccessCheckerInterface
    {
        return new class($answer(...)) implements BreadcrumbLinkAccessCheckerInterface {
            public function __construct(private readonly Closure $answer)
            {
            }

            public function canLink(string $route, array $params): bool
            {
                return ($this->answer)($route, $params);
            }
        };
    }

    /**
     * @param list<BreadcrumbItem> $extra crumbs between the parent and the leaf
     */
    private function trail(?BreadcrumbLinkAccessCheckerInterface $checker, array $extra = []): BreadcrumbTrailView
    {
        return $this->loader($checker, $extra)->loadTrailView('main');
    }

    /**
     * @param list<BreadcrumbItem> $extra
     */
    private function loader(?BreadcrumbLinkAccessCheckerInterface $checker, array $extra): BreadcrumbLoader
    {
        $resolver = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $resolver->method('resolve')->willReturnCallback(
            static fn (string $route): array => 'broken' === $route ? [null, []] : ['/'.$route, ['merged' => $route]],
        );

        return $this->loaderWith($resolver, $checker, $extra);
    }

    /**
     * Trail: home > parent > $extra… > leaf (the current page); $extra may hold its own `parent` item.
     *
     * @param list<BreadcrumbItem> $extra
     */
    private function loaderWith(BreadcrumbUrlResolverInterface $resolver, ?BreadcrumbLinkAccessCheckerInterface $checker, array $extra): BreadcrumbLoader
    {
        $collection = (new BreadcrumbCollection())->setCode('main');
        (new ReflectionProperty(BreadcrumbCollection::class, 'id'))->setValue($collection, 1);

        $chain = [
            (new BreadcrumbItem())->setRouteName('home')->setLabel('Home'),
        ];
        $hasParent = array_filter($extra, static fn (BreadcrumbItem $i): bool => 'parent' === $i->getRouteName());
        if ([] === $hasParent) {
            $chain[] = (new BreadcrumbItem())->setRouteName('parent')->setLabel('Parent');
        }
        foreach ($extra as $item) {
            $chain[] = $item;
        }
        $chain[] = (new BreadcrumbItem())->setRouteName('leaf')->setLabel('Leaf');

        $id = new ReflectionProperty(BreadcrumbItem::class, 'id');
        foreach ($chain as $i => $item) {
            $id->setValue($item, $i + 1);
            $item->setCollection($collection);
            $item->setParent($chain[$i - 1] ?? null);
        }

        $collections = $this->createMock(BreadcrumbCollectionRepository::class);
        $collections->method('findOneByCodeAndContextKey')->willReturn($collection);
        $items = $this->createMock(BreadcrumbItemRepository::class);
        $items->method('findAllForCollection')->willReturn($chain);

        $request = Request::create('/leaf');
        $request->attributes->set('_route', 'leaf');
        $request->attributes->set('_route_params', ['projectId' => 'p1']);
        $request->attributes->set('projectId', 'p1');
        $stack = new RequestStack();
        $stack->push($request);

        return new BreadcrumbLoader(
            $collections,
            $items,
            $resolver,
            $stack,
            'en',
            linkAccessChecker: $checker,
        );
    }
}
