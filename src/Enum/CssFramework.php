<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Enum;

/**
 * Dashboard CSS stack values for {@code nowo_breadcrumb_kit.dashboard.css_framework} (REQ-UI-001 / REQ-PHP-001).
 */
enum CssFramework: string
{
    case Bootstrap = 'bootstrap';
    case Bootstrap5 = 'bootstrap5';
    case Bootstrap4 = 'bootstrap4';
    case Tailwind = 'tailwind';
    case Foundation = 'foundation';
    case Custom = 'custom';
    case Tabler = 'tabler';
    case None = 'none';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Normalize config aliases ({@see Bootstrap} → {@see Bootstrap5}).
     */
    public function normalized(): self
    {
        return self::Bootstrap === $this ? self::Bootstrap5 : $this;
    }
}
