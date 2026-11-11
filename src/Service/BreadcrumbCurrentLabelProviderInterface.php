<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

/**
 * Read side of the current-crumb label (2.17): what {@see BreadcrumbLoader} asks while it builds a trail.
 * The write side is {@see BreadcrumbCurrentLabelInterface}; the bundle's {@see BreadcrumbCurrentLabel} implements both.
 * Plain scalars only (REQ-ARCH-001 rule 3): no framework type in the port.
 */
interface BreadcrumbCurrentLabelProviderInterface
{
    /**
     * Label for the trail built now for `$routeName`, or null to keep the item's label. Only a label set for that route
     * with the same scalar route parameters (non-scalar values are ignored) and, when it was restricted, that collection
     * / context key.
     *
     * @param array<array-key, mixed> $routeParams `_route_params` of the request the trail is built for
     */
    public function currentLabelFor(string $routeName, array $routeParams, string $collectionCode, string $contextKey, ?string $locale = null): ?string;
}
