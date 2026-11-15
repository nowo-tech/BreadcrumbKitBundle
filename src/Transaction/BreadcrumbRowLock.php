<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Transaction;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;

/**
 * 2.20 (Beacon spec 239): the bundle rows a unit of work writes, locked and read again.
 *
 * Inside a transaction only (a unit of work of {@see BreadcrumbTransactionRunner}). Rows are read with
 * `SELECT … FOR UPDATE` (no lock on SQLite, where a writing transaction locks the whole database). Lock order of the
 * bundle: the COLLECTION row first (it is the lock of its item tree), then nothing else: item rows are only written
 * under their collection's lock.
 *
 * Ids are compared with `===` after the read (a case-folding or PAD SPACE collation could match a look-alike key).
 *
 * Stateless.
 *
 * @internal
 */
final class BreadcrumbRowLock
{
    /** {@see self::checkParent()}: the parent can be used. */
    public const PARENT_OK = 'ok';

    /** {@see self::checkParent()}: the parent is gone or belongs to another collection. */
    public const PARENT_MISSING = 'missing';

    /** {@see self::checkParent()}: the parent chain leads back to the item. */
    public const PARENT_CYCLE = 'cycle';

    /** Name of the savepoint that wraps the claiming insert of a collection. */
    private const CLAIM_SAVEPOINT = 'nowo_breadcrumb_claim';

    /**
     * Write lock on one row by its id; the managed entity is left as it is (a form may have changed it).
     *
     * @param class-string $class Bundle entity class
     *
     * @return bool whether the row exists (any more)
     */
    public static function lock(EntityManagerInterface $entityManager, string $class, int $id): bool
    {
        /** @var list<array{id: int|string}> $rows */
        $rows = $entityManager->createQueryBuilder()
            ->select('e.id AS id')
            ->from($class, 'e')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getArrayResult();
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write lock on one collection by its id, the entity refreshed from the locked row (it holds the committed state).
     */
    public static function lockCollection(EntityManagerInterface $entityManager, int $id): ?BreadcrumbCollection
    {
        $collection = $entityManager->createQueryBuilder()
            ->select('c')
            ->from(BreadcrumbCollection::class, 'c')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            // A row the manager already holds is overwritten with the locked state.
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $collection instanceof BreadcrumbCollection ? $collection : null;
    }

    /**
     * Write lock on the collection of a (code, context key) pair, read again under the lock. A look-alike key (another
     * case, trailing spaces) is not the collection: the keys are compared with `===`.
     *
     * @return array{0: BreadcrumbCollection|null, 1: bool} the collection with exactly that key (or null), and whether
     *                                                      the server matched ONLY look-alike keys
     */
    public static function lockCollectionByKey(EntityManagerInterface $entityManager, string $code, string $contextKey): array
    {
        $rows = $entityManager->createQueryBuilder()
            ->select('c')
            ->from(BreadcrumbCollection::class, 'c')
            ->where('c.code = :code')
            ->andWhere('c.contextKey = :contextKey')
            ->setParameter('code', $code)
            ->setParameter('contextKey', $contextKey)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
        $rows = \is_array($rows) ? $rows : [];
        foreach ($rows as $row) {
            if ($row instanceof BreadcrumbCollection && $row->getCode() === $code && $row->getContextKey() === $contextKey) {
                return [$row, false];
            }
        }

        return [null, [] !== $rows];
    }

    /**
     * Claims the (code, context key) pair with a plain INSERT inside a savepoint: the unique key arbitrates between two
     * writers, and the loser learns it without a failed flush (which would close the entity manager).
     *
     * @return bool true when this call inserted the row (it is locked by the insert); false when the pair exists
     */
    public static function claimCollection(EntityManagerInterface $entityManager, string $code, string $contextKey): bool
    {
        $connection = $entityManager->getConnection();
        $metadata = $entityManager->getClassMetadata(BreadcrumbCollection::class);
        $platform = $connection->getDatabasePlatform();
        $connection->createSavepoint(self::CLAIM_SAVEPOINT);
        try {
            $connection->insert($platform->quoteSingleIdentifier($metadata->getTableName()), [
                $platform->quoteSingleIdentifier($metadata->getColumnName('code')) => $code,
                $platform->quoteSingleIdentifier($metadata->getColumnName('contextKey')) => $contextKey,
            ]);
            $connection->releaseSavepoint(self::CLAIM_SAVEPOINT);

            return true;
        } catch (UniqueConstraintViolationException) {
            $connection->rollbackSavepoint(self::CLAIM_SAVEPOINT);

            return false;
        }
    }

    /**
     * Inside a unit holding the collection's lock: whether the parent a form picked is still a usable parent for an item
     * of that collection, read from the committed rows (the form validated it before the lock, and a concurrent request
     * may have moved or deleted it since).
     *
     * @param int|null $parentId     the picked parent (null: a root item, always usable)
     * @param int|null $itemId       the item being edited (null: a new item)
     * @param int      $collectionId the collection of the item
     *
     * @return string one of {@see self::PARENT_OK}, {@see self::PARENT_MISSING} (gone, or in another collection) and
     *                {@see self::PARENT_CYCLE} (the item is the parent or one of its ancestors)
     */
    public static function checkParent(EntityManagerInterface $entityManager, ?int $parentId, ?int $itemId, int $collectionId): string
    {
        $seen = [];
        $first = true;
        for ($id = $parentId; null !== $id;) {
            if ($id === $itemId || isset($seen[$id])) {
                return self::PARENT_CYCLE;
            }
            $seen[$id] = true;
            /** @var list<array{parent: int|string|null, collection: int|string|null}> $rows */
            $rows = $entityManager->createQueryBuilder()
                ->select('IDENTITY(i.parent) AS parent', 'IDENTITY(i.collection) AS collection')
                ->from(BreadcrumbItem::class, 'i')
                ->where('i.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getArrayResult();
            if ([] === $rows || ($first && (int) $rows[0]['collection'] !== $collectionId)) {
                return self::PARENT_MISSING;
            }
            $first = false;
            $id = null === $rows[0]['parent'] ? null : (int) $rows[0]['parent'];
        }

        return self::PARENT_OK;
    }
}
