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
 * Host kernel with a configurable Doctrine setup (2.16, `doctrine.entity_manager`), dashboard on. One cache directory
 * per configuration and PHPUnit process.
 */
final class EntityManagerTestKernel extends Kernel
{
    use MicroKernelTrait;

    private readonly string $cacheKey;

    /**
     * @param array<string, mixed> $doctrine
     * @param array<string, mixed> $breadcrumbKit
     */
    public function __construct(
        private readonly array $doctrine,
        private readonly array $breadcrumbKit = [],
    ) {
        $this->cacheKey = hash('xxh128', serialize([$doctrine, $breadcrumbKit]));
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
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-em/'.getmypid().'/'.$this->cacheKey.'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-em/'.getmypid().'/'.$this->cacheKey.'/log';
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
        $container->extension('doctrine', $this->doctrine);
        $container->extension('nowo_breadcrumb_kit', $this->breadcrumbKit + [
            'web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs'],
            'security' => ['allow_unauthenticated' => true],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('home', '/')->controller('kernel::noop');
    }
}
