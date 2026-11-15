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
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final class StorageFailureSubscriberTest extends TestCase
{
    public function testItListensAfterTheApplicationAndBeforeTheFrameworkErrorListener(): void
    {
        self::assertSame([KernelEvents::EXCEPTION => ['onKernelException', -64]], StorageFailureSubscriber::getSubscribedEvents());
    }

    public function testATransientFailureOfABundleRouteAnswers503WithRetryAfter(): void
    {
        $event = $this->event(new TransientStorageFailure(7, 'deadlock'), 'nowo_breadcrumb_kit_dashboard_items_new');
        (new StorageFailureSubscriber())->onKernelException($event);

        $throwable = $event->getThrowable();
        self::assertInstanceOf(ServiceUnavailableHttpException::class, $throwable);
        self::assertSame(['Retry-After' => 7], $throwable->getHeaders());
        self::assertInstanceOf(TransientStorageFailure::class, $throwable->getPrevious(), 'kept for the logs');
    }

    public function testATransientFailureWithoutAHintAnswersRetryAfterOne(): void
    {
        $event = $this->event(new TransientStorageFailure(null, 'deadlock'), 'nowo_breadcrumb_kit_dashboard_import');
        (new StorageFailureSubscriber())->onKernelException($event);

        $throwable = $event->getThrowable();
        self::assertInstanceOf(ServiceUnavailableHttpException::class, $throwable);
        self::assertSame(['Retry-After' => 1], $throwable->getHeaders());
    }

    public function testARetryableDbalFailureThatTheRunnerDidNotWrapAnswers503(): void
    {
        $event = $this->event(new RuntimeException('wrapped', 0, new DeadlockException($this->driverException(), null)), null, 'Nowo\\BreadcrumbKitBundle\\Controller\\Dashboard\\CollectionCrudController::edit');
        (new StorageFailureSubscriber())->onKernelException($event);

        self::assertInstanceOf(ServiceUnavailableHttpException::class, $event->getThrowable());
    }

    public function testAUniqueKeyConflictAnswers409(): void
    {
        $event = $this->event(new UniqueConstraintViolationException($this->driverException(), new Query('INSERT', [], [])), 'nowo_breadcrumb_kit_dashboard_collections_new');
        (new StorageFailureSubscriber())->onKernelException($event);

        $throwable = $event->getThrowable();
        self::assertInstanceOf(ConflictHttpException::class, $throwable);
        self::assertInstanceOf(UniqueConstraintViolationException::class, $throwable->getPrevious());
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

    private function event(Throwable $throwable, ?string $route, ?string $controller = null): ExceptionEvent
    {
        $request = new Request();
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
