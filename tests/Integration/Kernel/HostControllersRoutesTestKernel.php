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
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Host app with the dashboard enabled whose routing is the Symfony default `controllers: { resource: routing.controllers }`,
 * optionally followed by the documented import of the bundle routing (REQ-SF-004 rule 7).
 */
final class HostControllersRoutesTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly bool $importBundleRoutes)
    {
        // One environment per routing setup: each compiles its own container class (no reuse of a removed cache).
        parent::__construct($importBundleRoutes ? 'test_imported' : 'test_controllers', false);
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
        return self::baseDir().'/'.($this->importBundleRoutes ? 'imported' : 'controllers-only').'/cache';
    }

    public function getLogDir(): string
    {
        return self::baseDir().'/log';
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-host-controllers-kernel';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'csrf_protection' => true,
            'form' => ['csrf_protection' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'router' => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['mappings' => ['NowoBreadcrumbKitBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => \dirname(__DIR__, 3).'/src/Entity',
                'prefix' => 'Nowo\\BreadcrumbKitBundle\\Entity',
            ]]],
        ]);
        $container->extension('nowo_breadcrumb_kit', [
            'web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs'],
            'security' => ['allow_unauthenticated' => true],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('routing.controllers');

        if ($this->importBundleRoutes) {
            $routes->import('@NowoBreadcrumbKitBundle/Resources/config/routing/dashboard.yaml', 'yaml')
                ->prefix('/admin/breadcrumbs');
        }
    }
}
