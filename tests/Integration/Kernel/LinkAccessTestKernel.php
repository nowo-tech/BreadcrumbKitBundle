<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbLinkAccessCheckerInterface;
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
 * Host app with project routes where `{id}` means different things (BK-1, BK-2), and a checker service that autowiring
 * could pick up through its interface alias whether or not link_access_checker names it.
 */
final class LinkAccessTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly ?string $linkAccessChecker)
    {
        parent::__construct(null === $linkAccessChecker ? 'test_unset' : 'test_set', false);
    }

    public function registerBundles(): iterable
    {
        // Twig first: the option must not depend on the bundle order.
        yield new TwigBundle();
        yield new FrameworkBundle();
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
        return self::baseDir().'/'.$this->environment.'/cache';
    }

    public function getLogDir(): string
    {
        return self::baseDir().'/log';
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-link-access-kernel';
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
        $container->extension('nowo_breadcrumb_kit', ['link_access_checker' => $this->linkAccessChecker]);
        $container->services()->set('app.link_checker', DenyListLinkChecker::class)->public();
        $container->services()->alias(BreadcrumbLinkAccessCheckerInterface::class, 'app.link_checker');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        foreach ([
            'home' => '/',
            'project_show' => '/projects/{id}',
            'issue_show' => '/projects/{projectId}/issues/{id}',
            'monitor_show' => '/uptime/{tenantSlug}/monitors/{id}',
            'wiki_page' => '/wiki/{spaceSlug}/pages/{id}',
        ] as $name => $path) {
            $routes->add($name, $path)->controller('kernel::noop');
        }
    }
}

final class DenyListLinkChecker implements BreadcrumbLinkAccessCheckerInterface
{
    /** @var list<string> */
    public array $denied = [];

    /** @var list<array{string, array<string, scalar|null>}> */
    public array $asked = [];

    public function canLink(string $route, array $params): bool
    {
        $this->asked[] = [$route, $params];

        return !\in_array($route, $this->denied, true);
    }
}
