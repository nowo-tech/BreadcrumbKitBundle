<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\DependencyInjection;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\EventSubscriber\TablePrefixSubscriber;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Ensures the DI extension loads services.yaml and registers core definitions.
 */
final class BreadcrumbKitExtensionTest extends TestCase
{
    public function testGetAliasMatchesDocumentedConfigurationRoot(): void
    {
        $extension = new BreadcrumbKitExtension();
        self::assertSame('nowo_breadcrumb_kit', $extension->getAlias());
    }

    public function testExtensionLoadRegistersBreadcrumbLoader(): void
    {
        $container = new ContainerBuilder();
        $extension = new BreadcrumbKitExtension();
        $extension->load([[]], $container);

        self::assertTrue($container->hasDefinition(BreadcrumbLoader::class));
        self::assertTrue($container->hasDefinition(BreadcrumbInlineEditResolver::class));
        self::assertTrue($container->hasDefinition('nowo_breadcrumb_kit.inline_edit.access_checker_locator'));
        self::assertTrue($container->hasDefinition(TablePrefixSubscriber::class));
        self::assertSame('', $container->getParameter('nowo_breadcrumb_kit.table_prefix'));
    }

    public function testPrependSetsDefaultDashboardParameters(): void
    {
        $container = new ContainerBuilder();
        (new BreadcrumbKitExtension())->prepend($container);

        self::assertSame('/breadcrumb-kit-admin', $container->getParameter('nowo_breadcrumb_kit.dashboard.path_prefix'));
        self::assertFalse($container->getParameter('nowo_breadcrumb_kit.dashboard.enabled'));
        self::assertNull($container->getParameter('nowo_breadcrumb_kit.inline_edit.query_param'));
        self::assertSame([], $container->getParameter('nowo_breadcrumb_kit.inline_edit.access_services'));
    }

    public function testLoadWithDashboardEnabledAndInlineEditConfig(): void
    {
        $container = new ContainerBuilder();
        $extension = new BreadcrumbKitExtension();
        $extension->load([
            [
                'locales' => ['es', 'en'],
                'dashboard' => [
                    'enabled' => true,
                    'path_prefix' => '/admin/breadcrumbs',
                    'layout_template' => '@App/custom_layout.html.twig',
                    'import_max_bytes' => 4096,
                    'pagination' => [
                        'enabled' => false,
                        'per_page' => 50,
                    ],
                    'modals' => [
                        'collection_form' => 'xl',
                        'item_form' => 'normal',
                        'import' => 'lg',
                        'delete' => 'normal',
                    ],
                ],
                'inline_edit' => [
                    'query_param' => 'edit_breadcrumbs',
                    'access_services' => [
                        'demo' => 'app.checker',
                        '' => 'ignored',
                        1 => 'ignored',
                    ],
                ],
                'cache' => [
                    'pool' => 'cache.app',
                    'ttl' => 120,
                ],
            ],
        ], $container);

        self::assertSame('/admin/breadcrumbs', $container->getParameter('nowo_breadcrumb_kit.dashboard.path_prefix'));
        self::assertTrue($container->getParameter('nowo_breadcrumb_kit.dashboard.enabled'));
        self::assertSame('@App/custom_layout.html.twig', $container->getParameter('nowo_breadcrumb_kit.dashboard.layout_template'));
        self::assertSame(4096, $container->getParameter('nowo_breadcrumb_kit.dashboard.import_max_bytes'));
        self::assertFalse($container->getParameter('nowo_breadcrumb_kit.dashboard.pagination.enabled'));
        self::assertSame(50, $container->getParameter('nowo_breadcrumb_kit.dashboard.pagination.per_page'));
        self::assertSame([
            'collection_form' => 'xl',
            'item_form' => 'normal',
            'import' => 'lg',
            'delete' => 'normal',
        ], $container->getParameter('nowo_breadcrumb_kit.dashboard.modals'));
        self::assertSame('edit_breadcrumbs', $container->getParameter('nowo_breadcrumb_kit.inline_edit.query_param'));
        self::assertSame(['demo' => 'app.checker'], $container->getParameter('nowo_breadcrumb_kit.inline_edit.access_services'));
        self::assertSame('es', $container->getParameter('nowo_breadcrumb_kit.default_locale_resolved'));
    }

    public function testLoadNormalizesInvalidDashboardPathPrefix(): void
    {
        $container = new ContainerBuilder();
        (new BreadcrumbKitExtension())->load([
            ['dashboard' => ['path_prefix' => 'no-leading-slash']],
        ], $container);

        self::assertSame('/breadcrumb-kit-admin', $container->getParameter('nowo_breadcrumb_kit.dashboard.path_prefix'));
    }
}
