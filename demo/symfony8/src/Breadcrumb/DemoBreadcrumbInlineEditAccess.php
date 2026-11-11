<?php

declare(strict_types=1);

namespace App\Breadcrumb;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbEditAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbEditContext;

/**
 * Demo checker: inline edit allowed for everyone (typical public documentation sites).
 */
final class DemoBreadcrumbInlineEditAccess implements BreadcrumbEditAccessCheckerInterface
{
    public function canEditInline(BreadcrumbEditContext $context): bool
    {
        return true;
    }
}
