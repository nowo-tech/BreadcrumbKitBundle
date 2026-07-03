<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

interface BreadcrumbUrlResolverInterface
{
    /**
     * @param array<string, scalar|null> $staticParams
     * @param list<string>|null          $dynamicKeys
     *
     * @return array{0: ?string, 1: array<string, scalar|null>}
     */
    public function resolve(
        string $routeName,
        array $staticParams,
        ?array $dynamicKeys,
    ): array;
}
