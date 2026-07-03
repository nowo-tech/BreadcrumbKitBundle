<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dto;

/**
 * Context for optional inline edit UI next to rendered breadcrumbs.
 */
final readonly class BreadcrumbInlineEditContext
{
    public function __construct(
        public bool $show = false,
        public ?string $iframeUrl = null,
        public string $modalTitle = '',
    ) {
    }
}
