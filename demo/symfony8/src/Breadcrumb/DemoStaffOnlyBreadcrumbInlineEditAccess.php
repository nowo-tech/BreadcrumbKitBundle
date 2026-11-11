<?php

declare(strict_types=1);

namespace App\Breadcrumb;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbEditAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbEditContext;

/**
 * Demo checker: only authenticated users may use inline edit (this demo has no firewall, so always false).
 */
final class DemoStaffOnlyBreadcrumbInlineEditAccess implements BreadcrumbEditAccessCheckerInterface
{
    public function canEditInline(BreadcrumbEditContext $context): bool
    {
        return $context->isAuthenticated();
    }
}
