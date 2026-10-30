<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

/**
 * REQ-SF-004 rule 7: the dashboard routes exist only when the host imports the bundle routing; the host's default
 * `routing.controllers` resource (Symfony >= 7.3) must not pick the bundle controllers up.
 */
final class HostControllersRoutesKernelTest extends TestCase
{
    private ?HostControllersRoutesTestKernel $kernel = null;

    protected function setUp(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove(HostControllersRoutesTestKernel::baseDir());
    }

    public function testRoutingControllersDoesNotRegisterTheDashboardRoutes(): void
    {
        $names = array_keys($this->router(false)->getRouteCollection()->all());

        self::assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'nowo_breadcrumb_kit'))));
    }

    public function testImportedDashboardRoutesKeepThePrefixAndServeTheIndex(): void
    {
        $router = $this->router(true);

        self::assertSame('/admin/breadcrumbs/', $router->getRouteCollection()->get('nowo_breadcrumb_kit_dashboard_index')?->getPath());
        self::assertSame('/admin/breadcrumbs/collections', $router->getRouteCollection()->get('nowo_breadcrumb_kit_dashboard_collections_index')?->getPath());

        \assert(null !== $this->kernel);
        // AbstractController::redirectToRoute() needs the service-subscriber container (explicit tag).
        $response = $this->kernel->handle(Request::create('/admin/breadcrumbs/'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/breadcrumbs/collections', $response->headers->get('Location'));
    }

    private function router(bool $importBundleRoutes): RouterInterface
    {
        (new Filesystem())->remove(HostControllersRoutesTestKernel::baseDir());
        $this->kernel = new HostControllersRoutesTestKernel($importBundleRoutes);
        $this->kernel->boot();
        $router = $this->kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router;
    }
}
