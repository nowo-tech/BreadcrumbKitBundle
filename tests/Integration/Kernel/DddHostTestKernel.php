<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbEditAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbEditContext;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * DDD / hexagonal host (REQ-ARCH-001): the breadcrumb entities live on a dedicated `breadcrumb` manager, the host maps
 * its own entities on its default manager, and the host's inline-edit policy implements only the framework-free
 * {@see BreadcrumbEditAccessCheckerInterface} (users referenced by identifier and roles, no Symfony type). The token
 * storage stands in for the host firewall.
 */
final class DddHostTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $hostDatabase, private readonly string $bundleDatabase)
    {
        parent::__construct('test_ddd', false);
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
        return self::baseDir().'/cache';
    }

    public function getLogDir(): string
    {
        return self::baseDir().'/log';
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-ddd-host/'.getmypid();
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
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => ['url' => 'sqlite:///'.$this->hostDatabase],
                    'breadcrumb' => ['url' => 'sqlite:///'.$this->bundleDatabase],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'default' => [
                        'connection' => 'default',
                        'mappings' => ['HostApp' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => __DIR__.'/Entity',
                            'prefix' => 'Nowo\\BreadcrumbKitBundle\\Tests\\Integration\\Kernel\\Entity',
                        ]],
                    ],
                    'breadcrumb' => ['connection' => 'breadcrumb'],
                ],
            ],
        ]);
        $container->extension('nowo_breadcrumb_kit', [
            'doctrine' => ['entity_manager' => 'breadcrumb'],
            'web_ui' => ['enabled' => true, 'path_prefix' => '/admin/breadcrumbs'],
            'security' => ['allow_unauthenticated' => true],
            'inline_edit' => ['query_param' => 'edit', 'access_services' => ['editors' => 'app.breadcrumb_edit_policy']],
        ]);
        $services = $container->services();
        $services->set('security.token_storage', TokenStorage::class)->public();
        $services->set('app.breadcrumb_edit_policy', EditorRolePolicy::class)->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('project_show', '/projects/{id}')->controller('kernel::noop');
        $routes->import('@NowoBreadcrumbKitBundle/Resources/config/routing/dashboard.yaml', 'yaml')
            ->prefix('/admin/breadcrumbs');
    }
}

/**
 * Host policy in the host's application layer: identifier and roles only.
 */
final class EditorRolePolicy implements BreadcrumbEditAccessCheckerInterface
{
    public ?BreadcrumbEditContext $lastContext = null;

    public function canEditInline(BreadcrumbEditContext $context): bool
    {
        $this->lastContext = $context;

        return $context->hasRole('ROLE_EDITOR');
    }
}
