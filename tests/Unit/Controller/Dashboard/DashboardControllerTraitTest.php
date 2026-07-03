<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Controller\Dashboard;

use Nowo\BreadcrumbKitBundle\Controller\Dashboard\CollectionCrudController;
use PHPUnit\Framework\TestCase;

final class DashboardControllerTraitTest extends TestCase
{
    public function testResolveModalClassesMapsSizes(): void
    {
        $method = new \ReflectionMethod(CollectionCrudController::class, 'resolveModalClasses');

        $classes = $method->invoke(null, [
            'collection_form' => 'lg',
            'item_form' => 'xl',
            'import' => 'normal',
            'delete' => 'normal',
        ]);

        self::assertSame('modal-lg', $classes['collection_form']);
        self::assertSame('modal-xl', $classes['item_form']);
        self::assertSame('', $classes['import']);
        self::assertSame('', $classes['delete']);
    }

    public function testResolveModalClassesUsesDefaultsForMissingKeys(): void
    {
        $method = new \ReflectionMethod(CollectionCrudController::class, 'resolveModalClasses');

        $classes = $method->invoke(null, []);

        self::assertSame('modal-lg', $classes['collection_form']);
        self::assertSame('modal-lg', $classes['item_form']);
        self::assertSame('', $classes['import']);
        self::assertSame('', $classes['delete']);
    }
}
