<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Event;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after {@see BreadcrumbLoader} builds a trail view.
 * Subscribers may replace the view (enrich labels, inject nodes, hide crumbs).
 */
final class BreadcrumbTrailBuiltEvent extends Event
{
    public function __construct(
        private BreadcrumbTrailView $view,
        public readonly string $collectionCode,
        public readonly string $contextKey,
        public readonly string $status,
        public readonly ?Request $request,
        public readonly ?string $matchedItemRoute,
    ) {
    }

    public function getView(): BreadcrumbTrailView
    {
        return $this->view;
    }

    public function setView(BreadcrumbTrailView $view): void
    {
        $this->view = $view;
    }
}
