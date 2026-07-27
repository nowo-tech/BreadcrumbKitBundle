<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\EventSubscriber;

use Nowo\BreadcrumbKitBundle\Security\BreadcrumbKitAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces dashboard CRUD access via BreadcrumbKitAccessCheckerInterface (REQ-UI-002).
 */
final readonly class DashboardAccessSubscriber implements EventSubscriberInterface
{
    private const ROUTE_PREFIX = 'nowo_breadcrumb_kit_dashboard_';

    public function __construct(
        private BreadcrumbKitAccessCheckerInterface $accessChecker,
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        if (null === $route || !str_starts_with((string) $route, self::ROUTE_PREFIX)) {
            return;
        }

        $token = $this->tokenStorage?->getToken();
        $user = $token?->getUser();
        if (!\is_object($user) || !$this->accessChecker->canAccess($user)) {
            throw new AccessDeniedException(\sprintf('Breadcrumb Kit dashboard requires an authenticated user allowed by %s.', BreadcrumbKitAccessCheckerInterface::class));
        }
    }
}
