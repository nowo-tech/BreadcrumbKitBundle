<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Controller\Dashboard;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Redirección segura al Referer (mismo host) tras guardar desde modal o página completa.
 */
trait DashboardRedirectTrait
{
    /**
     * @param array<string, mixed> $routeParams
     */
    private function redirectToRefererOr(Request $request, string $route, array $routeParams = []): RedirectResponse
    {
        $referer = $request->headers->get('Referer');
        if (null !== $referer && '' !== $referer) {
            $parsed = parse_url($referer);
            $host = $parsed['host'] ?? '';
            if ('' !== $host && $host === $request->getHost()) {
                return new RedirectResponse($referer);
            }
        }

        return $this->redirectToRoute($route, $routeParams);
    }
}
