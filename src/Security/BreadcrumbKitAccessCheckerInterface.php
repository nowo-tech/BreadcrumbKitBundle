<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Security;

/**
 * Global access control for Breadcrumb Kit dashboard CRUD routes (REQ-UI-002).
 */
interface BreadcrumbKitAccessCheckerInterface
{
    /**
     * @param object $user Authenticated security user
     */
    public function canAccess(object $user): bool;
}
