<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbTrailPreview;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class BreadcrumbTrailPreviewTest extends TestCase
{
    public function testPreviewPushesRequestAndDelegatesToLoader(): void
    {
        $stack = new RequestStack();
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $urlResolver = $this->createMock(BreadcrumbUrlResolverInterface::class);

        $loader = new BreadcrumbLoader($colRepo, $itemRepo, $urlResolver, $stack, 'en');
        $preview = new BreadcrumbTrailPreview($loader, $stack);

        $view = $preview->preview('default', '', '/x', 'app_x', ['id' => 1], ['tenant' => 'a'], 'GET', 'es');
        self::assertSame([], $view->nodes);
        self::assertNull($stack->getCurrentRequest());
    }
}
