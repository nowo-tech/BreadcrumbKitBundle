<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Enum;

/**
 * Bootstrap modal size tokens for dashboard dialogs (REQ-PHP-001).
 */
enum ModalSize: string
{
    case Normal = 'normal';
    case Lg = 'lg';
    case Xl = 'xl';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
