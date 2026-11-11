<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Event;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbTrailBuiltEvent;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

final class BreadcrumbEventsTest extends TestCase
{
    public function testTrailBuiltEventCarriesTheRouteAsScalars(): void
    {
        $request = new Request(attributes: [
            '_route' => 'project_show',
            '_route_params' => ['id' => '42', 'page' => 2, 'flag' => null, 'object' => new stdClass(), 7 => 'positional'],
        ]);

        $event = new BreadcrumbTrailBuiltEvent(new BreadcrumbTrailView([]), 'main', '', 'ok', $request, 'project_show');

        self::assertSame('project_show', $event->routeName);
        self::assertSame(['id' => '42', 'page' => 2, 'flag' => null], $event->routeParameters);
        self::assertSame($request, $event->request);

        $withoutRequest = new BreadcrumbTrailBuiltEvent(new BreadcrumbTrailView([]), 'main', '', 'ok', null, null);
        self::assertNull($withoutRequest->routeName);
        self::assertSame([], $withoutRequest->routeParameters);
    }

    public function testResetterUsesAndResetsTheConfiguredManagerOnly(): void
    {
        $closed = $this->createStub(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('breadcrumb')->willReturn($closed);
        $registry->expects(self::never())->method('getManagerForClass');
        $registry->expects(self::once())->method('resetManager')->with('breadcrumb');

        $resetter = new BreadcrumbEntityManagerResetter($registry, 'breadcrumb');
        self::assertSame($closed, $resetter->entityManager());
        $resetter->resetIfClosed();
    }

    public function testResetterFailsClearlyWithoutAnOrmManager(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No Doctrine ORM entity manager maps');
        (new BreadcrumbEntityManagerResetter($registry))->entityManager();
    }
}
