<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\DependencyInjection;

use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * REQ-UI-003 configuration vocabulary: web_ui.* canonical keys, the deprecated dashboard.* aliases and the
 * dashboard.classes → nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle bridge.
 */
final class WebUiConfigTest extends TestCase
{
    public function testCanonicalDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame([
            'enabled' => false,
            'path_prefix' => '/breadcrumb-kit-admin',
            'layout_template' => null,
            'templates' => [],
            'import_max_bytes' => 2_097_152,
            'pagination' => ['enabled' => true, 'per_page' => 20],
            'dialog_sizes' => ['collection_form' => 'lg', 'item_form' => 'lg', 'import' => 'md'],
        ], $config['web_ui']);
    }

    public function testCanonicalKeysReachTheParametersAndTheService(): void
    {
        $container = new ContainerBuilder();
        (new BreadcrumbKitExtension())->load([[
            'web_ui' => [
                'enabled' => true,
                'path_prefix' => '/admin/crumbs',
                'layout_template' => 'layouts/admin.html.twig',
                'templates' => ['items_index' => 'admin/items.html.twig'],
                'dialog_sizes' => ['import' => 'sm'],
            ],
            'security' => ['allow_unauthenticated' => true],
        ]], $container);

        self::assertSame('/admin/crumbs', $container->getParameter('nowo_breadcrumb_kit.web_ui.path_prefix'));
        self::assertSame('/admin/crumbs', $container->getParameter('nowo_breadcrumb_kit.dashboard.path_prefix'));
        self::assertTrue($container->getParameter('nowo_breadcrumb_kit.web_ui.enabled'));
        self::assertSame('layouts/admin.html.twig', $container->getParameter('nowo_breadcrumb_kit.web_ui.layout_template'));
        self::assertSame(['items_index' => 'admin/items.html.twig'], $container->getParameter('nowo_breadcrumb_kit.web_ui.templates'));
        self::assertEquals(['collection_form' => 'lg', 'item_form' => 'lg', 'import' => 'sm'], $container->getParameter('nowo_breadcrumb_kit.web_ui.dialog_sizes'));

        $arguments = $container->getDefinition(DashboardPages::class)->getArguments();
        self::assertSame('%nowo_breadcrumb_kit.web_ui.layout_template%', $arguments['$layoutTemplate']);
        self::assertSame('%nowo_breadcrumb_kit.web_ui.templates%', $arguments['$templates']);
    }

    public function testUnknownTemplatePageIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_breadcrumb_kit.web_ui.templates page(s): dashboard.');

        $this->process(['web_ui' => ['templates' => ['dashboard' => 'x.html.twig']]]);
    }

    #[IgnoreDeprecations]
    public function testDeprecatedDashboardKeysAreAliasesOfWebUi(): void
    {
        $this->expectUserDeprecationMessageMatches('/The "nowo_breadcrumb_kit\.dashboard\.path_prefix" option is deprecated, use "nowo_breadcrumb_kit\.web_ui\.path_prefix" instead\./');

        $config = $this->process([
            'dashboard' => [
                'enabled' => true,
                'path_prefix' => '/legacy',
                'layout_template' => Configuration::LEGACY_LAYOUT_TEMPLATE,
                'import_max_bytes' => 4096,
                'pagination' => ['per_page' => 5],
                'modals' => ['collection_form' => 'normal', 'item_form' => 'xl', 'delete' => 'lg'],
            ],
            'web_ui' => ['enabled' => false, 'pagination' => ['enabled' => false]],
        ]);

        self::assertFalse($config['web_ui']['enabled'], 'The canonical key wins.');
        self::assertSame('/legacy', $config['web_ui']['path_prefix']);
        self::assertNull($config['web_ui']['layout_template'], 'The removed 1.x layout reads as the UiKit layout.');
        self::assertSame(4096, $config['web_ui']['import_max_bytes']);
        self::assertSame(['enabled' => false, 'per_page' => 5], $config['web_ui']['pagination']);
        self::assertSame(['collection_form' => 'md', 'item_form' => 'xl', 'import' => 'md'], $config['web_ui']['dialog_sizes']);
    }

    #[IgnoreDeprecations]
    public function testCustomLegacyLayoutIsKeptAndEmptyLayoutIsNull(): void
    {
        self::assertSame('base.html.twig', $this->process(['dashboard' => ['layout_template' => 'base.html.twig']])['web_ui']['layout_template']);
        self::assertNull($this->process(['web_ui' => ['layout_template' => '']])['web_ui']['layout_template']);
    }

    #[IgnoreDeprecations]
    public function testInvalidDeprecatedCssFrameworkIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['dashboard' => ['css_framework' => 'bulma']]);
    }

    #[IgnoreDeprecations]
    public function testInvalidDeprecatedIconSetIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['dashboard' => ['icon_set' => 'fontawesome']]);
    }

    #[IgnoreDeprecations]
    public function testDashboardClassesAreBridgedIntoTheUiKitTheme(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class implements ExtensionInterface {
            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }
        });
        $container->prependExtensionConfig('nowo_ui_kit', ['css_framework' => 'tailwind', 'icon_set' => 'none']);
        $container->prependExtensionConfig('nowo_breadcrumb_kit', ['dashboard' => [
            'css_framework' => 'bootstrap5',
            'classes' => ['btn_primary' => 'btn-moss', 'action_delete' => 'text-red-700'],
            'partials' => ['pagination' => 'kit/_pagination.html.twig'],
        ]]);

        (new BreadcrumbKitExtension())->prepend($container);

        $uiKit = $container->getExtensionConfig('nowo_ui_kit');
        self::assertSame(['theme' => ['bundles' => ['NowoBreadcrumbKitBundle' => ['classes' => [
            'btn_primary' => ['value' => 'btn-moss', 'framework_defaults' => false],
            'action_danger' => 'text-red-700',
        ]]]]], $uiKit[0], 'Prepended (the host config wins); css_framework / icon_set are not seeded over the host ones.');
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
