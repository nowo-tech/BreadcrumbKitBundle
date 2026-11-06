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

    public function testValueBreakingARequirementGivesNoLinkUnderTheProductionRecipe(): void
    {
        $this->boot(null);

        $html = $this->render('wiki_num', ['spaceSlug' => 'abc--Team']);
        self::assertStringContainsString('Number', $html);
        self::assertStringNotContainsString('/n/', $html);

        self::assertStringContainsString('href="/n/42"', $this->render('wiki_num', ['spaceSlug' => '42--Team']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostValues(): iterable
    {
        yield 'dots and a fragment' => ['evil.test%23--x'];
        yield 'fragment, passes the default requirement' => ['evil#--x'];
        yield 'query' => ['evil?--x'];
        yield 'path' => ['evil/--x'];
        yield 'userinfo' => ['evil@--x'];
    }

    #[DataProvider('hostValues')]
    public function testHostVariableCannotLeaveTheSite(string $slug): void
    {
        $this->boot(null);

        $html = $this->render('wiki_host', ['spaceSlug' => rawurldecode($slug)]);

        self::assertStringContainsString('Host', $html);
        self::assertStringNotContainsString('example.test', $html);
        self::assertStringNotContainsString('evil', $html);
    }

    public function testValidHostVariableStillLinks(): void
    {
        $this->boot(null);

        self::assertStringContainsString('href="//team-1.example.test/t"', $this->render('wiki_host', ['spaceSlug' => 'team-1--Name']));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function localizedRoutes(): iterable
    {
        yield 'requirement, base name' => ['wiki_loc_num', 'abc--x', 'Number base'];
        yield 'requirement, full name' => ['wiki_loc_num_full', 'abc--x', 'Number full'];
        yield 'host variable, base name' => ['wiki_loc_host', 'evil.test#--x', 'Host base'];
        yield 'host variable, full name' => ['wiki_loc_host_full', 'evil.test#--x', 'Host full'];
    }

    #[DataProvider('localizedRoutes')]
    public function testLocalizedRouteGivesNoLinkForABadValue(string $page, string $slug, string $label): void
    {
        $this->boot(null);

        $html = $this->render($page, ['spaceSlug' => $slug]);

        self::assertStringContainsString($label, $html);
        self::assertStringNotContainsString('/en/', $html);
        self::assertStringNotContainsString('evil', $html);
    }

    public function testLocalizedRouteNamedByItsBaseNameStillLinks(): void
    {
        $this->boot(null);

        self::assertStringContainsString('href="/en/n/5"', $this->render('wiki_loc_num', ['spaceSlug' => '5--x']));
        self::assertStringContainsString('href="//acme.example.test/en/h"', $this->render('wiki_loc_host', ['spaceSlug' => 'acme--x']));
    }

    public function testRequestAttributesAreNotReadWithoutDeclaringThem(): void
    {
        $this->boot(null);

        $html = $this->render('issue_show', ['projectId' => 'p1', 'id' => 'i9'], ['tenantSlug' => 'not-a-route-param']);

        self::assertStringContainsString('href="/projects/p1"', $html);
        self::assertStringNotContainsString('not-a-route-param', $html);
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

        $number = ['routeName' => 'num_show', 'label' => 'Number', 'params' => ['id' => '@spaceSlug|before:--'], 'children' => [['routeName' => 'wiki_num', 'label' => 'Page']]];
        $host = ['routeName' => 'host_show', 'label' => 'Host', 'params' => ['sub' => '@spaceSlug|before:--'], 'children' => [['routeName' => 'wiki_host', 'label' => 'Page']]];

        $localized = [];
        foreach (['Base' => '', 'Full' => '.en'] as $label => $suffix) {
            $name = strtolower($label);
            $child = '' === $suffix ? '' : '_full';
            $localized[] = ['routeName' => 'loc_num'.$suffix, 'label' => 'Number '.$name, 'params' => ['id' => '@spaceSlug|before:--'], 'children' => [['routeName' => 'wiki_loc_num'.$child, 'label' => 'Page']]];
            $localized[] = ['routeName' => 'loc_host'.$suffix, 'label' => 'Host '.$name, 'params' => ['sub' => '@spaceSlug|before:--'], 'children' => [['routeName' => 'wiki_loc_host'.$child, 'label' => 'Page']]];
        }

        $importer = $this->service(BreadcrumbImporter::class);
        self::assertInstanceOf(BreadcrumbImporter::class, $importer);
        $result = $importer->import(['collection' => ['code' => 'default'], 'items' => [
            ['routeName' => 'home', 'label' => 'Home', 'children' => [$project, $number, $host, ...$localized]],
        ]]);
        self::assertSame([], $result['errors']);

        $checker = $this->service('app.link_checker');
        self::assertInstanceOf(DenyListLinkChecker::class, $checker);

        return $checker;
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $attributes extra request attributes, not route parameters
     */
    private function render(string $route, array $params, array $attributes = []): string
    {
        $request = Request::create('/');
        $request->attributes->add($params + $attributes);
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
