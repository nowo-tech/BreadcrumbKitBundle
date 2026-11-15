<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Support;

use Closure;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;

/**
 * A collection repository whose lookups can be followed by a competitor's commit: the importer's own read is the
 * "stale hint" of a race (the other writer commits right after it).
 */
final class RacingCollectionRepository extends BreadcrumbCollectionRepository
{
    /** Runs after the lookup returned its answer (the other process commits here). */
    public ?Closure $afterLookup = null;

    /** Answered instead of the real lookup when set (a hint that points at a row that is gone). */
    public ?BreadcrumbCollection $answer = null;

    public function findOneByCodeAndContextKey(string $code, string $contextKey = ''): ?BreadcrumbCollection
    {
        $found = $this->answer ?? parent::findOneByCodeAndContextKey($code, $contextKey);
        if (null !== $this->afterLookup) {
            $hook = $this->afterLookup;
            $this->afterLookup = null;
            $hook();
        }

        return $found;
    }
}
