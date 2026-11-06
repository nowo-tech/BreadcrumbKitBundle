<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
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
        $route = null;

        try {
            $route = $this->router->getRouteCollection()->get($routeName);
            if ($route instanceof Route && $request instanceof Request) {
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
        } catch (Exception) {
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
            if ($route instanceof Route && !$this->satisfiesRoute($route, $params)) {
                return [null, $params];
            }

            $url = $this->urlGenerator->generate($routeName, $params, UrlGeneratorInterface::ABSOLUTE_PATH);

            return [$url, $params];
        } catch (Exception) {
            return [null, $params];
        }
    }

    /**
     * The generator's own requirement check, whatever `framework.router.strict_requirements` says: `null` (the production
     * recipe) turns it off, and values here can come from the URL of the current request. Host variables must also hold
     * only host name characters: the generator does not encode them, and `evil#` passes the default requirement.
     *
     * @param array<string, scalar|null> $params
     */
    private function satisfiesRoute(Route $route, array $params): bool
    {
        $compiled = $route->compile();
        $defaults = $route->getDefaults();
        $merged = array_replace($defaults, $this->urlGenerator->getContext()->getParameters(), $params);
        if (isset($defaults['_canonical_route'], $defaults['_locale'])) {
            $merged['_locale'] = $params['_locale'] ?? $defaults['_locale'];
        }

        $optional = true;
        foreach ($compiled->getTokens() as $token) {
            if ('variable' !== $token[0]) {
                $optional = false;

                continue;
            }
            $name = $token[3];
            if (!\array_key_exists($name, $merged)) {
                continue; // the generator reports the missing parameter
            }
            $isDefault = \array_key_exists($name, $defaults) && (null === $merged[$name] || self::text($merged[$name]) === self::text($defaults[$name]));
            if ($optional && !($token[5] ?? false) && $isDefault) {
                continue; // left out of the URL, as the generator does
            }
            if (!self::matchesToken($token, $merged[$name])) {
                return false;
            }
            $optional = false;
        }

        foreach ($compiled->getHostTokens() as $token) {
            if ('variable' === $token[0] && \array_key_exists($token[3], $merged)
                && (!self::matchesToken($token, $merged[$token[3]]) || 1 !== preg_match('/^[\p{L}\p{N}._-]+$/Du', self::text($merged[$token[3]])))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, mixed> $token a variable token of a compiled route
     */
    private static function matchesToken(array $token, mixed $value): bool
    {
        if (!\is_scalar($value) && null !== $value) {
            return false;
        }
        // Look-around patterns are ignored, as in Symfony's UrlGenerator: they refer to text around the variable.
        $requirement = preg_replace('/\(\?(?:=|<=|!|<!)((?:[^()\\\\]+|\\\\.|\((?1)\))*)\)/', '', (string) $token[2]);

        return 1 === preg_match('#^(?:'.$requirement.')$#Di'.(empty($token[4]) ? '' : 'u'), self::text($value));
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
