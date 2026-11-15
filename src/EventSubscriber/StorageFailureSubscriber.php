<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\EventSubscriber;

use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nowo\TransactionContract\TransientStorageFailure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * 2.20 (Beacon spec 239): a storage failure of a bundle route answers 503 or 409 instead of 500.
 *
 * - transient ({@see TransientStorageFailure} of the bundle's runner, or a DBAL {@see RetryableException}: deadlock,
 *   lock wait timeout): `503 Service Unavailable` with `Retry-After` (the runner's hint, else 1 s);
 * - conflict (a unique key refused the write of a concurrent request, for example a collection code and context key):
 *   `409 Conflict`.
 *
 * The exception becomes a {@see ServiceUnavailableHttpException} / {@see ConflictHttpException}, rendered by the
 * application's error page. The storage error stays the previous exception, for the logs: no SQL or parameter reaches
 * the client.
 *
 * Only the bundle's routes: a `nowo_breadcrumb_kit_*` route or a controller of the bundle (the dashboard path prefix is
 * configurable, the route names are not). Priority -64: after the application's own exception listeners, before the
 * framework's error listener (-128).
 */
final class StorageFailureSubscriber implements EventSubscriberInterface
{
    /** Route name prefix of the bundle. */
    private const ROUTE_PREFIX = 'nowo_breadcrumb_kit_';

    /** Controller namespace of the bundle. */
    private const CONTROLLER_PREFIX = 'Nowo\\BreadcrumbKitBundle\\Controller\\';

    /** Retry-After when the failure carries no hint. */
    private const DEFAULT_RETRY_AFTER = 1;

    /**
     * Listens on kernel.exception at -64.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -64],
        ];
    }

    /**
     * Maps a transient storage failure to 503 and a unique-key conflict to 409 on the bundle's routes.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->isBundleRequest($event->getRequest())) {
            return;
        }

        $failure = $event->getThrowable();
        $retry = $this->retryAfter($failure);
        if (null !== $retry) {
            $event->setThrowable(new ServiceUnavailableHttpException($retry, 'The service is temporarily unavailable. Retry later.', $failure));

            return;
        }

        if ($this->isUniqueViolation($failure)) {
            $event->setThrowable(new ConflictHttpException('A concurrent request changed the same data. Reload and retry.', $failure));
        }
    }

    /**
     * The Retry-After seconds of a transient failure; null when the failure is not transient.
     */
    private function retryAfter(Throwable $failure): ?int
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                return \max(1, $current->retryAfterSeconds() ?? self::DEFAULT_RETRY_AFTER);
            }
            if ($current instanceof RetryableException) {
                return self::DEFAULT_RETRY_AFTER;
            }
        }

        return null;
    }

    private function isUniqueViolation(Throwable $failure): bool
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof UniqueConstraintViolationException) {
                return true;
            }
        }

        return false;
    }

    private function isBundleRequest(Request $request): bool
    {
        $route = $request->attributes->get('_route');
        if (\is_string($route) && \str_starts_with($route, self::ROUTE_PREFIX)) {
            return true;
        }
        $controller = $request->attributes->get('_controller');

        return \is_string($controller) && \str_starts_with($controller, self::CONTROLLER_PREFIX);
    }
}
