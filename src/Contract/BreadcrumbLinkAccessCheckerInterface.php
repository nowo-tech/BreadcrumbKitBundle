<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Contract;

/**
 * Decides whether a crumb may link to its page. Configured with `nowo_breadcrumb_kit.link_access_checker`.
 *
 * Asked once per crumb that would render a link, on every trail build (the answer is never cached by the bundle). The
 * current page's crumb, crumbs with links disabled and crumbs whose URL cannot be generated are not asked. Implementations
 * must stay stateless between requests (FrankenPHP worker mode), or implement ResetInterface.
 */
interface BreadcrumbLinkAccessCheckerInterface
{
    /**
     * @param string                     $route  route name of the crumb
     * @param array<string, scalar|null> $params parameters the link was generated with (static, mapped, copied from the
     *                                           current request and `_locale`)
     *
     * @return bool false keeps the crumb's label and drops its link
     */
    public function canLink(string $route, array $params): bool;
}
