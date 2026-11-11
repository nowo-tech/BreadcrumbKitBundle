<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;

/**
 * Public contract of the breadcrumb loader (2.16, architecture bar rule 1): the trail of the current request as a
 * view model (DTOs only, no entity). Type-hint this interface instead of the final {@see BreadcrumbLoader}; decorate it
 * to post-process trails.
 */
interface BreadcrumbLoaderInterface
{
    /**
     * Trail of the current main request for a collection (and context key), already filtered, collapsed and
     * translated.
     */
    public function loadTrailView(string $collectionCode, string $contextKey = ''): BreadcrumbTrailView;
}
