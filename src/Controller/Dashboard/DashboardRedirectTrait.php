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

    /**
     * After a delete: back to the list page the user came from (keeps its page / search query), else the list route.
     * Other referers (the deleted entity's edit page, the no-JS confirm page) would 404.
     *
     * @param array<string, mixed> $routeParams
     */
    private function redirectToListOr(Request $request, string $route, array $routeParams = []): RedirectResponse
    {
        $listPath = $this->generateUrl($route, $routeParams);
        $referer = $request->headers->get('Referer');
        if (null !== $referer && '' !== $referer) {
            $parsed = parse_url($referer);
            if (($parsed['host'] ?? '') === $request->getHost() && ($parsed['path'] ?? '') === $listPath) {
                return new RedirectResponse($referer);
            }
        }

        return $this->redirectToRoute($route, $routeParams);
    }
}
