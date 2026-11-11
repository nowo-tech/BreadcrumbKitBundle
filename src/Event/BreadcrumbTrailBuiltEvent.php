<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Event;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbRouteContext;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbRequestContextFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after {@see BreadcrumbLoader} builds a trail view.
 * Subscribers may replace the view (enrich labels, inject nodes, hide crumbs).
 *
 * 2.16: {@see self::$routeName} and {@see self::$routeParameters} carry the matched route as scalars.
 * 2.18: {@see self::getRouteContext()} carries route, parameters, locale and path as a framework-free VO; the HTTP
 * request ({@see self::$request}) is deprecated and leaves the event in 3.0.
 */
final class BreadcrumbTrailBuiltEvent extends Event
{
    /** `_route` of the request the trail was built for (null without a request or route). */
    public readonly ?string $routeName;

    /** @var array<string, scalar|null> scalar `_route_params` of that request */
    public readonly array $routeParameters;

    private readonly ?BreadcrumbRouteContext $routeContext;

    /**
     * @param Request|null $request deprecated since 2.18 (read {@see self::getRouteContext()}); removed in 3.0
     */
    public function __construct(
        private BreadcrumbTrailView $view,
        public readonly string $collectionCode,
        public readonly string $contextKey,
        public readonly string $status,
        /**
         * @deprecated since 2.18, use {@see self::getRouteContext()} (or `$routeName` / `$routeParameters`); removed in 3.0
         */
        public readonly ?Request $request,
        public readonly ?string $matchedItemRoute,
    ) {
        $this->routeContext = null === $request ? null : BreadcrumbRequestContextFactory::route($request);
        $this->routeName = $this->routeContext?->routeName;
        $this->routeParameters = $this->routeContext->routeParameters ?? [];
    }

    /**
     * Route, scalar route parameters, locale and path of the request the trail was built for; null when the trail was
     * built without a request (console, preview).
     */
    public function getRouteContext(): ?BreadcrumbRouteContext
    {
        return $this->routeContext;
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
