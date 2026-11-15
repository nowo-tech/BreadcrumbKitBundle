<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Transaction;

use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * 2.20 (audit lesson QN-M1): watches the flushes of one unit of work of {@see BreadcrumbTransactionRunner} on the shared
 * entity manager (the host's), and records whether one of them wrote an entity of the application (not a breadcrumb
 * row). Such a write is undone by a rollback of the unit while Doctrine keeps it as written, so the runner then closes
 * the manager: the application's next flush fails loudly instead of silently writing nothing.
 *
 * One instance per unit, registered on the manager's event manager for the unit only.
 *
 * @internal
 */
final class BreadcrumbFlushObserver
{
    /** Namespace of the bundle's entities. */
    private const ENTITY_NAMESPACE = 'Nowo\\BreadcrumbKitBundle\\Entity\\';

    /** A flush of the unit wrote (inserted, updated, deleted, or changed a collection of) an application entity. */
    public bool $hostWritten = false;

    /** Whether a class is one of the bundle's entities (its Doctrine proxy included). */
    public static function isBreadcrumbEntity(string $class): bool
    {
        return str_starts_with($class, self::ENTITY_NAMESPACE) || str_contains($class, '\\__CG__\\'.self::ENTITY_NAMESPACE);
    }

    /** Doctrine `onFlush`: what this flush is about to write. */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            $owner = $collection->getOwner();
            if (!\is_object($owner) || !self::isBreadcrumbEntity($owner::class)) {
                // @igor-ignore - One observer per unit of work, created and dropped by the runner: never a shared service.
                $this->hostWritten = true;

                return;
            }
        }
        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates(), ...$unitOfWork->getScheduledEntityDeletions()] as $entity) {
            if (!self::isBreadcrumbEntity($entity::class)) {
                // @igor-ignore - One observer per unit of work, created and dropped by the runner: never a shared service.
                $this->hostWritten = true;

                return;
            }
        }
    }
}
