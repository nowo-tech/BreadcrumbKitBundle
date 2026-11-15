<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\EventSubscriber;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Replaces a closed breadcrumb EntityManager before the next request uses it.
 *
 * Only at the start of a main request (a boundary), never in the middle of one (2.20, audit QR-L4): a manager that a
 * failed write closed stays closed for the rest of that request, so the application's next flush fails loudly instead of
 * silently writing nothing (the shared manager is the application's own).
 *
 * Needed when FrankenPHP worker mode does not reset the kernel / services between
 * requests: a failed dashboard flush would otherwise break later trail loads
 * (including public {@code breadcrumb_render()}) on the same worker.
 */
final readonly class DashboardEntityManagerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private BreadcrumbEntityManagerResetter $entityManagerResetter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Early enough that repositories/controllers see an open manager.
            KernelEvents::REQUEST => ['onKernelRequest', 31],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->entityManagerResetter->resetIfClosed();
        }
    }
}
