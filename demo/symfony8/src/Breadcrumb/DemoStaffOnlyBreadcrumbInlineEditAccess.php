<?php

declare(strict_types=1);

namespace App\Breadcrumb;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbInlineEditAccessCheckerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Demo checker: only authenticated users may use inline edit (this demo has no firewall, so always false).
 */
final class DemoStaffOnlyBreadcrumbInlineEditAccess implements BreadcrumbInlineEditAccessCheckerInterface
{
    public function canUseInlineBreadcrumbEditor(Request $request, ?UserInterface $user): bool
    {
        return null !== $user;
    }
}
