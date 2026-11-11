<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Event;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after {@see BreadcrumbLoader} builds a trail view.
 * Subscribers may replace the view (enrich labels, inject nodes, hide crumbs).
 *
 * 2.16: {@see self::$routeName} and {@see self::$routeParameters} carry the matched route as scalars, for listeners that
 * do not want to depend on the HTTP request (`$request` stays).
 */
final class BreadcrumbTrailBuiltEvent extends Event
{
    /** `_route` of the request the trail was built for (null without a request or route). */
    public readonly ?string $routeName;

    /** @var array<string, scalar|null> scalar `_route_params` of that request */
    public readonly array $routeParameters;

    public function __construct(
        private BreadcrumbTrailView $view,
        public readonly string $collectionCode,
        public readonly string $contextKey,
        public readonly string $status,
        public readonly ?Request $request,
        public readonly ?string $matchedItemRoute,
    ) {
        $route = $request?->attributes->get('_route');
        $this->routeName = \is_string($route) ? $route : null;
        $parameters = $request?->attributes->get('_route_params');
        $scalars = [];
        foreach (\is_array($parameters) ? $parameters : [] as $name => $value) {
            if (\is_string($name) && (null === $value || \is_scalar($value))) {
                $scalars[$name] = $value;
            }
        }
        $this->routeParameters = $scalars;
    }

    public function getView(): BreadcrumbTrailView
    {
        return $this->view;
    }

    public function setView(BreadcrumbTrailView $view): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->view = $view;
    }
}
