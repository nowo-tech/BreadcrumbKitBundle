<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dto;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbEditAccessCheckerInterface;

/**
 * Everything {@see BreadcrumbEditAccessCheckerInterface} needs to decide whether the
 * inline breadcrumb editor may be shown (2.18, REQ-ARCH-001 rule 3): the route, the collection and the user, as
 * scalars. No Symfony type, so DDD / hexagonal hosts can implement the port in their own layer.
 *
 * An anonymous visitor, a request without a firewall and a host without the Security component all give
 * `userIdentifier === null` and `roles === []` (the same cases where the legacy port received `null` as the user).
 */
final readonly class BreadcrumbEditContext
{
    /**
     * @param string|null                $routeName       `_route` of the current request
     * @param array<string, scalar|null> $routeParameters scalar `_route_params` of the current request
     * @param string                     $locale          request locale
     * @param string                     $path            path info of the current request (no query string)
     * @param string                     $collectionCode  code of the collection whose editor is requested
     * @param string                     $contextKey      context key of that collection
     * @param string|null                $userIdentifier  `UserInterface::getUserIdentifier()` of the authenticated user,
     *                                                    null when anonymous
     * @param list<string>               $roles           `UserInterface::getRoles()` of that user, `[]` when anonymous
     */
    public function __construct(
        public ?string $routeName,
        public array $routeParameters,
        public string $locale,
        public string $path,
        public string $collectionCode,
        public string $contextKey,
        public ?string $userIdentifier,
        public array $roles,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->userIdentifier;
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }
}
