<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dto;

/**
 * Collection presentation + ordered nodes.
 */
final readonly class BreadcrumbTrailView
{
    /**
     * @param list<BreadcrumbNode> $nodes
     * @param array<string, mixed> $responsiveConfig
     */
    public function __construct(
        public array $nodes,
        public ?string $homeIcon = null,
        public ?string $separatorIcon = null,
        public ?string $classList = null,
        public ?string $classItem = null,
        public ?string $classSeparator = null,
        public ?string $classCurrent = null,
        public array $responsiveConfig = [],
    ) {
    }
}
