<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Testing\Fixtures;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invokable controller answering a streamed download.
 */
final class InvokableDownload
{
    public function __invoke(): StreamedResponse
    {
        return new StreamedResponse();
    }
}
