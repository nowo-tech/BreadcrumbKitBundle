<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Service;

use ArrayObject;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbCollectionChangedEvent;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use Nowo\BreadcrumbKitBundle\Tests\Support\RacingCollectionRepository;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 2.20 (Beacon spec 239): the import races, replayed deterministically on one connection (the competitor's commit is
 * injected right after the importer's own read).
 */
final class ImporterLoadReadinessTest extends TestCase
{
    public function testTwoImportsCreatingTheSameCollectionBothSucceed(): void
    {
        $store = new BreadcrumbStore();
        $repository = $this->racing($store);
        $repository->afterLookup = static function () use ($store): void {
            // The competing import committed its collection and its tree right after our read found nothing.
            $store->connection()->executeStatement("INSERT INTO dashboard_breadcrumb_collection (code, context_key) VALUES ('main', '')");
            $store->connection()->executeStatement("INSERT INTO dashboard_breadcrumb_item (collection_id, routeName, linkEnabled) VALUES (1, 'theirs', 1)");
        };

        $result = $this->importer($store, $repository)->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'ours']]], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0, 'errors' => []], $result, 'the loser replaces what the winner created: no unique violation');
        self::assertSame(['ours'], $this->routes($store, 'main'), 'one tree, not two');
        self::assertTrue($store->entityManager->isOpen());
        self::assertSame(0, $store->connection()->getTransactionNestingLevel());
    }

    public function testTheLoserOfACreateRaceSkipsWhenTheStrategyIsSkipExisting(): void
    {
        $store = new BreadcrumbStore();
        $repository = $this->racing($store);
        $repository->afterLookup = static function () use ($store): void {
            $store->connection()->executeStatement("INSERT INTO dashboard_breadcrumb_collection (code, context_key) VALUES ('main', '')");
        };

        $result = $this->importer($store, $repository)->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'ours']]]);

        self::assertSame(['created' => 0, 'updated' => 0, 'skipped' => 1, 'errors' => []], $result);
        self::assertSame([], $this->routes($store, 'main'));
    }

    public function testAHintThatPointsAtADeletedCollectionClaimsTheKeyAgain(): void
    {
        $store = new BreadcrumbStore();
        $repository = $this->racing($store);
        $gone = (new BreadcrumbCollection())->setCode('main');
        (new ReflectionProperty(BreadcrumbCollection::class, 'id'))->setValue($gone, 99);
        $repository->answer = $gone;

        $result = $this->importer($store, $repository)->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'ours']]], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame(1, $result['created']);
        self::assertSame(['ours'], $this->routes($store, 'main'));
    }

    public function testALookAlikeKeyIsReportedNotOverwritten(): void
    {
        // A case-folding column: the server matches `Main` to `main`; the bundle compares with ===.
        $store = new BreadcrumbStore(schemaReplacements: ['code VARCHAR(64) NOT NULL' => 'code VARCHAR(64) NOT NULL COLLATE NOCASE']);
        $store->importer()->import(['collection' => ['code' => 'main', 'name' => 'Original'], 'items' => [['routeName' => 'theirs']]]);

        $result = $store->importer()->import(['collection' => ['code' => 'Main', 'name' => 'Look-alike'], 'items' => [['routeName' => 'ours']]], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame(0, $result['created'] + $result['updated'] + $result['skipped']);
        self::assertSame(['import.error.collection_key_conflict{"%code%":"Main"}'], $result['errors']);
        self::assertSame(['theirs'], $this->routes($store, 'main'));
        self::assertSame('Original', $store->collections->findOneByCodeAndContextKey('main')?->getName());
    }

    public function testACollectionThatVanishesWhileItIsClaimedIsReplayedThenRefusedAsTransient(): void
    {
        // The unique index folds case, the column does not: the claim collides, the locking read finds nothing.
        $store = new BreadcrumbStore(schemaReplacements: ['(code, context_key)' => '(code COLLATE NOCASE, context_key)']);
        $store->importer()->import(['collection' => ['code' => 'main'], 'items' => []]);

        $this->expectException(TransientStorageFailure::class);
        try {
            $store->importer()->import(['collection' => ['code' => 'Main'], 'items' => []]);
        } finally {
            self::assertSame(0, $store->connection()->getTransactionNestingLevel());
        }
    }

    public function testATransientFailureReplaysTheUnitFromTheCommittedState(): void
    {
        $store = new BreadcrumbStore();
        $failures = 0;
        $store->entityManager->getEventManager()->addEventListener(Events::prePersist, new class($failures) {
            public function __construct(private int &$failures)
            {
            }

            public function prePersist(PrePersistEventArgs $args): void
            {
                if ($args->getObject() instanceof BreadcrumbItem && 0 === $this->failures) {
                    ++$this->failures;

                    throw new TransientStorageFailure(1, 'deadlock');
                }
            }
        });

        $result = $store->importer()->import([
            'collection' => ['code' => 'main', 'homeIcon' => '<img src=x onerror=alert(1)>'],
            'items' => [['routeName' => 'one'], ['routeName' => 'two']],
        ]);

        self::assertSame(1, $failures);
        self::assertSame(1, $result['created']);
        self::assertCount(1, $result['errors'], 'the rejected-values counters restart with the replay');
        self::assertSame(['one', 'two'], $this->routes($store, 'main'), 'the first attempt left nothing behind');
    }

    public function testAnImportInsideATransactionOfTheApplicationAnnouncesAfterTheApplicationCommits(): void
    {
        /** @var ArrayObject<int, string> $announced */
        $announced = new ArrayObject();
        $store = new BreadcrumbStore(dispatcher: $this->dispatcher($announced));

        $store->connection()->beginTransaction();
        $store->importer()->import(['collection' => ['code' => 'main'], 'items' => []]);
        self::assertSame([], $announced->getArrayCopy());
        $store->connection()->commit();
        $store->invalidator->dispatchPending($store->connection());

        self::assertSame(['main'], $announced->getArrayCopy());
    }

    public function testTheCacheEntryIsDroppedAgainOnceTheChangeIsCommitted(): void
    {
        $pool = new ArrayAdapter();
        $store = new BreadcrumbStore(pool: $pool);
        $store->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'old']]]);
        $key = BreadcrumbLoader::cacheKey(1);
        $this->prime($pool, $key);

        $store->runner->run(function () use ($store, $pool, $key): void {
            $collection = $store->collections->findOneByCodeAndContextKey('main');
            self::assertInstanceOf(BreadcrumbCollection::class, $collection);
            $collection->setName('changed');
            $store->entityManager->flush();
            self::assertFalse($pool->hasItem($key), 'dropped by the flush');
            // A reader of another connection still sees the old rows (the transaction is open) and caches them again.
            $this->prime($pool, $key);
        });

        self::assertFalse($pool->hasItem($key), 'dropped again after the commit: no stale trail survives it');
    }

    public function testACacheFailureCannotUndoTheWrite(): void
    {
        $pool = new class extends ArrayAdapter {
            public function deleteItem(mixed $key): bool
            {
                throw new RuntimeException('cache backend down');
            }
        };
        $store = new BreadcrumbStore(pool: $pool);

        $result = $store->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'a']]]);

        self::assertSame(1, $result['created']);
        self::assertSame(['a'], $this->routes($store, 'main'));
    }

    private function racing(BreadcrumbStore $store): RacingCollectionRepository
    {
        return new RacingCollectionRepository($store->registry);
    }

    private function importer(BreadcrumbStore $store, RacingCollectionRepository $repository): BreadcrumbImporter
    {
        return new BreadcrumbImporter($repository, $store->items, $store->entityManager, $this->translator(), changeNotifier: $store->invalidator, transactionRunner: $store->runner);
    }

    /**
     * @return list<string>
     */
    private function routes(BreadcrumbStore $store, string $code): array
    {
        $collection = $store->collections->findOneByCodeAndContextKey($code);
        if (!$collection instanceof BreadcrumbCollection) {
            return [];
        }

        return array_map(static fn (BreadcrumbItem $item): string => $item->getRouteName(), $store->items->findAllForCollection($collection));
    }

    private function prime(ArrayAdapter $pool, string $key): void
    {
        $pool->save($pool->getItem($key)->set('stale trail'));
    }

    /**
     * @param ArrayObject<int, string> $announced
     */
    private function dispatcher(ArrayObject $announced): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BreadcrumbCollectionChangedEvent::class, static function (BreadcrumbCollectionChangedEvent $event) use ($announced): void {
            $announced[] = $event->collectionCode;
        });

        return $dispatcher;
    }

    private function translator(): TranslatorInterface
    {
        return (new BreadcrumbStore())->translator();
    }
}
