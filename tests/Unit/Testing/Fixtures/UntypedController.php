<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Testing\Fixtures;

use Symfony\Component\HttpFoundation\Response;

/**
 * Controllers whose return type does not tell a page from a non-page.
 */
final class UntypedController
{
    public function run() // @phpstan-ignore missingType.return
    {
        return new Response();
    }

    public function text(): string
    {
        return '';
    }

    public function either(bool $text = false): Response|string
    {
        return $text ? '' : new Response();
    }
}
