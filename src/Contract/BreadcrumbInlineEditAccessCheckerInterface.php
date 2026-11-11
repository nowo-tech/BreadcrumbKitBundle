<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Contract;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Decides whether the inline breadcrumb editor (modal) may be shown for the current request.
 *
 * @deprecated since 2.18, implement {@see BreadcrumbEditAccessCheckerInterface} (framework-free context VO) instead;
 *             this interface is removed in 3.0. See docs/UPGRADING.md.
 */
interface BreadcrumbInlineEditAccessCheckerInterface
{
    /**
     * @param UserInterface|null $user Authenticated user, or null if anonymous / no security
     */
    public function canUseInlineBreadcrumbEditor(Request $request, ?UserInterface $user): bool;
}
