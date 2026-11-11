<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabel;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabelProviderInterface;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 2.17 / audit MF-M1: the page label only names the current crumb of the trail built for its own route.
 */
final class BreadcrumbLoaderCurrentLabelTest extends TestCase
{
    private RequestStack $stack;

    private BreadcrumbCurrentLabel $label;

    protected function setUp(): void
    {
        $this->stack = new RequestStack();
        $this->stack->push($this->routed('wiki_page', ['id' => '7']));
        $this->label = new BreadcrumbCurrentLabel($this->stack);
    }

    public function testRouteItemGetsTheLabel(): void
    {
        $this->label->setCurrentLabel('Getting started');

        self::assertSame(['Home', 'Getting started'], $this->labels($this->loader($this->routeRows()), 'default'));
    }

    public function testGenericPathPatternItemKeepsItsSectionLabel(): void
    {
        $collection = $this->collection('default');
        $home = $this->item(1, $collection, null, 'home', 'Home');
        $section = $this->item(2, $collection, $home, '*', 'Wiki');
        $section->setPathPattern('^/wiki');

        $this->label->setCurrentLabel('Getting started');

        self::assertSame(['Home', 'Wiki'], $this->labels($this->loader([$collection, [$home, $section]]), 'default'));
    }

    public function testOtherCollectionKeepsItsLabelWhenTheLabelIsRestricted(): void
    {
        $this->label->setCurrentLabel('Getting started', null, [], 'default');

        $loader = $this->loader($this->routeRows('sidebar'));
        self::assertSame(['Home', 'Page'], $this->labels($loader, 'sidebar'));
        self::assertSame(['Home', 'Getting started'], $this->labels($this->loader($this->routeRows()), 'default'));
    }

    public function testFragmentWithAnotherRouteKeepsItsLabel(): void
    {
        $this->label->setCurrentLabel('Getting started');
        // render(path('wiki_page', {id: 8})) or an ESI fragment of another page.
        $this->stack->push($this->routed('wiki_page', ['id' => '8']));

        self::assertSame(['Home', 'Page'], $this->labels($this->loader($this->routeRows()), 'default'));
    }

    public function testCustomProviderIsAsked(): void
    {
        $provider = new class implements BreadcrumbCurrentLabelProviderInterface {
            public function currentLabelFor(string $routeName, array $routeParams, string $collectionCode, string $contextKey, ?string $locale = null): string
            {
                return $routeName.'/'.json_encode($routeParams).'/'.$collectionCode.'/'.$contextKey.'/'.$locale;
            }
        };

        self::assertSame(['Home', 'wiki_page/{"id":"7"}/default//en'], $this->labels($this->loader($this->routeRows(), $provider), 'default'));
    }

    /**
     * @return array{BreadcrumbCollection, list<BreadcrumbItem>}
     */
    private function routeRows(string $code = 'default'): array
    {
        $collection = $this->collection($code);
        $home = $this->item(1, $collection, null, 'home', 'Home');

        return [$collection, [$home, $this->item(2, $collection, $home, 'wiki_page', 'Page')]];
    }

    /**
     * @param array{BreadcrumbCollection, list<BreadcrumbItem>} $rows
     */
    private function loader(array $rows, ?BreadcrumbCurrentLabelProviderInterface $provider = null): BreadcrumbLoader
    {
        $collections = $this->createMock(BreadcrumbCollectionRepository::class);
        $collections->method('findOneByCodeAndContextKey')->willReturn($rows[0]);
        $items = $this->createMock(BreadcrumbItemRepository::class);
        $items->method('findAllForCollection')->willReturn($rows[1]);

        return new BreadcrumbLoader(
            $collections,
            $items,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $this->stack,
            'en',
            currentLabel: $provider ?? $this->label,
        );
    }

    /**
     * @return list<string>
     */
    private function labels(BreadcrumbLoader $loader, string $code): array
    {
        return array_map(static fn ($node): string => $node->label, $loader->loadTrailView($code)->nodes);
    }

    /**
     * @param array<string, string> $params
     */
    private function routed(string $route, array $params): Request
    {
        $request = Request::create('/wiki/pages/'.($params['id'] ?? ''));
        $request->attributes->set('_route', $route);
        $request->attributes->set('_route_params', $params);

        return $request;
    }

    private function collection(string $code): BreadcrumbCollection
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode($code);
        $collection->setContextKey('');
        (new ReflectionProperty(BreadcrumbCollection::class, 'id'))->setValue($collection, 'default' === $code ? 1 : 2);

        return $collection;
    }

    private function item(int $id, BreadcrumbCollection $collection, ?BreadcrumbItem $parent, string $route, string $label): BreadcrumbItem
    {
        $item = new BreadcrumbItem();
        $item->setCollection($collection);
        $item->setParent($parent);
        $item->setRouteName($route);
        $item->setLabel($label);
        $item->setLinkEnabled(false);
        (new ReflectionProperty(BreadcrumbItem::class, 'id'))->setValue($item, $id);

        return $item;
    }
}
