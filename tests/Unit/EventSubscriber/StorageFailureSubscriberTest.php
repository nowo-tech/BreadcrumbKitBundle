<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\EventSubscriber;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;
use Exception;
use Nowo\BreadcrumbKitBundle\EventSubscriber\StorageFailureSubscriber;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final class StorageFailureSubscriberTest extends TestCase
{
    public function testItListensAfterTheApplicationAndBeforeTheFrameworkErrorListener(): void
    {
        self::assertSame([KernelEvents::EXCEPTION => ['onKernelException', -64]], StorageFailureSubscriber::getSubscribedEvents());
    }

    public function testATransientFailureOfABundleRouteAnswers409WithRetryAfterAndConflict001(): void
    {
        $event = $this->event(new TransientStorageFailure(7, 'deadlock'), 'nowo_breadcrumb_kit_dashboard_items_new');
        (new StorageFailureSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('1', $response->headers->get('Retry-After'));
        self::assertStringContainsString('data-conflict-code="CONFLICT_001"', (string) $response->getContent());
    }

    public function testAJsonRequestGetsTheCodeInTheBody(): void
    {
        $event = $this->event(new TransientStorageFailure(null, 'deadlock'), 'nowo_breadcrumb_kit_dashboard_import', null, true);
        (new StorageFailureSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('1', $response->headers->get('Retry-After'));
        self::assertSame('CONFLICT_001', json_decode((string) $response->getContent(), true)['code']);
    }

    public function testACommittedConflictAnswers409WithConflict002AndNoRetryAfter(): void
    {
        $failure = new TransientStorageFailure(1, 'late', new ConcurrencyConflict(1, TransientFailureKind::Deadlock, null, new RuntimeException('deadlock'), true));
        $event = $this->event($failure, 'nowo_breadcrumb_kit_dashboard_items_edit', null, true);
        (new StorageFailureSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(409, $response->getStatusCode());
        self::assertFalse($response->headers->has('Retry-After'));
        self::assertSame('CONFLICT_002', json_decode((string) $response->getContent(), true)['code']);
    }

    public function testTheStatusIsConfigurableTo503(): void
    {
        $event = $this->event(new TransientStorageFailure(1, 'deadlock'), 'nowo_breadcrumb_kit_dashboard_items_new', null, true);
        (new StorageFailureSubscriber(new ConflictResponsePolicy(503, 4)))->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('4', $response->headers->get('Retry-After'));
        self::assertSame('CONFLICT_001', json_decode((string) $response->getContent(), true)['code']);
    }

    public function testARetryableDbalFailureThatTheRunnerDidNotWrapAnswers409(): void
    {
        $event = $this->event(new RuntimeException('wrapped', 0, new DeadlockException($this->driverException(), null)), null, 'Nowo\\BreadcrumbKitBundle\\Controller\\Dashboard\\CollectionCrudController::edit', true);
        (new StorageFailureSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CONFLICT_001', json_decode((string) $response->getContent(), true)['code']);
    }

    public function testTheErrorPageModeKeepsTheHttpExceptionOfTheApplication(): void
    {
        $failure = new TransientStorageFailure(7, 'deadlock');
        $event = $this->event($failure, 'nowo_breadcrumb_kit_dashboard_items_new');
        (new StorageFailureSubscriber(new ConflictResponsePolicy(503, 1), null, true))->onKernelException($event);

        $throwable = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $throwable);
        self::assertSame(503, $throwable->getStatusCode());
        self::assertSame(['Retry-After' => '7'], $throwable->getHeaders());
        self::assertSame($failure, $throwable->getPrevious(), 'kept for the logs');
        self::assertNull($event->getResponse());
    }

    public function testTheErrorPageModeOfACommittedConflictHasNoRetryAfter(): void
    {
        $failure = new TransientStorageFailure(7, 'late', new ConcurrencyConflict(1, TransientFailureKind::Deadlock, null, new RuntimeException('deadlock'), true));
        $event = $this->event($failure, 'nowo_breadcrumb_kit_dashboard_items_new');
        (new StorageFailureSubscriber(null, null, true))->onKernelException($event);

        $throwable = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $throwable);
        self::assertSame(409, $throwable->getStatusCode());
        self::assertSame([], $throwable->getHeaders());
    }

    public function testAUniqueKeyViolationIsARealErrorNotAConflict(): void
    {
        $unique = new UniqueConstraintViolationException($this->driverException(), new Query('INSERT', [], []));
        $event = $this->event($unique, 'nowo_breadcrumb_kit_dashboard_collections_new');
        (new StorageFailureSubscriber())->onKernelException($event);

        self::assertSame($unique, $event->getThrowable());
        self::assertNull($event->getResponse());
    }

    public function testAnythingElseAndAnyOtherRouteIsLeftAlone(): void
    {
        $other = new RuntimeException('boom');
        $event = $this->event($other, 'nowo_breadcrumb_kit_dashboard_items_new');
        (new StorageFailureSubscriber())->onKernelException($event);
        self::assertSame($other, $event->getThrowable());

        $failure = new TransientStorageFailure(1, 'deadlock');
        $event = $this->event($failure, 'app_home', 'App\\Controller\\HomeController::index');
        (new StorageFailureSubscriber())->onKernelException($event);
        self::assertSame($failure, $event->getThrowable(), 'not a route of the bundle');

        $event = $this->event($failure, null, null);
        (new StorageFailureSubscriber())->onKernelException($event);
        self::assertSame($failure, $event->getThrowable(), 'no route, no controller');
    }

    private function event(Throwable $throwable, ?string $route, ?string $controller = null, bool $json = false): ExceptionEvent
    {
        $request = new Request();
        if ($json) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }
        if (null !== $route) {
            $request->attributes->set('_route', $route);
        }
        if (null !== $controller) {
            $request->attributes->set('_controller', $controller);
        }

        return new ExceptionEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }

    private function driverException(): DriverException
    {
        return new class('storage failure') extends Exception implements DriverException {
            public function getSQLState(): ?string
            {
                return null;
            }
        };
    }
}
