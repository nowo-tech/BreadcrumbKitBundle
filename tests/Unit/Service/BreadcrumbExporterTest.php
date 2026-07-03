<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbExporter;
use PHPUnit\Framework\TestCase;

final class BreadcrumbExporterTest extends TestCase
{
    public function testExportCollectionBuildsNestedTreeWithoutEntityIds(): void
    {
        $collection = $this->makeCollection(10, 'main', 'ctx', 'Main Trail');
        $root = $this->makeItem(1, $collection, null, 'app_home', 'Home');
        $child = $this->makeItem(2, $collection, $root, 'product_show', 'Product');
        $child->setStaticRouteParams(['id' => '5']);
        $child->setDynamicParamKeys(['slug']);
        $child->setTranslations(['en' => 'Product EN']);
        $child->setIcon('box');
        $child->setLinkEnabled(false);

        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->expects(self::once())
            ->method('findAllForCollection')
            ->with($collection)
            ->willReturn([$root, $child]);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);

        $exporter = new BreadcrumbExporter($colRepo, $itemRepo);
        $result = $exporter->exportCollection($collection);

        self::assertSame('main', $result['collection']['code']);
        self::assertSame('ctx', $result['collection']['contextKey']);
        self::assertSame('Main Trail', $result['collection']['name']);
        self::assertCount(1, $result['items']);
        self::assertSame('app_home', $result['items'][0]['routeName']);
        self::assertSame('Home', $result['items'][0]['label']);
        self::assertArrayNotHasKey('id', $result['items'][0]);
        self::assertArrayHasKey('children', $result['items'][0]);
        self::assertSame('product_show', $result['items'][0]['children'][0]['routeName']);
        self::assertSame(['id' => '5'], $result['items'][0]['children'][0]['staticRouteParams']);
        self::assertSame(['slug'], $result['items'][0]['children'][0]['dynamicParamKeys']);
        self::assertSame(['en' => 'Product EN'], $result['items'][0]['children'][0]['translations']);
        self::assertSame('box', $result['items'][0]['children'][0]['icon']);
        self::assertFalse($result['items'][0]['children'][0]['linkEnabled']);
    }

    public function testExportAllExportsEveryCollection(): void
    {
        $colA = $this->makeCollection(1, 'a', '', 'A');
        $colB = $this->makeCollection(2, 'b', 'x', 'B');
        $itemA = $this->makeItem(10, $colA, null, 'route_a', 'A item');

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->expects(self::once())
            ->method('findBy')
            ->with([], ['code' => 'ASC', 'contextKey' => 'ASC'])
            ->willReturn([$colA, $colB]);

        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturnCallback(
            static fn (BreadcrumbCollection $c): array => 'a' === $c->getCode() ? [$itemA] : [],
        );

        $exporter = new BreadcrumbExporter($colRepo, $itemRepo);
        $result = $exporter->exportAll();

        self::assertCount(2, $result['collections']);
        self::assertSame('a', $result['collections'][0]['collection']['code']);
        self::assertCount(1, $result['collections'][0]['items']);
        self::assertSame('b', $result['collections'][1]['collection']['code']);
        self::assertSame([], $result['collections'][1]['items']);
    }

    public function testExportStripsNullAndEmptyOptionalFields(): void
    {
        $collection = $this->makeCollection(1, 'minimal', '', null);
        $item = $this->makeItem(1, $collection, null, 'home', null);
        $item->setStaticRouteParams([]);
        $item->setDynamicParamKeys(null);
        $item->setTranslations(null);
        $item->setIcon(null);

        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $exporter = new BreadcrumbExporter(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $itemRepo,
        );
        $result = $exporter->exportCollection($collection);

        $node = $result['items'][0];
        self::assertSame('home', $node['routeName']);
        self::assertArrayNotHasKey('label', $node);
        self::assertArrayNotHasKey('staticRouteParams', $node);
        self::assertArrayNotHasKey('dynamicParamKeys', $node);
        self::assertArrayNotHasKey('translations', $node);
        self::assertArrayNotHasKey('icon', $node);
        self::assertTrue($node['linkEnabled']);
    }

    private function makeCollection(int $id, string $code, string $contextKey, ?string $name): BreadcrumbCollection
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode($code);
        $collection->setContextKey($contextKey);
        $collection->setName($name);

        $ref = new \ReflectionProperty(BreadcrumbCollection::class, 'id');
        $ref->setValue($collection, $id);

        return $collection;
    }

    private function makeItem(
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
