<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Testing;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controllers of {@see CoverageTestKernel}: HTML pages and non-pages told apart by their return type.
 */
final class CoverageAppController
{
    public function page(): Response
    {
        return new Response('<html><body>page</body></html>');
    }

    public function json(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    public function download(): BinaryFileResponse
    {
        return new BinaryFileResponse(__FILE__);
    }

    public function joinLink(): RedirectResponse
    {
        return new RedirectResponse('/');
    }
}
