<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbEditContext;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbRouteContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Turns the HTTP request (and the user) into the bundle's framework-free VOs. Stateless: every call reads only its
 * arguments.
 *
 * @internal
 */
final class BreadcrumbRequestContextFactory
{
    public static function route(Request $request): BreadcrumbRouteContext
    {
        $route = $request->attributes->get('_route');
        $parameters = $request->attributes->get('_route_params');
        $scalars = [];
        foreach (\is_array($parameters) ? $parameters : [] as $name => $value) {
            if (\is_string($name) && (null === $value || \is_scalar($value))) {
                $scalars[$name] = $value;
            }
        }

        return new BreadcrumbRouteContext(
            \is_string($route) ? $route : null,
            $scalars,
            $request->getLocale(),
            $request->getPathInfo(),
        );
    }

    public static function edit(Request $request, ?UserInterface $user, string $collectionCode, string $contextKey): BreadcrumbEditContext
    {
        $route = self::route($request);
        $roles = [];
        if ($user instanceof UserInterface) {
            foreach ($user->getRoles() as $role) {
                $roles[] = (string) $role;
            }
        }

        return new BreadcrumbEditContext(
            $route->routeName,
            $route->routeParameters,
            $route->locale,
            $route->path,
            $collectionCode,
            $contextKey,
            $user?->getUserIdentifier(),
            $roles,
        );
    }
}
