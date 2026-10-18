<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\DependencyInjection;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\Twig\BreadcrumbKitDashboardGlobalsExtension;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Spec 003: dashboard.classes / partials / ui_kit_stylesheet / collections_view / row_actions_position / cancel_first.
 */
#[IgnoreDeprecations]
final class DashboardChromeConfigTest extends TestCase
{
    public function testDefaultsKeepTheLegacyChrome(): void
    {
        $container = $this->load([]);

        self::assertSame([], $container->getParameter('nowo_breadcrumb_kit.dashboard.classes'));
        self::assertSame([], $container->getParameter('nowo_breadcrumb_kit.dashboard.partials'));
        self::assertTrue($container->getParameter('nowo_breadcrumb_kit.dashboard.ui_kit_stylesheet'));
        self::assertSame('list', $container->getParameter('nowo_breadcrumb_kit.dashboard.collections_view'));
        self::assertSame('start', $container->getParameter('nowo_breadcrumb_kit.dashboard.row_actions_position'));
        self::assertFalse($container->getParameter('nowo_breadcrumb_kit.dashboard.cancel_first'));
    }

    public function testConfiguredValuesReachTheTwigGlobals(): void
    {
        $container = $this->load([
            'enabled' => true,
            'classes' => ['btn_primary' => ' btn-primary ', 'table' => 'data-table', 'empty' => ''],
            'partials' => ['pagination' => 'kit/_pagination.html.twig', 'action_inner' => '@NowoUiKitBundle/partials/_action_inner.html.twig'],
            'ui_kit_stylesheet' => false,
            'collections_view' => 'table',
            'row_actions_position' => 'end',
            'cancel_first' => true,
        ]);

        self::assertSame(['btn_primary' => 'btn-primary', 'table' => 'data-table'], $container->getParameter('nowo_breadcrumb_kit.dashboard.classes'));

        $arguments = $container->getDefinition(BreadcrumbKitDashboardGlobalsExtension::class)->getArguments();
        self::assertSame('%nowo_breadcrumb_kit.dashboard.classes%', $arguments['$classes']);
        self::assertSame('%nowo_breadcrumb_kit.dashboard.partials%', $arguments['$partials']);
        self::assertSame('%nowo_breadcrumb_kit.dashboard.cancel_first%', $arguments['$cancelFirst']);

        self::assertSame(['pagination' => 'kit/_pagination.html.twig', 'action_inner' => '@NowoUiKitBundle/partials/_action_inner.html.twig'], $container->getParameter('nowo_breadcrumb_kit.dashboard.partials'));
        self::assertFalse($container->getParameter('nowo_breadcrumb_kit.dashboard.ui_kit_stylesheet'));
        self::assertSame('table', $container->getParameter('nowo_breadcrumb_kit.dashboard.collections_view'));
        self::assertSame('end', $container->getParameter('nowo_breadcrumb_kit.dashboard.row_actions_position'));
        self::assertTrue($container->getParameter('nowo_breadcrumb_kit.dashboard.cancel_first'));

        $globals = (new BreadcrumbKitDashboardGlobalsExtension(
            'layout.html.twig',
            'tailwind',
            'ux_icon',
            ['table' => 'data-table'],
            ['pagination' => 'kit/_pagination.html.twig'],
            false,
            'table',
            'end',
            true,
        ))->getGlobals();

        self::assertSame('data-table', $globals['nowo_breadcrumb_kit_classes']['table']);
        self::assertSame('kit/_pagination.html.twig', $globals['nowo_breadcrumb_kit_partials']['pagination']);
        self::assertFalse($globals['nowo_breadcrumb_kit_ui_kit_stylesheet']);
        self::assertSame('table', $globals['nowo_breadcrumb_kit_collections_view']);
        self::assertSame('end', $globals['nowo_breadcrumb_kit_row_actions_position']);
        self::assertTrue($globals['nowo_breadcrumb_kit_cancel_first']);
    }

    public function testUnknownClassKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_breadcrumb_kit.dashboard.classes key(s): btn_primay');

        $this->load(['classes' => ['btn_primay' => 'x']]);
    }

    public function testUnknownPartialKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_breadcrumb_kit.dashboard.partials key(s): layout');

        $this->load(['partials' => ['layout' => 'x.html.twig']]);
    }

    public function testInvalidCollectionsViewIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['collections_view' => 'grid']);
    }

    /**
     * @param array<string, mixed> $dashboard
     */
    private function load(array $dashboard): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new BreadcrumbKitExtension())->load([['dashboard' => $dashboard]], $container);

        return $container;
    }
}
