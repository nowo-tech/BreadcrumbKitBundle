<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\DataCollector;

use Nowo\BreadcrumbKitBundle\DataCollector\BreadcrumbDataCollector;
use Nowo\BreadcrumbKitBundle\Profiler\BreadcrumbProfilerRecorder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BreadcrumbDataCollectorTest extends TestCase
{
    public function testCollectStoresTrailsFromRequestAttribute(): void
    {
        $trails = [
            ['nodeCount' => 2, 'status' => 'ok'],
            ['nodeCount' => 0, 'status' => 'no_item_match'],
        ];

        $request = Request::create('/');
        $request->attributes->set(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE, $trails);

        $collector = new BreadcrumbDataCollector();
        $collector->collect($request, new Response());

        self::assertSame($trails, $collector->getTrails());
        self::assertSame(2, $collector->getSnapshotCount());
        self::assertSame(0, $collector->getLastNodeCount());
    }

    public function testCollectHandlesMissingOrInvalidAttribute(): void
    {
        $collector = new BreadcrumbDataCollector();
        $collector->collect(new Request(), new Response());

        self::assertSame([], $collector->getTrails());
        self::assertSame(0, $collector->getSnapshotCount());
        self::assertSame(0, $collector->getLastNodeCount());
    }

    public function testResetClearsData(): void
    {
        $request = Request::create('/');
        $request->attributes->set(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE, [
            ['nodeCount' => 3],
        ]);

        $collector = new BreadcrumbDataCollector();
        $collector->collect($request, new Response());
        $collector->reset();

        self::assertSame([], $collector->getTrails());
        self::assertSame(0, $collector->getSnapshotCount());
        self::assertSame(0, $collector->getLastNodeCount());
    }

    public function testGetNameAndTemplate(): void
    {
        $collector = new BreadcrumbDataCollector();

        self::assertSame('nowo_breadcrumb_kit', $collector->getName());
        self::assertSame('@NowoBreadcrumbKitBundle/Collector/breadcrumb.html.twig', BreadcrumbDataCollector::getTemplate());
    }
}
