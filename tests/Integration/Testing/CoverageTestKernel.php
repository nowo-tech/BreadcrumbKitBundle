<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Testing;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Host app for the breadcrumb coverage trait: pages, a JSON route, downloads, redirects, a fragment, a format route,
 * a POST-only route, an internal route and an opted-out route; breadcrumb rows in an in-memory SQLite database.
 */
final class CoverageTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return self::baseDir().'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return self::baseDir().'/log';
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-coverage-kernel';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
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
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $page = CoverageAppController::class.'::page';

        // Pages.
        $routes->add('app_home', '/')->controller($page)->methods(['GET']);
        $routes->add('app_items', '/items')->controller($page);
        $routes->add('app_item_show', '/items/{id}')->controller($page)->requirements(['id' => '\d+'])->methods(['GET', 'HEAD']);
        $routes->add('app_items_fragment', '/items/table-fragment')->controller($page);

        // Non-pages.
        $routes->add('app_items_api', '/api/items')->controller(CoverageAppController::class.'::json');
        $routes->add('app_items_export', '/items/export.csv')->controller($page);
        $routes->add('app_item_download', '/items/{id}/download')->controller(CoverageAppController::class.'::download');
        $routes->add('app_join', '/join/{token}')->controller(CoverageAppController::class.'::joinLink');
        $routes->add('app_legacy', '/old-items')->controller(RedirectController::class.'::urlRedirectAction')
            ->defaults(['path' => '/items', 'permanent' => true]);
        $routes->add('app_feed', '/feed.{_format}')->controller($page)->requirements(['_format' => 'rss|atom']);
        $routes->add('app_items_create', '/items')->controller($page)->methods(['POST']);
        $routes->add('_internal_toolbar', '/_wdt/{token}')->controller($page);
        $routes->add('app_healthcheck', '/healthz')->controller($page)->options(['breadcrumb' => false]);
    }
}
