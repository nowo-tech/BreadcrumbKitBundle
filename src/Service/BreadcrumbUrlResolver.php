<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Builds URLs for breadcrumb items from named routes + merged static/dynamic params.
 */
final readonly class BreadcrumbUrlResolver implements BreadcrumbUrlResolverInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private RouterInterface $router,
    ) {
    }

    /**
     * @param array<string, scalar|null> $staticParams
     * @param list<string>|null          $dynamicKeys
     *
     * @return array{0: ?string, 1: array<string, scalar|null>}
     */
    public function resolve(
        string $routeName,
        array $staticParams,
        ?array $dynamicKeys,
    ): array {
        if ('' === $routeName) {
            return [null, []];
        }

        $params = $staticParams;
        $request = $this->requestStack->getCurrentRequest();
        $routeNeedsLocale = false;

        try {
            $route = $this->router->getRouteCollection()->get($routeName);
            if ($route instanceof \Symfony\Component\Routing\Route && $request instanceof Request) {
                $compiled = $route->compile();
                $pathVars = $compiled->getPathVariables();
                $routeNeedsLocale = \in_array('_locale', $pathVars, true);
                $currentParams = (array) $request->attributes->get('_route_params', []);
                foreach ($pathVars as $var) {
                    if (!\array_key_exists($var, $params) && \array_key_exists($var, $currentParams)) {
                        $params[$var] = $currentParams[$var];
                    }
                }
            }
        } catch (\Exception) {
        }

        if ($request instanceof Request && \is_array($dynamicKeys)) {
            $currentParams = (array) $request->attributes->get('_route_params', []);
            foreach ($dynamicKeys as $key) {
                if (!\array_key_exists($key, $params) && \array_key_exists($key, $currentParams)) {
                    $params[$key] = $currentParams[$key];
                }
            }
        }

        if ($request instanceof Request && $routeNeedsLocale && !\array_key_exists('_locale', $params)) {
            $params = ['_locale' => $request->getLocale()] + $params;
        }

        try {
            $url = $this->urlGenerator->generate($routeName, $params, UrlGeneratorInterface::ABSOLUTE_PATH);

            return [$url, $params];
        } catch (\Exception) {
            return [null, $params];
        }
    }
}
