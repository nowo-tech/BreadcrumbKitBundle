<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\DependencyInjection;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\ConfiguredLocales;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * nowo_breadcrumb_kit.locales as a literal list, a parameter or an env placeholder (direct load() callers).
 */
final class LocalesConfigTest extends TestCase
{
    public function testParameterStringIsResolvedInLoad(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.locales', ['es', 'en']);
        (new BreadcrumbKitExtension())->load([['locales' => '%app.locales%']], $container);

        self::assertSame(['es', 'en'], $container->getParameter('nowo_breadcrumb_kit.locales'));
        self::assertSame('es', $container->getParameter('nowo_breadcrumb_kit.default_locale_resolved'));
        self::assertSame('%nowo_breadcrumb_kit.default_locale_resolved%', $container->getDefinition(BreadcrumbLoader::class)->getArgument('$defaultLocale'));
    }

    public function testEnvPlaceholderIsResolvedAtRuntimeByAFactory(): void
    {
        $container = new ContainerBuilder();
        (new BreadcrumbKitExtension())->load([['locales' => '%env(json:APP_LOCALES)%']], $container);

        self::assertSame('%env(json:APP_LOCALES)%', $container->resolveEnvPlaceholders($container->getParameter('nowo_breadcrumb_kit.locales')));
        self::assertSame(ConfiguredLocales::FALLBACK_LOCALE, $container->getParameter('nowo_breadcrumb_kit.default_locale_resolved'));
        $argument = $container->getDefinition(BreadcrumbLoader::class)->getArgument('$defaultLocale');
        self::assertInstanceOf(Definition::class, $argument);
        self::assertSame([ConfiguredLocales::class, 'defaultLocale'], $argument->getFactory());
        self::assertSame(['%nowo_breadcrumb_kit.locales%'], $argument->getArguments());
    }

    public function testEnvPlaceholderWithExplicitDefaultLocaleKeepsTheParameter(): void
    {
        $container = new ContainerBuilder();
        (new BreadcrumbKitExtension())->load([['locales' => '%env(json:APP_LOCALES)%', 'default_locale' => 'fr']], $container);

        self::assertSame('fr', $container->getParameter('nowo_breadcrumb_kit.default_locale_resolved'));
        self::assertSame('%nowo_breadcrumb_kit.default_locale_resolved%', $container->getDefinition(BreadcrumbLoader::class)->getArgument('$defaultLocale'));
    }

    public function testInvalidLocalesAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid nowo_breadcrumb_kit.locales');

        (new BreadcrumbKitExtension())->load([['locales' => ['en', ['es']]]], new ContainerBuilder());
    }

    public function testNonListScalarLocalesAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid nowo_breadcrumb_kit.locales');

        (new BreadcrumbKitExtension())->load([['locales' => 42]], new ContainerBuilder());
    }

    public function testPrependIgnoresParameterStringsOutsideTheDashboardKeys(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($extension = new BreadcrumbKitExtension());
        $container->loadFromExtension('nowo_breadcrumb_kit', ['locales' => '%kernel.enabled_locales%', 'cache' => ['ttl' => '%app.ttl%']]);

        $extension->prepend($container);

        self::assertFalse($container->getParameter('nowo_breadcrumb_kit.web_ui.enabled'));
    }
}
