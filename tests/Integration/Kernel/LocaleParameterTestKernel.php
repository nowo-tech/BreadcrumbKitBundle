<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal host app whose nowo_breadcrumb_kit.locales is a container parameter or an env var (not a literal list).
 */
final class LocaleParameterTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $breadcrumbConfig
     */
    public function __construct(
        private readonly string $case,
        private readonly array $breadcrumbConfig,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new NowoUiKitBundle();
        yield new NowoFormKitBundle();
        yield new NowoBreadcrumbKitBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-locale-kernel/'.$this->case.'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-locale-kernel/'.$this->case.'/log';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->setParameter('app.locales', ['es', 'en', 'fr']);
            // Env vars of the host (no process env mutation in tests): read through Symfony's env var loaders.
            $container->register('test.locale_env_loader', LocaleEnvVarLoader::class)
                ->addTag('container.env_var_loader');
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_method_override' => false,
                'enabled_locales' => ['de', 'en'],
                'csrf_protection' => false,
                'router' => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
            ]);
            $container->loadFromExtension('doctrine', [
                'dbal' => ['url' => 'sqlite:///:memory:'],
                'orm' => ['mappings' => ['NowoBreadcrumbKitBundle' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => \dirname(__DIR__, 3).'/src/Entity',
                    'prefix' => 'Nowo\\BreadcrumbKitBundle\\Entity',
                ]]],
            ]);
            $container->loadFromExtension('nowo_breadcrumb_kit', $this->breadcrumbConfig);
        });
    }
}
