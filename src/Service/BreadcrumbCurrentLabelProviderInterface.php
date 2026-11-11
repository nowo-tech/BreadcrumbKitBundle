<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Read side of the current-crumb label (2.17): what {@see BreadcrumbLoader} asks while it builds a trail.
 * The write side is {@see BreadcrumbCurrentLabelInterface}; the bundle's {@see BreadcrumbCurrentLabel} implements both.
 */
interface BreadcrumbCurrentLabelProviderInterface
{
    /**
     * Label for the trail built now for `$request`, or null to keep the item's label. Only a label set for that route
     * (same scalar route parameters, same main request) and, when it was restricted, that collection / context key.
     */
    public function currentLabelFor(Request $request, string $collectionCode, string $contextKey, ?string $locale = null): ?string;
}
