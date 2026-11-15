<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Service;

use ArrayObject;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use Nowo\BreadcrumbKitBundle\Tests\Support\RacingCollectionRepository;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * Audit QR regression tests (QR-L2, QR-L3, QR-I4) on SQLite, shared manager (the default mode).
 */
final class AuditQrRegressionTest extends TestCase
{
    private function importer(BreadcrumbStore $store, RacingCollectionRepository $repository): BreadcrumbImporter
    {
        return new BreadcrumbImporter($repository, $store->items, $store->entityManager, $store->translator(), changeNotifier: $store->invalidator, transactionRunner: $store->runner);
    }

    /**
     * @return list<string>
     */
    private function routes(BreadcrumbStore $store, string $code): array
    {
        $store->entityManager->clear();
        $collection = $store->collections->findOneByCodeAndContextKey($code);
        if (!$collection instanceof BreadcrumbCollection) {
            return [];
        }

        return array_map(static fn (BreadcrumbItem $item): string => $item->getRouteName(), $store->items->findAllForCollection($collection));
    }

    /** QR-L2 (a): renamed between the hint read and the lock: the renamed collection is not the import's target. */
    public function testACollectionRenamedBetweenTheHintAndTheLockIsLeftAlone(): void
    {
        $store = new BreadcrumbStore();
        $store->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'kept']]]);
        $repository = new RacingCollectionRepository($store->registry);
        $repository->afterLookup = static function () use ($store): void {
            $store->connection()->executeStatement("UPDATE dashboard_breadcrumb_collection SET code = 'legacy' WHERE code = 'main'");
        };

        $result = $this->importer($store, $repository)->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'ours']]], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []], $result, 'a new "main" is created');
        self::assertSame(['kept'], $this->routes($store, 'legacy'), 'the renamed collection is untouched');
        self::assertSame(['ours'], $this->routes($store, 'main'));
        self::assertSame(2, (int) $store->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
    }

    /** QR-L2 (b): renamed AND a new "main" created meanwhile: the new one is replaced, no unique violation, manager open. */
    public function testARenamedAndRecreatedKeyReplacesTheNewCollectionWithoutClosingTheManager(): void
    {
        $store = new BreadcrumbStore();
        $store->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'kept']]]);
        $repository = new RacingCollectionRepository($store->registry);
        $repository->afterLookup = static function () use ($store): void {
            $store->connection()->executeStatement("UPDATE dashboard_breadcrumb_collection SET code = 'legacy' WHERE code = 'main'");
            $store->connection()->executeStatement("INSERT INTO dashboard_breadcrumb_collection (code, context_key) VALUES ('main', '')");
        };

        $result = $this->importer($store, $repository)->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'ours']]], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame(1, $result['updated']);
        self::assertTrue($store->entityManager->isOpen());
        self::assertSame(['kept'], $this->routes($store, 'legacy'));
        self::assertSame(['ours'], $this->routes($store, 'main'));
    }

    /** QR-L3: a failed item unit must not leave the edited item in the initialised `items` of its (kept) collection. */
    public function testAFailedItemUnitDoesNotResurrectTheItemThroughItsCollection(): void
    {
        $store = new BreadcrumbStore();
        $store->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'x', 'label' => 'original']]]);
        $store->entityManager->clear();
        $em = $store->entityManager;
        $collection = $store->collections->findOneByCodeAndContextKey('main');
        self::assertInstanceOf(BreadcrumbCollection::class, $collection);
        $item = $collection->getItems()->first();
        self::assertInstanceOf(BreadcrumbItem::class, $item);

        $item->setLabel('rejected edit');
        $failure = $this->failureOf(static fn (): mixed => $store->runner->run(static function (): void {
            throw new ConflictHttpException('A concurrent request changed the item tree. Reload and retry.');
        }));
        self::assertInstanceOf(ConflictHttpException::class, $failure);
        self::assertFalse($em->contains($item));

        $em->flush();

        $rows = $store->connection()->fetchAllAssociative('SELECT routeName, label FROM dashboard_breadcrumb_item ORDER BY id');
        self::assertCount(1, $rows, 'no copy of the rejected item was inserted');
        self::assertSame('original', $rows[0]['label']);
    }

    /** QR-I4: a closed shared manager is not replayed into; the original failure reaches the caller. */
    public function testAClosedSharedManagerStopsTheReplays(): void
    {
        $store = new BreadcrumbStore();
        $attempts = new ArrayObject(['n' => 0]);

        $failure = $this->failureOf(static fn (): mixed => $store->runner->runRetrying(static function () use ($store, $attempts): void {
            ++$attempts['n'];
            $store->entityManager->close();

            throw new TransientStorageFailure(1, 'deadlock inside the flush');
        }));

        self::assertInstanceOf(TransientStorageFailure::class, $failure);
        self::assertSame('deadlock inside the flush', $failure->getMessage(), 'the original failure, not the "manager closed" refusal');
        self::assertSame(1, $attempts['n']);
    }

    private function failureOf(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $failure) {
            return $failure;
        }

        self::fail('The operation must fail.');
    }
}
