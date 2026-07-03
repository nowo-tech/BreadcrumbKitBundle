<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Entity;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use PHPUnit\Framework\TestCase;

final class BreadcrumbCollectionTest extends TestCase
{
    public function testAddItemSetsBidirectionalRelation(): void
    {
        $collection = new BreadcrumbCollection();
        $item = new BreadcrumbItem();

        $collection->addItem($item);

        self::assertTrue($collection->getItems()->contains($item));
        self::assertSame($collection, $item->getCollection());
    }

    public function testAddItemDoesNotDuplicateSameItem(): void
    {
        $collection = new BreadcrumbCollection();
        $item = new BreadcrumbItem();

        $collection->addItem($item);
        $collection->addItem($item);

        self::assertCount(1, $collection->getItems());
    }

    public function testRemoveItemDetachesFromCollection(): void
    {
        $collection = new BreadcrumbCollection();
        $item = new BreadcrumbItem();
        $collection->addItem($item);

        $collection->removeItem($item);

        self::assertFalse($collection->getItems()->contains($item));
    }

    public function testGettersAndSetters(): void
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('code');
        $collection->setContextKey('ctx');
        $collection->setName('Name');
        $collection->setHomeIcon('home');
        $collection->setSeparatorIcon('sep');
        $collection->setResponsiveConfig(['max' => 3]);
        $collection->setClassList('list');
        $collection->setClassItem('item');
        $collection->setClassSeparator('sep');
        $collection->setClassCurrent('current');
        $collection->setInlineEditAccessKey('admin');

        self::assertNull($collection->getId());
        self::assertSame('code', $collection->getCode());
        self::assertSame('ctx', $collection->getContextKey());
        self::assertSame('Name', $collection->getName());
        self::assertSame('home', $collection->getHomeIcon());
        self::assertSame('sep', $collection->getSeparatorIcon());
        self::assertSame(['max' => 3], $collection->getResponsiveConfig());
        self::assertSame('list', $collection->getClassList());
        self::assertSame('item', $collection->getClassItem());
        self::assertSame('sep', $collection->getClassSeparator());
        self::assertSame('current', $collection->getClassCurrent());
        self::assertSame('admin', $collection->getInlineEditAccessKey());
    }
}
