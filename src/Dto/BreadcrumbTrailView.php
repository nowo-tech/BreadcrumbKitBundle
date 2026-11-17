<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dto;

use OutOfBoundsException;

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
        public bool $homeIconReplacesLabel = true,
        public BreadcrumbCollapse $collapse = new BreadcrumbCollapse(),
    ) {
    }

    /**
     * Copy of this view with only the nodes replaced; every presentation property (icons, classes, responsive config,
     * collapse) is kept. The view stays immutable: this instance is not modified.
     *
     * @param list<BreadcrumbNode> $nodes
     */
    public function withNodes(array $nodes): static
    {
        return new static(
            $nodes,
            $this->homeIcon,
            $this->separatorIcon,
            $this->classList,
            $this->classItem,
            $this->classSeparator,
            $this->classCurrent,
            $this->responsiveConfig,
            $this->homeIconReplacesLabel,
            $this->collapse,
        );
    }

    /**
     * Copy of this view with the node at `$index` replaced.
     *
     * @throws OutOfBoundsException when there is no node at that index
     */
    public function withNode(int $index, BreadcrumbNode $node): static
    {
        if (!isset($this->nodes[$index])) {
            throw new OutOfBoundsException(\sprintf('The trail has no node at index %d.', $index));
        }
        $nodes = $this->nodes;
        $nodes[$index] = $node;

        return $this->withNodes(array_values($nodes));
    }

    /**
     * Copy of this view with every node passed through `$mapper(BreadcrumbNode $node, int $index): BreadcrumbNode`.
     * Returns this same instance when the mapper returned every node unchanged (identical objects), so a listener can
     * call `setView()` unconditionally without a "changed" flag.
     *
     * @param callable(BreadcrumbNode, int): BreadcrumbNode $mapper
     */
    public function mapNodes(callable $mapper): static
    {
        $nodes = [];
        $changed = false;
        foreach ($this->nodes as $index => $node) {
            $mapped = $mapper($node, $index);
            $changed = $changed || $mapped !== $node;
            $nodes[] = $mapped;
        }

        return $changed ? $this->withNodes($nodes) : $this;
    }

    /**
     * True when the default template renders the "collapse on narrow screens" markup for this trail
     * (option enabled and at least `min_items` crumbs). Twig: `trail.collapsible`.
     */
    public function isCollapsible(): bool
    {
        return $this->collapse->appliesTo(\count($this->nodes));
    }
}
