<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Routes without locale prefix: favicon and browser well-known probes.
 *
 * Registered in config/routes.yaml (same pattern as {@see RootController}).
 */
final class SystemController
{
    public function favicon(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function wellKnownChrome(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
