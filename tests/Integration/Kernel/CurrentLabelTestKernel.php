<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabelInterface;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * Host with a PHP page (controller calls setCurrentLabel()) and a Twig page (`{% do breadcrumb_current_label() %}`
 * at the top of a child template whose layout renders the trail before the content).
 */
final class CurrentLabelTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test_current_label', false);
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
        return sys_get_temp_dir().'/nowo-breadcrumb-kit-current-label-kernel';
    }

    /** PHP page: names the current crumb from the controller unless the slug is "none". */
    public function phpPage(string $slug): Response
    {
        if ('none' !== $slug) {
            $this->currentLabel()->setCurrentLabel('PHP '.$slug);
        }

        return new Response($this->twig()->createTemplate('{{ breadcrumb_render() }}')->render());
    }

    /** Twig page: the template names the current crumb. */
    public function twigPage(string $slug): Response
    {
        return new Response($this->twig()->render('page.html.twig', ['page' => ['title' => 'Twig '.$slug]]));
    }

    /**
     * Names its own crumb, then embeds another page as a sub-request (`render()` fragment): that fragment's trail must
     * keep its own label.
     */
    public function fragmentPage(string $slug): Response
    {
        $this->currentLabel()->setCurrentLabel('Main '.$slug);

        return new Response($this->twig()->createTemplate(
            "<div id=\"main\">{{ breadcrumb_render() }}</div><div id=\"fragment\">{{ render(path('wiki_php', {slug: 'none'})) }}</div>",
        )->render());
    }

    /** Sets a label, then fails: the error must not leak it into the next request. */
    public function failingPage(): Response
    {
        $this->currentLabel()->setCurrentLabel('Leaked');

        throw new RuntimeException('boom');
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'router' => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
        ]);
        // The failing page logs a critical error; keep the test output clean.
        $container->services()->set('logger', NullLogger::class);
        $container->extension('twig', ['default_path' => \dirname(__DIR__, 2).'/fixtures/templates/current_label']);
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
        $routes->add('home', '/')->controller('kernel::phpPage')->defaults(['slug' => 'none']);
        $routes->add('wiki_php', '/wiki/php/{slug}')->controller('kernel::phpPage');
        $routes->add('wiki_twig', '/wiki/twig/{slug}')->controller('kernel::twigPage');
        $routes->add('wiki_frag', '/wiki/frag/{slug}')->controller('kernel::fragmentPage');
        $routes->add('wiki_fail', '/wiki/fail')->controller('kernel::failingPage');
    }

    private function twig(): Environment
    {
        $twig = $this->service('twig');
        \assert($twig instanceof Environment);

        return $twig;
    }

    private function currentLabel(): BreadcrumbCurrentLabelInterface
    {
        $label = $this->service(BreadcrumbCurrentLabelInterface::class);
        \assert($label instanceof BreadcrumbCurrentLabelInterface);

        return $label;
    }

    private function service(string $id): object
    {
        $container = $this->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);

        return $container->get($id);
    }
}
