<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Nowo\BreadcrumbKitBundle\Doctrine\AfterCommitMiddleware;
use Nowo\BreadcrumbKitBundle\Doctrine\BreadcrumbChangeOutbox;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbCollectionChangedEvent;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * Drops the cached item list of every collection touched by a flush (dashboard CRUD, import, custom code) and, since
 * 2.16, dispatches {@see BreadcrumbCollectionChangedEvent} for each of them once the change is **committed**.
 *
 * Affected collections are collected in onFlush (while change sets are available; leftovers of a flush that threw are
 * dropped there); the cache entries are deleted in postFlush, once the rows have been written. The events of a flush
 * go to the {@see BreadcrumbChangeOutbox}, which {@see AfterCommitMiddleware} tells about the real transactions of the
 * bundle's connection (audit LI-BC-M1): a flush outside a transaction is committed at once; inside one, the events wait
 * for the outermost commit (the host's or the importer's) and a rollback — of the transaction or of the savepoint
 * that held them — drops them.
 *
 * Committed events are dispatched at the next safe point where DBAL reports no open transaction on the bundle's
 * connection: right after a flush, {@see self::dispatchPending()} (the importer calls it after its commit; a host may
 * call it after its own), and the end of the request / command / message. Never inside a flush or a transaction. All
 * state is dropped on kernel.reset (FrankenPHP worker mode without kernel reset).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class BreadcrumbCacheInvalidator implements ResetInterface, EventSubscriberInterface
{
    /** @var array<int, true> */
    private array $pendingCollectionIds = [];

    /** @var array<int, array{collection: BreadcrumbCollection, id: ?int, deleted: bool}> spl_object_id => collection touched by the current flush */
    private array $touchedCollections = [];

    /**
     * @param BreadcrumbEntityManagerResetter|null $managers the bundle's entity manager (its connection is the one the
     *                                                       events follow); null: the connection of the flush
     */
    public function __construct(
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly BreadcrumbChangeOutbox $outbox = new BreadcrumbChangeOutbox(),
        private readonly ?BreadcrumbEntityManagerResetter $managers = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onBoundary', -1024],
            KernelEvents::TERMINATE => ['onBoundary', -1024],
            ConsoleEvents::ERROR => ['onBoundary', -1024],
            ConsoleEvents::TERMINATE => ['onBoundary', -1024],
            // symfony/messenger is optional: `::class` names need no autoloading.
            WorkerMessageHandledEvent::class => ['onBoundary', -1024],
            WorkerMessageFailedEvent::class => ['onBoundary', -1024],
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        // A previous flush that threw ran onFlush but never postFlush: its collections must not be announced.
        // @igor-ignore - Cleared here, in postFlush(), at every boundary and in reset().
        $this->touchedCollections = [];
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
        // @igor-ignore - Cleared here, in onFlush(), at every boundary and in reset().
        $this->pendingCollectionIds = [];

        foreach ($ids as $id) {
            $this->invalidateCollection($id);
        }

        // Ids of inserted collections exist only now; ids of deleted ones were captured in onFlush.
        $events = [];
        foreach ($this->touchedCollections as $touched) {
            $id = $touched['id'] ?? $touched['collection']->getId();
            if (null !== $id) {
                $events[] = new BreadcrumbCollectionChangedEvent($id, $touched['collection']->getCode(), $touched['collection']->getContextKey(), $touched['deleted']);
            }
        }
        // @igor-ignore - Cleared here, in onFlush(), at every boundary and in reset().
        $this->touchedCollections = [];

        // Held by the open transaction of the bundle connection, or committed at once.
        $this->outbox->hold(...$events);
        $this->dispatchPending($args->getObjectManager()->getConnection());
    }

    /**
     * Dispatches the collection-changed events of committed writes when DBAL reports no open transaction on the
     * bundle's connection (2.16): one event per collection (a deletion wins). A listener that throws cannot undo the
     * commit: it is logged and the other events are still dispatched.
     *
     * @param Connection|null $connection connection to check when the bundle manager is not known (tests, custom setups)
     *
     * @return int number of events dispatched
     */
    public function dispatchPending(?Connection $connection = null): int
    {
        if (0 === $this->outbox->countCommitted()) {
            return 0;
        }

        $connection = $this->managers?->entityManager()->getConnection() ?? $connection;
        if ($connection?->isTransactionActive()) {
            return 0;
        }

        /** @var array<int, BreadcrumbCollectionChangedEvent> $byCollection */
        $byCollection = [];
        foreach ($this->outbox->takeCommitted() as $event) {
            if ($event instanceof BreadcrumbCollectionChangedEvent && !($byCollection[$event->collectionId] ?? null)?->deleted) {
                $byCollection[$event->collectionId] = $event;
            }
        }

        foreach ($byCollection as $event) {
            try {
                $this->eventDispatcher?->dispatch($event);
            } catch (Throwable $e) {
                $this->logger->error('Breadcrumb Kit: a listener failed after the commit.', [
                    'bundle' => 'NowoBreadcrumbKitBundle',
                    'event' => $event::class,
                    'exception' => $e::class,
                ]);
            }
        }

        return \count($byCollection);
    }

    /**
     * End of a request, command or message (also a failed one): dispatches what was committed and forgets the
     * collections of a flush that never completed.
     */
    public function onBoundary(): void
    {
        $this->pendingCollectionIds = [];
        $this->touchedCollections = [];
        $this->dispatchPending();
    }

    public function invalidateCollection(int $collectionId): void
    {
        $this->cachePool?->deleteItem(BreadcrumbLoader::cacheKey($collectionId));
    }

    public function reset(): void
    {
        $this->pendingCollectionIds = [];
        $this->touchedCollections = [];
        $this->outbox->reset();
    }

    private function remember(?BreadcrumbCollection $collection, bool $deleted = false): void
    {
        $id = $collection?->getId();
        if (null !== $id) {
            // @igor-ignore - Cleared in postFlush(), onFlush(), at every boundary and in reset().
            $this->pendingCollectionIds[$id] = true;
        }

        if ($collection instanceof BreadcrumbCollection && $this->eventDispatcher instanceof EventDispatcherInterface && !($this->touchedCollections[spl_object_id($collection)]['deleted'] ?? false)) {
            // @igor-ignore - Cleared in postFlush(), onFlush(), at every boundary and in reset().
            $this->touchedCollections[spl_object_id($collection)] = ['collection' => $collection, 'id' => $id, 'deleted' => $deleted];
        }
    }
}
