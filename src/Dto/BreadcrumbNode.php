<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dto;

/**
 * Immutable crumb for Twig / JSON consumers.
 */
final readonly class BreadcrumbNode
{
    /**
     * @param array<string, scalar|null> $routeParams effective params used if url is set (debug / JSON-LD)
     */
    public function __construct(
        public string $label,
        public ?string $url,
        public bool $linkEnabled,
        public bool $current,
        public ?string $icon = null,
        public array $routeParams = [],
    ) {
    }
}
