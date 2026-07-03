<?php

declare(strict_types=1);

namespace App\Breadcrumb;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbInlineEditAccessCheckerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Demo checker: inline edit allowed for everyone (typical public documentation sites).
 */
final class DemoBreadcrumbInlineEditAccess implements BreadcrumbInlineEditAccessCheckerInterface
{
    public function canUseInlineBreadcrumbEditor(Request $request, ?UserInterface $user): bool
    {
        return true;
    }
}
