<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Worker;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoaderInterface;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Kept-alive host kernel of the worker tests (2.19.0): the `http_kernel` service is driven directly, the services
 * resetter never runs. `dedicated` maps the breadcrumb entities on their own `breadcrumb` manager; `standard` keeps
 * them on the host's default manager; `default-named` sets `doctrine.entity_manager: default` (the host default).
 */
final class WorkerTestKernel extends Kernel
{
    use MicroKernelTrait;

    public const DEDICATED = 'dedicated';
    public const STANDARD = 'standard';
    public const DEFAULT_NAMED = 'default-named';

    public function __construct(
        private readonly string $mode,
        private readonly string $directory,
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
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-worker/'.getmypid().'/'.hash('xxh128', serialize([$this->mode, $this->directory])).'/cache';
    }

    public function getLogDir(): string
    {
        return \dirname($this->getCacheDir()).'/log';
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('w_home', '/w')->controller('kernel::noop');
        $routes->add('w_reports', '/w/reports')->controller('kernel::noop');
        $routes->add('w_report', '/w/reports/latest')->controller([WorkerTrailController::class, 'trail']);
        $routes->add('w_boom', '/w/boom')->controller([WorkerTrailController::class, 'boom']);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'router' => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
            // symfony/messenger is a dev dependency for the worker tests; the framework's own worker listeners (typed on
            // the real event classes) are the host's choice and stay off here, so the bundle's boundaries are tested alone.
            'messenger' => ['enabled' => false],
        ]);
        $hostApp = ['HostApp' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__).'/Kernel/Entity',
            'prefix' => 'Nowo\\BreadcrumbKitBundle\\Tests\\Integration\\Kernel\\Entity',
        ]];
        // No item-list cache: every request reads the database through the bundle's manager.
        $breadcrumbKit = ['cache' => ['pool' => ''], 'link_access_checker' => WorkerLinkChecker::class];
        if (self::STANDARD === $this->mode) {
            $container->extension('doctrine', [
                'dbal' => ['url' => 'sqlite:///'.$this->directory.'/host.sqlite'],
                'orm' => ['auto_mapping' => true, 'mappings' => $hostApp],
            ]);
        } else {
            $managers = ['default' => ['connection' => 'default', 'mappings' => $hostApp]];
            if (self::DEDICATED === $this->mode) {
                $managers['breadcrumb'] = ['connection' => 'breadcrumb'];
            }
            $container->extension('doctrine', [
                'dbal' => ['default_connection' => 'default', 'connections' => [
                    'default' => ['url' => 'sqlite:///'.$this->directory.'/host.sqlite'],
                    'breadcrumb' => ['url' => 'sqlite:///'.$this->directory.'/breadcrumb.sqlite'],
                ]],
                'orm' => ['default_entity_manager' => 'default', 'entity_managers' => $managers],
            ]);
            $breadcrumbKit['doctrine'] = ['entity_manager' => self::DEDICATED === $this->mode ? 'breadcrumb' : 'default'];
        }
        $container->extension('nowo_breadcrumb_kit', $breadcrumbKit);

        $services = $container->services();
        $services->set(WorkerLinkChecker::class)->args([service('request_stack')])->public();
        $services->set(WorkerTrailController::class)
            ->args([service(BreadcrumbLoaderInterface::class)])
            ->public()
            ->tag('controller.service_arguments');
    }
}
