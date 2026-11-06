<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * Real kernel, importer, router and templates: the collection JSON → the rendered trail, for the two cases Beacon solved
 * with decorators of BreadcrumbUrlResolverInterface.
 */
final class LinkAccessKernelTest extends TestCase
{
    private ?LinkAccessTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove(LinkAccessTestKernel::baseDir());
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string}>
     */
    public static function nestedPages(): iterable
    {
        yield 'project id in {projectId}, {id} is the issue' => ['issue_show', ['projectId' => 'p1', 'id' => 'i9'], '/projects/p1'];
        yield 'project id in {tenantSlug}, {id} is the monitor' => ['monitor_show', ['tenantSlug' => 'p2', 'id' => 'm9'], '/projects/p2'];
        yield 'project id in {spaceSlug} with a name' => ['wiki_page', ['spaceSlug' => 'p3--Team', 'id' => 'w9'], '/projects/p3'];
        yield 'project id in {spaceSlug} alone' => ['wiki_page', ['spaceSlug' => 'p4', 'id' => 'w9'], '/projects/p4'];
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('nestedPages')]
    public function testParentCrumbTakesItsParameterFromTheMappedAttribute(string $route, array $params, string $expected): void
    {
        $this->boot(null);

        self::assertStringContainsString('href="'.$expected.'"', $this->render($route, $params));
    }

    public function testWithoutTheMappingTheParentCrumbTakesTheChildId(): void
    {
        $this->boot(null, withParams: false);

        self::assertStringContainsString('href="/projects/i9"', $this->render('issue_show', ['projectId' => 'p1', 'id' => 'i9']));
    }

    public function testRefusedCrumbRendersItsLabelWithoutALink(): void
    {
        $checker = $this->boot('app.link_checker');
        $checker->denied = ['project_show'];

        $html = $this->render('issue_show', ['projectId' => 'p1', 'id' => 'i9']);

        self::assertStringContainsString('Project', $html);
        self::assertStringNotContainsString('/projects/p1"', $html);
        self::assertStringContainsString('href="/"', $html, 'the other crumbs keep theirs');
        self::assertContains(['project_show', ['id' => 'p1']], $checker->asked);
    }

    public function testHostServiceIsNotUsedUntilTheOptionNamesIt(): void
    {
        $checker = $this->boot(null);
        $checker->denied = ['project_show'];

        self::assertStringContainsString('href="/projects/p1"', $this->render('issue_show', ['projectId' => 'p1', 'id' => 'i9']));
        self::assertSame([], $checker->asked, 'autowiring must not pick up the interface alias');
    }

    private function boot(?string $linkChecker, bool $withParams = true): DenyListLinkChecker
    {
        $this->kernel = new LinkAccessTestKernel($linkChecker);
        $this->kernel->boot();

        $em = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(BreadcrumbCollection::class), $em->getClassMetadata(BreadcrumbItem::class)]);

        $project = ['routeName' => 'project_show', 'label' => 'Project', 'children' => [
            ['routeName' => 'issue_show', 'label' => 'Issue'],
            ['routeName' => 'monitor_show', 'label' => 'Monitor'],
            ['routeName' => 'wiki_page', 'label' => 'Page'],
        ]];
        if ($withParams) {
            $project['params'] = ['id' => ['@projectId', '@tenantSlug', '@spaceSlug|before:--']];
        }

        $importer = $this->service(BreadcrumbImporter::class);
        self::assertInstanceOf(BreadcrumbImporter::class, $importer);
        $result = $importer->import(['collection' => ['code' => 'default'], 'items' => [
            ['routeName' => 'home', 'label' => 'Home', 'children' => [$project]],
        ]]);
        self::assertSame([], $result['errors']);

        $checker = $this->service('app.link_checker');
        self::assertInstanceOf(DenyListLinkChecker::class, $checker);

        return $checker;
    }

    /**
     * @param array<string, string> $params
     */
    private function render(string $route, array $params): string
    {
        $request = Request::create('/');
        $request->attributes->add($params);
        $request->attributes->set('_route', $route);
        $request->attributes->set('_route_params', $params);
        $stack = $this->service('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $stack->push($request);

        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->createTemplate('{{ breadcrumb_render() }}')->render();
    }

    private function service(string $id): object
    {
        self::assertNotNull($this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container->get($id);
    }
}
