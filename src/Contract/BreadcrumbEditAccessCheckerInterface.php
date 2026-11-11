<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Contract;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbEditContext;

/**
 * Decides whether the inline breadcrumb editor (modal) may be shown (2.18). Framework-free successor of
 * {@see BreadcrumbInlineEditAccessCheckerInterface}: register the service under `inline_edit.access_services` exactly
 * like the legacy one. When a service implements both interfaces, only this one is asked.
 *
 * Deny by default: anything but `true` hides the editor. Implementations must stay stateless between requests
 * (FrankenPHP worker mode); everything request-specific arrives in the context.
 */
interface BreadcrumbEditAccessCheckerInterface
{
    public function canEditInline(BreadcrumbEditContext $context): bool;
}
