<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * Builds URLs for breadcrumb items from named routes + merged static/dynamic params.
 */
final readonly class BreadcrumbUrlResolver implements BreadcrumbUrlResolverInterface
{
    /**
     * @param string|null $routerDefaultLocale the default locale the router's generator was given (`%kernel.default_locale%`
     *                                         through the container). Pass it when building this class by hand: with no
     *                                         `_locale` in the parameters or the request context, a localized route
     *                                         named by its base name cannot be found, so its requirements and host
     *                                         variables are not checked
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private RouterInterface $router,
        private ?string $routerDefaultLocale = null,
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
            foreach ($this->routesToCheck($routeName, $params) as $generated) {
                if (!$this->satisfiesRoute($generated, $params)) {
                    return [null, $params];
                }
            }

            $url = $this->urlGenerator->generate($routeName, $params, UrlGeneratorInterface::ABSOLUTE_PATH);

            return [$url, $params];
        } catch (Throwable) {
            // Throwable: an array `_locale` is a TypeError in the generator, and the form lets one reach here.
            return [null, $params];
        }
    }

    /**
     * The routes the generator may use. A localized route named by its base name (`x` for `x.en`) is only in the collection
     * under its full names. Same locale order as the generator: `_locale` parameter, context, default, with scalars cast;
     * `en_GB` tries `x.en_GB`, then `x.en`. A locale copy (`_canonical_route`) is what every generator uses. Anything else
     * under such a name (an alias, or an unrelated route called `x.en`) is used by some generators and not others, so
     * every route found has to pass, along with `x`.
     *
     * @param array<string, scalar|null> $params
     *
     * @return list<Route>
     */
    private function routesToCheck(string $name, array $params): array
    {
        try {
            $routes = $this->router->getRouteCollection();
        } catch (Exception) {
            return [];
        }

        $candidates = [];
        $locale = $params['_locale'] ?? $this->urlGenerator->getContext()->getParameter('_locale') ?: $this->routerDefaultLocale;
        if (\is_scalar($locale)) {
            $locale = (string) $locale;
            do {
                $route = $routes->get($name.'.'.$locale);
                if (null === $route) {
                    continue;
                }
                if ($route->getDefault('_canonical_route') === $name) {
                    return [$route];
                }
                $candidates[] = $route;
            } while (false !== $locale = strstr($locale, '_', true));
        }

        $base = $routes->get($name);
        if (null !== $base) {
            $candidates[] = $base;
        }

        return $candidates;
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
