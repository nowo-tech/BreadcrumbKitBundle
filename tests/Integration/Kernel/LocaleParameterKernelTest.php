<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Real kernel: nowo_breadcrumb_kit.locales given as %app.locales%, %kernel.enabled_locales% or an env var
 * (the host keeps one locale list) must compile and reach the bundle as the resolved list.
 */
final class LocaleParameterKernelTest extends TestCase
{
    protected function tearDown(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/nowo-breadcrumb-kit-locale-kernel');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, string}>
     */
    public static function provideLocaleConfigs(): iterable
    {
        yield 'app parameter' => [['locales' => '%app.locales%'], ['es', 'en', 'fr'], 'es'];
        yield 'kernel.enabled_locales' => [['locales' => '%kernel.enabled_locales%'], ['de', 'en'], 'de'];
        yield 'parameter + explicit default_locale' => [['locales' => '%app.locales%', 'default_locale' => 'fr'], ['es', 'en', 'fr'], 'fr'];
        yield 'env var (json)' => [['locales' => '%env(json:'.LocaleEnvVarLoader::JSON.')%'], ['pt', 'en'], 'pt'];
        yield 'env var (csv)' => [['locales' => '%env(csv:'.LocaleEnvVarLoader::CSV.')%'], ['it', 'en'], 'it'];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $expectedLocales
     */
    #[DataProvider('provideLocaleConfigs')]
    public function testLocaleListFromParameterCompilesAndResolves(array $config, array $expectedLocales, string $expectedDefault): void
    {
        $kernel = new LocaleParameterTestKernel(md5(serialize($config)), $config);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $loader = $container->get(BreadcrumbLoader::class);
            self::assertInstanceOf(BreadcrumbLoader::class, $loader);
            $defaultLocale = (new \ReflectionProperty(BreadcrumbLoader::class, 'defaultLocale'))->getValue($loader);
            self::assertSame($expectedDefault, $defaultLocale);
            if (!str_contains((string) json_encode($config), 'env(')) {
                self::assertSame($expectedLocales, $container->getParameter('nowo_breadcrumb_kit.locales'));
                self::assertSame($expectedDefault, $container->getParameter('nowo_breadcrumb_kit.default_locale_resolved'));
            }
        } finally {
            $kernel->shutdown();
        }
    }
}
