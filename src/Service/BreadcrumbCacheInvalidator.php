<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Drops the cached item list of every collection touched by a flush (dashboard CRUD, import, custom code).
 *
 * Affected collection ids are collected in onFlush (while change sets are available) and the cache
 * entries are deleted in postFlush, once the rows have been written.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class BreadcrumbCacheInvalidator implements ResetInterface
{
    /** @var array<int, true> */
    private array $pendingCollectionIds = [];

    public function __construct(
        private readonly ?CacheItemPoolInterface $cachePool = null,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->cachePool instanceof CacheItemPoolInterface) {
            return;
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $entities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$unitOfWork->getScheduledEntityDeletions(),
        ];

        foreach ($entities as $entity) {
            if ($entity instanceof BreadcrumbCollection) {
                $this->remember($entity);
            } elseif ($entity instanceof BreadcrumbItem) {
                $this->remember($entity->getCollection());
                // An item moved to another collection must also leave the previous collection's cache.
                $previous = $unitOfWork->getEntityChangeSet($entity)['collection'][0] ?? null;
                if ($previous instanceof BreadcrumbCollection) {
                    $this->remember($previous);
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $ids = array_keys($this->pendingCollectionIds);
        $this->pendingCollectionIds = [];

        foreach ($ids as $id) {
            $this->invalidateCollection($id);
        }
    }

    public function invalidateCollection(int $collectionId): void
    {
        $this->cachePool?->deleteItem(BreadcrumbLoader::cacheKey($collectionId));
    }

    public function reset(): void
    {
        $this->pendingCollectionIds = [];
    }

    private function remember(?BreadcrumbCollection $collection): void
    {
        $id = $collection?->getId();
        if (null !== $id) {
            $this->pendingCollectionIds[$id] = true;
        }
    }
}
