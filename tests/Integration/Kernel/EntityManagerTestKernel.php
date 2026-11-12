<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
     * @param bool                 $hostDefaultDriver the host gives its default manager's chain a catch-all default
     *                                                driver (what DoctrineBundle 3.4 `auto_discover_entities` does)
     */
    public function __construct(
        private readonly array $doctrine,
        private readonly array $breadcrumbKit = [],
        private readonly bool $hostDefaultDriver = false,
    ) {
        $this->cacheKey = hash('xxh128', serialize([$doctrine, $breadcrumbKit, $hostDefaultDriver]));
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

    protected function build(ContainerBuilder $container): void
    {
        if (!$this->hostDefaultDriver) {
            return;
        }

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // An attribute driver answers "not transient" for any class carrying #[ORM\Entity], whatever its paths.
                $container->getDefinition('doctrine.orm.default_metadata_driver')
                    ->addMethodCall('setDefaultDriver', [new Definition(AttributeDriver::class, [[__DIR__.'/Entity'], true])]);
            }
        });
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
