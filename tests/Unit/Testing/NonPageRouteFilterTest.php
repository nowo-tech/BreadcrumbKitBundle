<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Testing;

use Nowo\BreadcrumbKitBundle\Testing\NonPageRouteFilter;
use Nowo\BreadcrumbKitBundle\Tests\Integration\Testing\CoverageAppController;
use Nowo\BreadcrumbKitBundle\Tests\Unit\Testing\Fixtures\InvokableDownload;
use Nowo\BreadcrumbKitBundle\Tests\Unit\Testing\Fixtures\UntypedController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\Routing\Route;

final class NonPageRouteFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Route, string|null}>
     */
    public static function routes(): iterable
    {
        $page = CoverageAppController::class.'::page';

        yield 'page' => ['app_items', new Route('/items', ['_controller' => $page]), null];
        yield 'GET page' => ['app_items', new Route('/items', [], [], [], '', [], ['GET', 'HEAD']), null];
        yield 'html format default' => ['app_items', new Route('/items', ['_format' => 'html']), null];
        yield 'placeholder that looks like an extension' => ['app_item', new Route('/items/{slug}'), null];
        yield 'unknown extension' => ['app_v', new Route('/release/v1.2'), null];
        yield 'POST only' => ['app_items_create', new Route('/items', [], [], [], '', [], ['POST']), 'not a GET route'];
        yield 'internal name' => ['_wdt', new Route('/wdt/{token}'), 'internal route'];
        yield 'internal path' => ['profiler', new Route('/_profiler/{token}'), 'internal route'];
        yield 'opted out' => ['app_health', new Route('/healthz', [], [], ['breadcrumb' => false]), 'route option "breadcrumb" is false'];
        yield 'format requirement' => ['app_feed', new Route('/feed', [], ['_format' => 'rss']), 'format requirement'];
        yield 'format placeholder' => ['app_feed', new Route('/feed.{_format}'), 'format requirement'];
        yield 'json format default' => ['app_feed', new Route('/feed', ['_format' => 'json']), 'format requirement'];
        yield 'csv download' => ['app_export', new Route('/items/export.CSV'), 'file extension'];
        yield 'redirect controller' => ['app_old', new Route('/old', ['_controller' => RedirectController::class.'::redirectAction']), 'redirect controller'];
        yield 'json controller' => ['app_api', new Route('/items.list', ['_controller' => CoverageAppController::class.'::json']), 'non-page response type'];
        yield 'redirect return type' => ['app_join', new Route('/join/{token}', ['_controller' => CoverageAppController::class.'::joinLink']), 'non-page response type'];
        yield 'invokable download' => ['app_dl', new Route('/dl', ['_controller' => InvokableDownload::class]), 'non-page response type'];
        yield 'unknown class' => ['app_x', new Route('/x', ['_controller' => 'App\\Missing::run']), null];
        yield 'unknown method' => ['app_x', new Route('/x', ['_controller' => CoverageAppController::class.'::missing']), null];
        yield 'no return type' => ['app_x', new Route('/x', ['_controller' => UntypedController::class.'::run']), null];
        yield 'builtin return type' => ['app_x', new Route('/x', ['_controller' => UntypedController::class.'::text']), null];
        yield 'union return type' => ['app_x', new Route('/x', ['_controller' => UntypedController::class.'::either']), null];
        yield 'empty controller' => ['app_x', new Route('/x', ['_controller' => '']), null];
        yield 'array controller' => ['app_x', new Route('/x', ['_controller' => [CoverageAppController::class, 'json']]), null];
    }

    #[DataProvider('routes')]
    public function testReasons(string $name, Route $route, ?string $reason): void
    {
        $filter = new NonPageRouteFilter();

        self::assertSame($reason, $filter->nonPageReason($name, $route));
        self::assertSame(null === $reason, $filter->isPage($name, $route));
    }

    public function testHostListsAndPredicate(): void
    {
        $filter = (new NonPageRouteFilter(
            ignoredRoutes: ['app_login'],
            isNonPage: static fn (string $name, Route $route): bool => 'ajax' === $route->getOption('kind'),
        ))->withIgnoredRoutes(['legal_*'])->withIgnoredPaths(['/api/*']);

        self::assertSame('ignored route name', $filter->nonPageReason('app_login', new Route('/login')));
        self::assertSame('ignored route name', $filter->nonPageReason('legal_terms', new Route('/terms')));
        self::assertSame('ignored route path', $filter->nonPageReason('app_api', new Route('/api/v1/items')));
        self::assertSame('custom predicate', $filter->nonPageReason('app_panel', new Route('/panel', [], [], ['kind' => 'ajax'])));
        self::assertTrue($filter->isPage('app_items', new Route('/items')));
    }

    public function testReturnTypeInspectionAndExtensionsCanBeChanged(): void
    {
        $filter = new NonPageRouteFilter(nonPageExtensions: ['pdf'], inspectControllerReturnTypes: false);

        self::assertTrue($filter->isPage('app_api', new Route('/items', ['_controller' => CoverageAppController::class.'::json'])));
        self::assertTrue($filter->isPage('app_export', new Route('/export.csv')));
        self::assertFalse($filter->isPage('app_invoice', new Route('/invoice.pdf')));
    }
}
