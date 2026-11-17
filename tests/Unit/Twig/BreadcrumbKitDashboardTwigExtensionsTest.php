<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Twig;

use Nowo\BreadcrumbKitBundle\Twig\BreadcrumbKitDashboardGlobalsExtension;
use Nowo\BreadcrumbKitBundle\Twig\BreadcrumbKitDashboardLinkExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BreadcrumbKitDashboardTwigExtensionsTest extends TestCase
{
    public function testTheLinkFunctionIsRegisteredAndGeneratesTheCollectionsUrl(): void
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->expects(self::once())->method('generate')->with('nowo_breadcrumb_kit_dashboard_collections_index')->willReturn('/admin/breadcrumbs/collections');
        $extension = new BreadcrumbKitDashboardLinkExtension(true, $generator);

        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('breadcrumb_kit_dashboard_collections_url', $functions[0]->getName());
        self::assertSame('/admin/breadcrumbs/collections', $extension->collectionsUrl());
    }

    public function testNoUrlWhenTheDashboardIsOffOrTheRouteIsMissing(): void
    {
        $off = $this->createMock(UrlGeneratorInterface::class);
        $off->expects(self::never())->method('generate');
        self::assertNull((new BreadcrumbKitDashboardLinkExtension(false, $off))->collectionsUrl());

        $missing = $this->createMock(UrlGeneratorInterface::class);
        $missing->method('generate')->willThrowException(new RouteNotFoundException());
        self::assertNull((new BreadcrumbKitDashboardLinkExtension(true, $missing))->collectionsUrl());
    }

    public function testTheGlobalsExposeTheConfiguredDashboardSettings(): void
    {
        $globals = (new BreadcrumbKitDashboardGlobalsExtension('layout.html.twig', 'tailwind', 'lucide', ['btn' => 'x'], ['head' => 'p.html.twig'], false, 'cards', 'end', true))->getGlobals();

        self::assertSame([
            'nowo_breadcrumb_kit_layout_template' => 'layout.html.twig',
            'nowo_breadcrumb_kit_css_framework' => 'tailwind',
            'nowo_breadcrumb_kit_icon_set' => 'lucide',
            'nowo_breadcrumb_kit_classes' => ['btn' => 'x'],
            'nowo_breadcrumb_kit_partials' => ['head' => 'p.html.twig'],
            'nowo_breadcrumb_kit_ui_kit_stylesheet' => false,
            'nowo_breadcrumb_kit_collections_view' => 'cards',
            'nowo_breadcrumb_kit_row_actions_position' => 'end',
            'nowo_breadcrumb_kit_cancel_first' => true,
        ], $globals);
        self::assertSame('bootstrap5', (new BreadcrumbKitDashboardGlobalsExtension('l'))->getGlobals()['nowo_breadcrumb_kit_css_framework']);
    }
}
