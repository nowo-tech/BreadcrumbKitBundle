<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbCollectionChangedEvent;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Drops the cached item list of every collection touched by a flush (dashboard CRUD, import, custom code) and, since
 * 2.16, dispatches {@see BreadcrumbCollectionChangedEvent} for each of them once the change is committed.
 *
 * Affected collections are collected in onFlush (while change sets are available); the cache entries are deleted in
 * postFlush, once the rows have been written. The events follow in postFlush when no transaction is open on that
 * connection, else when {@see self::dispatchPendingEvents()} runs after the outer commit (the importer calls it).
 * Pending events of a transaction that never committed are dropped at the end of the request (worker-safe).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class BreadcrumbCacheInvalidator implements ResetInterface, EventSubscriberInterface
{
    /** @var array<int, true> */
    private array $pendingCollectionIds = [];

    /** @var array<int, array{collection: BreadcrumbCollection, id: ?int, deleted: bool}> spl_object_id => collection touched by the current flush */
    private array $touchedCollections = [];

    /** @var array<int, BreadcrumbCollectionChangedEvent> collection id => event waiting for the commit */
    private array $pendingEvents = [];

    public function __construct(
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => ['reset', -1024]];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->cachePool instanceof CacheItemPoolInterface && !$this->eventDispatcher instanceof EventDispatcherInterface) {
            return;
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $deletions = $unitOfWork->getScheduledEntityDeletions();
        $entities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$deletions,
        ];

        foreach ($entities as $entity) {
            if ($entity instanceof BreadcrumbCollection) {
                $this->remember($entity, \in_array($entity, $deletions, true));
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
        // @igor-ignore - Cleared here, in reset() and at kernel.terminate.
        $this->pendingCollectionIds = [];

        foreach ($ids as $id) {
            $this->invalidateCollection($id);
        }

        // Ids of inserted collections exist only now; ids of deleted ones were captured in onFlush.
        foreach ($this->touchedCollections as $touched) {
            $id = $touched['id'] ?? $touched['collection']->getId();
            if (null !== $id && !($this->pendingEvents[$id] ?? null)?->deleted) {
                // @igor-ignore - Cleared in dispatchPendingEvents(), reset() and at kernel.terminate.
                $this->pendingEvents[$id] = new BreadcrumbCollectionChangedEvent($id, $touched['collection']->getCode(), $touched['collection']->getContextKey(), $touched['deleted']);
            }
        }
        // @igor-ignore - Cleared here, in reset() and at kernel.terminate.
        $this->touchedCollections = [];

        if (!$args->getObjectManager()->getConnection()->isTransactionActive()) {
            $this->dispatchPendingEvents();
        }
    }

    /**
     * Dispatches the collection-changed events waiting for a commit (2.16). Call it after committing a transaction in
     * which breadcrumb entities were flushed.
     */
    public function dispatchPendingEvents(): void
    {
        $events = $this->pendingEvents;
        // @igor-ignore - Cleared here, in reset() and at kernel.terminate.
        $this->pendingEvents = [];

        foreach ($events as $event) {
            $this->eventDispatcher?->dispatch($event);
        }
    }

    public function invalidateCollection(int $collectionId): void
    {
        $this->cachePool?->deleteItem(BreadcrumbLoader::cacheKey($collectionId));
    }

    public function reset(): void
    {
        $this->pendingCollectionIds = [];
        $this->touchedCollections = [];
        $this->pendingEvents = [];
    }

    private function remember(?BreadcrumbCollection $collection, bool $deleted = false): void
    {
        $id = $collection?->getId();
        if (null !== $id) {
            // @igor-ignore - Cleared in postFlush(), reset() and at kernel.terminate.
            $this->pendingCollectionIds[$id] = true;
        }

        if ($collection instanceof BreadcrumbCollection && $this->eventDispatcher instanceof EventDispatcherInterface && !($this->touchedCollections[spl_object_id($collection)]['deleted'] ?? false)) {
            // @igor-ignore - Cleared in postFlush(), reset() and at kernel.terminate.
            $this->touchedCollections[spl_object_id($collection)] = ['collection' => $collection, 'id' => $id, 'deleted' => $deleted];
        }
    }
}
