<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolve a trail for a synthetic HTTP request (CLI / debug preview).
 */
final readonly class BreadcrumbTrailPreview
{
    public function __construct(
        private BreadcrumbLoader $loader,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, scalar|null> $routeParams
     * @param array<string, scalar|null> $attributes  Extra request attributes (merged after route params)
     */
    public function preview(
        string $collectionCode,
        string $contextKey,
        string $path,
        ?string $routeName,
        array $routeParams = [],
        array $attributes = [],
        string $method = 'GET',
        ?string $locale = null,
    ): BreadcrumbTrailView {
        $request = Request::create($path, $method);
        if (null !== $routeName && '' !== $routeName) {
            $request->attributes->set('_route', $routeName);
        }
        $request->attributes->set('_route_params', $routeParams);
        foreach ($attributes as $key => $value) {
            $request->attributes->set($key, $value);
        }
        if (null !== $locale && '' !== $locale) {
            $request->setLocale($locale);
        }

        $this->requestStack->push($request);
        try {
            return $this->loader->loadTrailView($collectionCode, $contextKey);
        } finally {
            $this->requestStack->pop();
        }
    }
}
