<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Worker;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbLinkAccessCheckerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Per-user link access of the worker tests: alice may open the reports list, bob may not (the `X-User` header names
 * the user of the request). Stateless: every answer comes from the current request.
 */
final readonly class WorkerLinkChecker implements BreadcrumbLinkAccessCheckerInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function canLink(string $route, array $params): bool
    {
        if ('w_reports' !== $route) {
            return true;
        }

        return 'alice' === $this->requestStack->getMainRequest()?->headers->get('X-User');
    }
}
