<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dto;

/**
 * Framework-free view of the route a trail or an inline-edit check runs for (2.18, REQ-ARCH-001 rule 3).
 *
 * Built by the bundle from the current request; hosts read it instead of the HTTP request.
 */
final readonly class BreadcrumbRouteContext
{
    /**
     * @param string|null                $routeName       `_route` of the request (null without a matched route)
     * @param array<string, scalar|null> $routeParameters scalar `_route_params` of the request (objects, arrays and
     *                                                    positional keys are left out)
     * @param string                     $locale          request locale
     * @param string                     $path            path info of the request (no query string)
     */
    public function __construct(
        public ?string $routeName,
        public array $routeParameters,
        public string $locale,
        public string $path,
    ) {
    }
}
