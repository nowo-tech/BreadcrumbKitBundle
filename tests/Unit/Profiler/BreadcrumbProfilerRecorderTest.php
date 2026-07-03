<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Profiler;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Profiler\BreadcrumbProfilerRecorder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class BreadcrumbProfilerRecorderTest extends TestCase
{
    public function testRecordDoesNothingWhenDebugDisabled(): void
    {
        $request = Request::create('/');
        $stack = new RequestStack();
        $stack->push($request);

        $recorder = new BreadcrumbProfilerRecorder($stack, debug: false);
        $recorder->record('default', '', new BreadcrumbTrailView([]), 'ok');

        self::assertFalse($request->attributes->has(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE));
    }

    public function testRecordAppendsSnapshotWhenDebugEnabled(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'home');
        $stack = new RequestStack();
        $stack->push($request);

        $view = new BreadcrumbTrailView(
            [new BreadcrumbNode('Home', '/', true, true, 'house', ['id' => 1])],
            separatorIcon: '/',
            classList: 'trail',
        );

        $recorder = new BreadcrumbProfilerRecorder($stack, debug: true);
        $recorder->record('default', 'ctx', $view, 'ok', 'home', 'home');

        /** @var list<array<string, mixed>> $log */
        $log = $request->attributes->get(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE);
        self::assertCount(1, $log);
        self::assertSame('default', $log[0]['collection']);
        self::assertSame('ctx', $log[0]['contextKey']);
        self::assertSame('ok', $log[0]['status']);
        self::assertSame('home', $log[0]['requestRoute']);
        self::assertSame('home', $log[0]['matchedItemRoute']);
        self::assertSame(1, $log[0]['nodeCount']);
        self::assertSame('Home', $log[0]['nodes'][0]['label']);
        self::assertSame(['id'], $log[0]['nodes'][0]['routeParamKeys']);
        self::assertSame('trail', $log[0]['classList']);
    }

    public function testRecordDoesNothingWithoutCurrentRequest(): void
    {
        $recorder = new BreadcrumbProfilerRecorder(new RequestStack(), debug: true);
        $recorder->record('default', '', new BreadcrumbTrailView([]), 'no_http_request');

        self::assertTrue(true);
    }
}
