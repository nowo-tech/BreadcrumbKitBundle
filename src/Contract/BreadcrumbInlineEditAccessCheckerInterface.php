<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Contract;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Decides whether the inline breadcrumb editor (modal) may be shown for the current request.
 */
interface BreadcrumbInlineEditAccessCheckerInterface
{
    /**
     * @param UserInterface|null $user Authenticated user, or null if anonymous / no security
     */
    public function canUseInlineBreadcrumbEditor(Request $request, ?UserInterface $user): bool;
}
