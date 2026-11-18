<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\EventSubscriber;

use Doctrine\DBAL\Exception\RetryableException;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\ConflictResponsePass;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\Symfony\EventListener\ConcurrencyConflictResponder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * A lock conflict of a write of a bundle route answers the platform conflict answer instead of a 500.
 *
 * 2.23.0 (REQ-RUNTIME-003 rule 6): a transient failure ({@see TransientStorageFailure} of the bundle's runner, or a DBAL
 * {@see RetryableException}: deadlock, lock wait timeout, serialization failure) answers **409** (configurable to 503)
 * with a conflict code ({@see ConflictCode}): `CONFLICT_001` and `Retry-After` when nothing was committed, `CONFLICT_002`
 * and no `Retry-After` when part of the request was. The answer is the one of
 * {@see ConcurrencyConflictResponder::respond()} (nowo-tech/transaction-doctrine): JSON `{"error", "code", "message"}`
 * for JSON, XHR and `/api/` requests, else the "try again" page with `data-conflict-code`. With
 * `conflict_response.error_page: true` the failure is replaced by an {@see HttpException} with that status and
 * `Retry-After`, rendered by the application's error page (as up to 2.22, which answered 503). See
 * {@see ConflictResponsePass} for the wiring.
 *
 * A unique-key violation is not a lock conflict and is no longer mapped here (2.20 to 2.22 answered it 409): the
 * dashboard catches the expected "only one" races where they happen (the collection code and context key: a flash
 * message; the collection claim of the importer: the row of the winner), any other one is a real error. A concurrent
 * change of the item tree (a parent deleted, moved or made a descendant) is a domain 409 of the item controller, with
 * no `CONFLICT_*` code.
 *
 * Only the bundle's routes: a `nowo_breadcrumb_kit_*` route or a controller of the bundle (the dashboard path prefix is
 * configurable, the route names are not). Priority -64: after the application's own exception listeners, before the
 * framework's error listener (-128). Stateless.
 */
final readonly class StorageFailureSubscriber implements EventSubscriberInterface
{
    /** Route name prefix of the bundle. */
    private const ROUTE_PREFIX = 'nowo_breadcrumb_kit_';

    /** Controller namespace of the bundle. */
    private const CONTROLLER_PREFIX = 'Nowo\\BreadcrumbKitBundle\\Controller\\';

    private ConflictResponsePolicy $policy;

    private ConcurrencyConflictResponder $responder;

    /**
     * @param ConflictResponsePolicy|null       $policy    status (409 or 503) and Retry-After; null = 409 + Retry-After: 1
     * @param ConcurrencyConflictResponder|null $responder builds the answer; null = a plain one with $policy
     * @param bool                              $errorPage whether the application's error page renders the answer (no conflict code)
     */
    public function __construct(
        ?ConflictResponsePolicy $policy = null,
        ?ConcurrencyConflictResponder $responder = null,
        private bool $errorPage = false,
    ) {
        $this->policy = $policy ?? new ConflictResponsePolicy();
        $this->responder = $responder ?? new ConcurrencyConflictResponder($this->policy);
    }

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
     * Answers a lock conflict of a bundle route with the conflict status, the conflict code and, when retrying is safe,
     * Retry-After.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->isBundleRequest($event->getRequest())) {
            return;
        }

        $failure = $event->getThrowable();
        $transient = $this->transientFailure($failure);
        if (null === $transient) {
            return;
        }

        if ($this->errorPage) {
            $retrySafe = ConflictCode::forFailure($failure)->retrySafe();
            $event->setThrowable(new HttpException($this->policy->status, 'The service is temporarily unavailable. Retry later.', $failure, $retrySafe ? $this->policy->headers($this->hint($transient)) : []));

            return;
        }

        $event->setResponse($this->responder->respond($event->getRequest(), $failure));
    }

    /**
     * The transient failure in the chain (the runner's, or a DBAL retryable one); null when the failure is not transient.
     */
    private function transientFailure(Throwable $failure): ?Throwable
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure || $current instanceof RetryableException) {
                return $current;
            }
        }

        return null;
    }

    private function hint(Throwable $transient): ?int
    {
        return $transient instanceof TransientStorageFailure ? $transient->retryAfterSeconds() : null;
    }

    private function isBundleRequest(Request $request): bool
    {
        $route = $request->attributes->get('_route');
        if (\is_string($route) && str_starts_with($route, self::ROUTE_PREFIX)) {
            return true;
        }
        $controller = $request->attributes->get('_controller');

        return \is_string($controller) && str_starts_with($controller, self::CONTROLLER_PREFIX);
    }
}
