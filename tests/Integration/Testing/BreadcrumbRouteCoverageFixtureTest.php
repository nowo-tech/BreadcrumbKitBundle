<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Testing;

use Nowo\BreadcrumbKitBundle\Testing\BreadcrumbRouteCoverageTrait;
use Nowo\BreadcrumbKitBundle\Testing\BreadcrumbRows;
use Nowo\BreadcrumbKitBundle\Testing\NonPageRouteFilter;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * The coverage trait with rows from fixture files and a host filter: it passes when every page has a row and fails
 * listing the pages without one, never the JSON, download, redirect, format, POST-only or internal routes.
 */
final class BreadcrumbRouteCoverageFixtureTest extends KernelTestCase
{
    use BreadcrumbRouteCoverageTrait;

    private const FIXTURES = __DIR__.'/../../fixtures/coverage/';

    private string $fixture = self::FIXTURES.'breadcrumbs.full.json';

    private bool $checkRows = true;

    protected static function getKernelClass(): string
    {
        return CoverageTestKernel::class;
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(CoverageTestKernel::baseDir());
        parent::tearDownAfterClass();
    }

    public function testPassesWhenEveryPageHasARow(): void
    {
        $this->assertEveryPageRouteHasBreadcrumb();
    }

    public function testFailsListingOnlyThePagesWithoutARow(): void
    {
        $this->fixture = self::FIXTURES.'breadcrumbs.partial.yaml';
        $this->checkRows = false;

        $message = $this->failureMessage(fn () => $this->assertEveryPageRouteHasBreadcrumb());

        self::assertStringContainsString('1 page route(s) have no breadcrumb row. Add them to '.$this->fixture, $message);
        self::assertStringContainsString('  - app_items ANY /items', $message);
        foreach (['app_items_api', 'app_items_export', 'app_item_download', 'app_join', 'app_legacy', 'app_feed', 'app_items_create', '_internal_toolbar', 'app_healthcheck', 'app_items_fragment'] as $nonPage) {
            self::assertStringNotContainsString(' - '.$nonPage.' ', $message);
        }
    }

    public function testFailsOnRowsNamingAMissingRouteOrParent(): void
    {
        $this->fixture = self::FIXTURES.'breadcrumbs.partial.yaml';

        $message = $this->failureMessage(fn () => self::assertBreadcrumbRowsAreConsistent($this->routes(), BreadcrumbRows::fromFile($this->fixture), 'partial.yaml'));

        self::assertStringContainsString('2 breadcrumb row problem(s) in partial.yaml', $message);
        self::assertStringContainsString('  - app_item_show: parent "app_items" has no row', $message);
        self::assertStringContainsString('  - app_gone: no such route', $message);
    }

    public function testControllerReturnTypesAndTheHostListDecideTheRemainingNonPages(): void
    {
        $rows = BreadcrumbRows::fromFile($this->fixture);

        self::assertSame(['app_items_fragment ANY /items/table-fragment'], self::missingBreadcrumbRoutes($this->routes(), $rows));
        self::assertSame([
            'app_item_download ANY /items/{id}/download',
            'app_items_api ANY /api/items',
            'app_items_fragment ANY /items/table-fragment',
            'app_join ANY /join/{token}',
        ], self::missingBreadcrumbRoutes($this->routes(), $rows, new NonPageRouteFilter(inspectControllerReturnTypes: false)));
    }

    public function testStaticAssertionPassesWithAHostPredicate(): void
    {
        $filter = new NonPageRouteFilter(isNonPage: static fn (string $name): bool => str_ends_with($name, '_fragment'));

        self::assertBreadcrumbCoverage($this->routes(), BreadcrumbRows::fromFile($this->fixture), $filter);
        self::assertBreadcrumbRowsAreConsistent($this->routes(), BreadcrumbRows::fromFile($this->fixture));
        self::assertBreadcrumbRowsAreConsistent($this->routes(), ['app_home' => null, '*' => 'app_home']);
    }

    protected function breadcrumbCoverageFixturePath(): string
    {
        return $this->fixture;
    }

    protected function breadcrumbCoverageFilter(): NonPageRouteFilter
    {
        return new NonPageRouteFilter(ignoredRoutes: ['*_fragment'], ignoredPaths: ['/api/*']);
    }

    protected function breadcrumbCoverageChecksRows(): bool
    {
        return $this->checkRows;
    }

    private function routes(): RouteCollection
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router->getRouteCollection();
    }

    private function failureMessage(callable $assertion): string
    {
        try {
            $assertion();
        } catch (AssertionFailedError $e) {
            return $e->getMessage();
        }
        self::fail('The assertion was expected to fail.');
    }
}
