<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dto;

/**
 * "Collapse on narrow screens" settings of a trail (GitHub-style): below {@see $breakpoint} the trail shows
 * only a "…" disclosure listing the previous crumbs, one separator and the current crumb.
 *
 * Global defaults come from `presentation.collapse`; a collection may override them with
 * `responsiveConfig.collapse` (boolean shorthand or an object with `enabled`, `min_items`, `breakpoint`).
 */
final readonly class BreadcrumbCollapse
{
    public const DEFAULT_MIN_ITEMS = 3;

    public const DEFAULT_BREAKPOINT = '40rem';

    /** Smallest accepted `min_items` (a trail needs one previous crumb to collapse). */
    public const MIN_ITEMS_FLOOR = 2;

    /** CSS length allowed in the container / media query (it is printed into a `<style>` block). */
    private const BREAKPOINT_PATTERN = '/^(?:0|[1-9]\d{0,4})(?:\.\d{1,3})?(?:px|rem|em)$/';

    public function __construct(
        public bool $enabled = false,
        public int $minItems = self::DEFAULT_MIN_ITEMS,
        public string $breakpoint = self::DEFAULT_BREAKPOINT,
    ) {
        if ($minItems < self::MIN_ITEMS_FLOOR) {
            throw new \InvalidArgumentException(\sprintf('Breadcrumb collapse min_items must be >= %d, got %d.', self::MIN_ITEMS_FLOOR, $minItems));
        }

        if (!self::isValidBreakpoint($breakpoint)) {
            throw new \InvalidArgumentException(\sprintf('Invalid breadcrumb collapse breakpoint "%s": use a positive length in px, rem or em (e.g. "40rem", "640px") or an integer (pixels).', $breakpoint));
        }
    }

    public static function isValidBreakpoint(string $breakpoint): bool
    {
        return 1 === preg_match(self::BREAKPOINT_PATTERN, $breakpoint);
    }

    /**
     * Integer → pixels ("640" / 640 → "640px"); valid length string → trimmed; anything else → null.
     */
    public static function normalizeBreakpoint(mixed $value): ?string
    {
        if (\is_int($value)) {
            $value = (string) $value;
        }

        if (!\is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));
        if (1 === preg_match('/^\d+$/', $value)) {
            $value .= 'px';
        }

        return self::isValidBreakpoint($value) ? $value : null;
    }

    /**
     * Applies a collection override (`responsiveConfig.collapse`). Invalid values are ignored key by key,
     * so a typo in the database never breaks rendering; keys missing from an object keep the global value.
     */
    public function withOverride(mixed $override): self
    {
        if (\is_bool($override)) {
            return new self($override, $this->minItems, $this->breakpoint);
        }

        if (!\is_array($override)) {
            return $this;
        }

        $enabled = \is_bool($override['enabled'] ?? null) ? $override['enabled'] : $this->enabled;

        $minItems = $this->minItems;
        $rawMin = $override['min_items'] ?? null;
        if (\is_int($rawMin) && $rawMin >= self::MIN_ITEMS_FLOOR) {
            $minItems = $rawMin;
        }

        $breakpoint = self::normalizeBreakpoint($override['breakpoint'] ?? null) ?? $this->breakpoint;

        return new self($enabled, $minItems, $breakpoint);
    }

    /**
     * True when the trail with $crumbCount crumbs renders the collapse markup.
     */
    public function appliesTo(int $crumbCount): bool
    {
        return $this->enabled && $crumbCount >= $this->minItems;
    }
}
