<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Controller\Dashboard;

/**
 * Shared helpers for breadcrumb kit dashboard controllers.
 */
trait DashboardControllerTrait
{
    /**
     * @param array<string, string> $sizes
     *
     * @return array{collection_form: string, item_form: string, import: string, delete: string}
     */
    private static function resolveModalClasses(array $sizes): array
    {
        $map = static fn (string $v): string => match ($v) {
            'lg' => 'modal-lg',
            'xl' => 'modal-xl',
            default => '',
        };

        return [
            'collection_form' => $map($sizes['collection_form'] ?? 'lg'),
            'item_form' => $map($sizes['item_form'] ?? 'lg'),
            'import' => $map($sizes['import'] ?? 'normal'),
            'delete' => $map($sizes['delete'] ?? 'normal'),
        ];
    }
}
