<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Entity;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use PHPUnit\Framework\TestCase;

final class BreadcrumbItemTest extends TestCase
{
    public function testResolveLabelPrefersLocaleTranslation(): void
    {
        $item = new BreadcrumbItem();
        $item->setLabel('Default');
        $item->setTranslations(['en' => 'English', 'es' => 'Español']);

        self::assertSame('Español', $item->resolveLabel('es', 'en'));
    }

    public function testResolveLabelFallsBackToDefaultLocale(): void
    {
        $item = new BreadcrumbItem();
        $item->setLabel('Default');
        $item->setTranslations(['en' => 'English']);

        self::assertSame('English', $item->resolveLabel('fr', 'en'));
    }

    public function testResolveLabelFallsBackToPlainLabel(): void
    {
        $item = new BreadcrumbItem();
        $item->setLabel('Plain');

        self::assertSame('Plain', $item->resolveLabel('fr', 'en'));
    }

    public function testResolveLabelReturnsEmptyWhenNothingAvailable(): void
    {
        $item = new BreadcrumbItem();

        self::assertSame('', $item->resolveLabel('fr', null));
    }

    public function testGettersAndSetters(): void
    {
        $collection = new BreadcrumbCollection();
        $parent = new BreadcrumbItem();
        $item = new BreadcrumbItem();

        $item->setCollection($collection);
        $item->setParent($parent);
        $item->setRouteName('route');
        $item->setStaticRouteParams(['id' => 1]);
        $item->setDynamicParamKeys(['slug']);
        $item->setLinkEnabled(false);
        $item->setLabel('Label');
        $item->setTranslations(['en' => 'EN']);
        $item->setIcon('icon');

        self::assertNull($item->getId());
        self::assertSame($collection, $item->getCollection());
        self::assertSame($parent, $item->getParent());
        self::assertSame('route', $item->getRouteName());
        self::assertSame(['id' => 1], $item->getStaticRouteParams());
        self::assertSame(['slug'], $item->getDynamicParamKeys());
        self::assertFalse($item->isLinkEnabled());
        self::assertSame('Label', $item->getLabel());
        self::assertSame(['en' => 'EN'], $item->getTranslations());
        self::assertSame('icon', $item->getIcon());
    }
}
