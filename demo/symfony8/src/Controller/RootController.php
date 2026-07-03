<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends / to the default locale home (localized routes live under /{_locale}/…).
 *
 * Registered as route `app_root` in config/routes.yaml (path `/`).
 */
final class RootController
{
    public function __invoke(UrlGeneratorInterface $urlGenerator): RedirectResponse
    {
        return new RedirectResponse($urlGenerator->generate('app_home', ['_locale' => 'en']));
    }
}
