<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\EventSubscriber\DashboardEntityManagerSubscriber;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class DashboardEntityManagerSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        self::assertSame([
            KernelEvents::REQUEST => ['onKernelRequest', 31],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ], DashboardEntityManagerSubscriber::getSubscribedEvents());
    }

    public function testResetsClosedManagerOnEveryMainRequestAndMainException(): void
    {
        $subscriber = new DashboardEntityManagerSubscriber($this->resetter(expectedResets: 3));

        $subscriber->onKernelRequest($this->requestEvent('nowo_breadcrumb_kit_dashboard_collections_new', HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onKernelRequest($this->requestEvent('app_home', HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onKernelException($this->exceptionEvent('app_product_show'));
    }

    public function testIgnoresSubRequests(): void
    {
        $subscriber = new DashboardEntityManagerSubscriber($this->resetter(expectedResets: 0));

        $subscriber->onKernelRequest($this->requestEvent('nowo_breadcrumb_kit_dashboard_collections_new', HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelRequest($this->requestEvent('app_home', HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelException($this->exceptionEvent('app_home', HttpKernelInterface::SUB_REQUEST));
    }

    private function resetter(int $expectedResets): BreadcrumbEntityManagerResetter
    {
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($closed);
        $registry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $registry->method('getManager')->willReturn($closed);
        $registry->expects(self::exactly($expectedResets))->method('resetManager')->willReturn($this->createMock(EntityManagerInterface::class));

        return new BreadcrumbEntityManagerResetter($registry);
    }

    private function requestEvent(?string $route, int $type): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), $this->request($route), $type);
    }

    private function exceptionEvent(string $route, int $type = HttpKernelInterface::MAIN_REQUEST): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $this->request($route),
            $type,
            new \RuntimeException('flush failed'),
        );
    }

    private function request(?string $route): Request
    {
        $request = Request::create('/');
        if (null !== $route) {
            $request->attributes->set('_route', $route);
        }

        return $request;
    }
}
