<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\EventSubscriber;

use Nowo\BreadcrumbKitBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbKitAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

final class DashboardAccessSubscriberTest extends TestCase
{
    public function testIgnoresNonDashboardRoutes(): void
    {
        $checker = $this->createMock(BreadcrumbKitAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        $subscriber = new DashboardAccessSubscriber($checker);
        $subscriber->onKernelController($this->controllerEvent('app_home'));
    }

    public function testSubscribedEvents(): void
    {
        self::assertArrayHasKey(KernelEvents::CONTROLLER, DashboardAccessSubscriber::getSubscribedEvents());
    }

    public function testAllowsWhenCheckerPasses(): void
    {
        $user = $this->createMock(UserInterface::class);
        $checker = $this->createMock(BreadcrumbKitAccessCheckerInterface::class);
        $checker->expects(self::once())->method('canAccess')->with($user)->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        $subscriber = new DashboardAccessSubscriber($checker, $storage);
        $subscriber->onKernelController($this->controllerEvent('nowo_breadcrumb_kit_dashboard_collections_index'));
    }

    public function testDeniesAnonymousUser(): void
    {
        $checker = $this->createMock(BreadcrumbKitAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn(null);

        $subscriber = new DashboardAccessSubscriber($checker, $storage);

        $this->expectException(AccessDeniedException::class);
        $subscriber->onKernelController($this->controllerEvent('nowo_breadcrumb_kit_dashboard_collections_index'));
    }

    private function controllerEvent(string $route): ControllerEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new ControllerEvent($kernel, static fn () => null, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
