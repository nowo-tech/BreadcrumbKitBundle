<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\EventSubscriber;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Replaces a closed breadcrumb EntityManager before the next request uses it.
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
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->entityManagerResetter->resetIfClosed();
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->entityManagerResetter->resetIfClosed();
        }
    }
}
