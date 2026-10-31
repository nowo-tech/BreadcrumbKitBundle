<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Testing;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * Asserts that every page route of the application has a breadcrumb row, and that every row names an existing route
 * and an existing parent row.
 *
 * ```php
 * final class BreadcrumbCoverageTest extends KernelTestCase
 * {
 *     use BreadcrumbRouteCoverageTrait;
 *
 *     public function testEveryPageHasABreadcrumb(): void
 *     {
 *         $this->assertEveryPageRouteHasBreadcrumb();
 *     }
 *
 *     protected function breadcrumbCoverageFixturePath(): ?string
 *     {
 *         return \dirname(__DIR__).'/fixtures/breadcrumbs.json'; // null (default): rows from the database
 *     }
 *
 *     protected function breadcrumbCoverageFilter(): NonPageRouteFilter
 *     {
 *         return new NonPageRouteFilter(ignoredRoutes: ['app_login', 'legal_*'], ignoredPaths: ['/api/*']);
 *     }
 * }
 * ```
 *
 * The high-level method needs a `KernelTestCase` (it reads the `router` and, without a fixture path, Doctrine's
 * entity manager from the test container); the static assertions work in any PHPUnit test. Needs `phpunit/phpunit`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
trait BreadcrumbRouteCoverageTrait
{
    /**
     * Checks the kernel's routes against the configured rows (see the hooks below).
     */
    protected function assertEveryPageRouteHasBreadcrumb(): void
    {
        $router = static::getContainer()->get('router');
        Assert::assertInstanceOf(RouterInterface::class, $router);
        $routes = $router->getRouteCollection();
        $rows = $this->breadcrumbCoverageRows();
        $source = $this->breadcrumbCoverageFixturePath() ?? 'the breadcrumb tables';

        self::assertBreadcrumbCoverage($routes, $rows, $this->breadcrumbCoverageFilter(), $source);
        if ($this->breadcrumbCoverageChecksRows()) {
            self::assertBreadcrumbRowsAreConsistent($routes, $rows, $source);
        }
    }

    /**
     * JSON or YAML fixture holding the rows; null reads the bundle tables through Doctrine.
     */
    protected function breadcrumbCoverageFixturePath(): ?string
    {
        return null;
    }

    /**
     * Collection code the database rows are limited to; null takes every collection.
     */
    protected function breadcrumbCoverageCollection(): ?string
    {
        return null;
    }

    /**
     * Which routes are pages (override to list non-pages or add a predicate).
     */
    protected function breadcrumbCoverageFilter(): NonPageRouteFilter
    {
        return new NonPageRouteFilter();
    }

    /**
     * Whether the rows themselves are checked too (existing route, parent with a row).
     */
    protected function breadcrumbCoverageChecksRows(): bool
    {
        return true;
    }

    /**
     * Row map "route => parent route" (override to read rows from another provider).
     *
     * @return array<string, string|null>
     */
    protected function breadcrumbCoverageRows(): array
    {
        $path = $this->breadcrumbCoverageFixturePath();

        return null !== $path ? BreadcrumbRows::fromFile($path) : $this->breadcrumbCoverageDatabaseRows();
    }

    /**
     * Rows of the bundle tables (Doctrine's default entity manager of the test container).
     *
     * @return array<string, string|null>
     */
    protected function breadcrumbCoverageDatabaseRows(): array
    {
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return BreadcrumbRows::fromEntityManager($entityManager, $this->breadcrumbCoverageCollection());
    }

    /**
     * Page routes without a row, as "name METHODS path" lines sorted by name.
     *
     * @param array<string, string|null> $rows
     *
     * @return list<string>
     */
    public static function missingBreadcrumbRoutes(RouteCollection $routes, array $rows, ?NonPageRouteFilter $filter = null): array
    {
        $filter ??= new NonPageRouteFilter();
        $missing = [];
        foreach ($routes->all() as $name => $route) {
            if (\array_key_exists($name, $rows) || !$filter->isPage($name, $route)) {
                continue;
            }
            $methods = [] !== $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY';
            $missing[$name] = $name.' '.$methods.' '.$route->getPath();
        }
        ksort($missing);

        return array_values($missing);
    }

    /**
     * Fails with the list of page routes that have no breadcrumb row.
     *
     * @param array<string, string|null> $rows
     */
    public static function assertBreadcrumbCoverage(RouteCollection $routes, array $rows, ?NonPageRouteFilter $filter = null, string $source = 'the breadcrumb rows'): void
    {
        $missing = self::missingBreadcrumbRoutes($routes, $rows, $filter);
        Assert::assertSame([], $missing, \sprintf(
            "%d page route(s) have no breadcrumb row. Add them to %s, or mark them as non-pages (route option \"breadcrumb: false\", NonPageRouteFilter ignoredRoutes / ignoredPaths / isNonPage):\n  - %s",
            \count($missing),
            $source,
            implode("\n  - ", $missing),
        ));
    }

    /**
     * Fails when a row names a route that does not exist or a parent that has no row (`*` rows are not routes).
     *
     * @param array<string, string|null> $rows
     */
    public static function assertBreadcrumbRowsAreConsistent(RouteCollection $routes, array $rows, string $source = 'the breadcrumb rows'): void
    {
        $problems = [];
        foreach ($rows as $route => $parent) {
            // `*` rows match by path pattern or request attributes, not by route name.
            if ('*' !== $route && null === $routes->get($route)) {
                $problems[] = \sprintf('%s: no such route', $route);
            }
            if (null !== $parent && !\array_key_exists($parent, $rows)) {
                $problems[] = \sprintf('%s: parent "%s" has no row', $route, $parent);
            }
        }
        Assert::assertSame([], $problems, \sprintf(
            "%d breadcrumb row problem(s) in %s:\n  - %s",
            \count($problems),
            $source,
            implode("\n  - ", $problems),
        ));
    }
}
