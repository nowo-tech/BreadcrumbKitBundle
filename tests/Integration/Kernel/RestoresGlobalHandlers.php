<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

/**
 * FrameworkBundle::boot() registers Symfony's ErrorHandler (error + exception handlers): put back the handlers PHPUnit
 * had installed after the kernel test, otherwise the test is reported as risky.
 */
trait RestoresGlobalHandlers
{
    /** @var callable|null */
    private $previousErrorHandler;

    /** @var callable|null */
    private $previousExceptionHandler;

    private function rememberGlobalHandlers(): void
    {
        $this->previousErrorHandler = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();
    }

    private function restoreGlobalHandlers(): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $this->previousExceptionHandler) {
                break;
            }
            restore_exception_handler();
        }

        for ($i = 0; $i < 100; ++$i) {
            $current = set_error_handler(static fn (): bool => false);
            restore_error_handler();
            if ($current === $this->previousErrorHandler) {
                break;
            }
            restore_error_handler();
        }
    }
}
