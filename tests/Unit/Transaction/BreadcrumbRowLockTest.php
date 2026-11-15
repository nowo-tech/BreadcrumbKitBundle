<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Transaction;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\SequenceGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbRowLock;
use PHPUnit\Framework\TestCase;

final class BreadcrumbRowLockTest extends TestCase
{
    public function testLockAnswersWhetherTheRowExists(): void
    {
        $store = new BreadcrumbStore();
        $collection = $this->collection($store, 'main');

        $store->runner->run(static function () use ($store, $collection): void {
            $manager = $store->runner->manager();
            self::assertTrue(BreadcrumbRowLock::lock($manager, BreadcrumbCollection::class, (int) $collection->getId()));
            self::assertFalse(BreadcrumbRowLock::lock($manager, BreadcrumbCollection::class, 999));
        });
    }

    public function testLockCollectionReadsTheCommittedRowAgain(): void
    {
        $store = new BreadcrumbStore();
        $collection = $this->collection($store, 'main');
        $store->connection()->executeStatement("UPDATE dashboard_breadcrumb_collection SET name = 'committed elsewhere'");

        $store->runner->run(static function () use ($store, $collection): void {
            $locked = BreadcrumbRowLock::lockCollection($store->runner->manager(), (int) $collection->getId());
            self::assertSame($collection, $locked);
            self::assertSame('committed elsewhere', $locked->getName(), 'the managed entity is refreshed from the locked row');
            self::assertNull(BreadcrumbRowLock::lockCollection($store->runner->manager(), 999));
        });
    }

    public function testLockCollectionByKeyMatchesTheExactKeyOnly(): void
    {
        $store = new BreadcrumbStore();
        $this->collection($store, 'main');

        $store->runner->run(static function () use ($store): void {
            $manager = $store->runner->manager();
            [$found, $lookAlike] = BreadcrumbRowLock::lockCollectionByKey($manager, 'main', '');
            self::assertInstanceOf(BreadcrumbCollection::class, $found);
            self::assertFalse($lookAlike);
            self::assertSame([null, false], BreadcrumbRowLock::lockCollectionByKey($manager, 'absent', ''));
        });
    }

    public function testALookAlikeKeyIsNotTheCollection(): void
    {
        // A case-folding column (the default collation of MySQL, MariaDB and SQL Server): `Main` matches `main`.
        $store = new BreadcrumbStore(schemaReplacements: ['code VARCHAR(64) NOT NULL' => 'code VARCHAR(64) NOT NULL COLLATE NOCASE']);
        $this->collection($store, 'main');

        $store->runner->run(static function () use ($store): void {
            [$found, $lookAlike] = BreadcrumbRowLock::lockCollectionByKey($store->runner->manager(), 'Main', '');
            self::assertNull($found, 'compared with ===');
            self::assertTrue($lookAlike, 'the server matched a look-alike only');
        });
        self::assertNull($store->collections->findOneByCodeAndContextKey('MAIN'), 'no LIMIT before the exact match, no look-alike answer');
        self::assertInstanceOf(BreadcrumbCollection::class, $store->collections->findOneByCodeAndContextKey('main'));
    }

    public function testClaimInsertsTheKeyOnceAndTheLoserLearnsIt(): void
    {
        $store = new BreadcrumbStore();

        $store->runner->run(static function () use ($store): void {
            $manager = $store->runner->manager();
            self::assertTrue(BreadcrumbRowLock::claimCollection($manager, 'main', 'ctx'));
            self::assertFalse(BreadcrumbRowLock::claimCollection($manager, 'main', 'ctx'), 'the unique key arbitrates, without a failed flush');
            self::assertTrue($manager->isOpen());
        });

        self::assertSame('1', (string) $store->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
        self::assertSame(0, $store->connection()->getTransactionNestingLevel());
    }

    public function testClaimTakesTheIdFromTheSequenceWhenTheOrmUsesOne(): void
    {
        // PostgreSQL on doctrine/dbal 3: the ORM's sequence strategy, the column has no default.
        $store = new BreadcrumbStore();
        $metadata = $store->entityManager->getClassMetadata(BreadcrumbCollection::class);
        $generator = new class('breadcrumb_seq', 1) extends SequenceGenerator {
            public function generateId(EntityManagerInterface $em, ?object $entity): int
            {
                return 4242;
            }
        };
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_SEQUENCE);
        $metadata->setIdGenerator($generator);

        $store->runner->run(static function () use ($store): void {
            self::assertTrue(BreadcrumbRowLock::claimCollection($store->runner->manager(), 'main', ''));
        });

        self::assertSame('4242', (string) $store->connection()->fetchOne('SELECT id FROM dashboard_breadcrumb_collection'));
    }

    public function testCheckParentJudgesTheCommittedTree(): void
    {
        $store = new BreadcrumbStore();
        $collection = $this->collection($store, 'main');
        $other = $this->collection($store, 'other');
        $root = $this->item($store, $collection, null, 'root');
        $child = $this->item($store, $collection, $root, 'child');
        $grandchild = $this->item($store, $collection, $child, 'grandchild');
        $foreign = $this->item($store, $other, null, 'foreign');
        $collectionId = (int) $collection->getId();

        $store->runner->run(static function () use ($store, $collectionId, $root, $child, $grandchild, $foreign): void {
            $manager = $store->runner->manager();
            self::assertSame(BreadcrumbRowLock::PARENT_OK, BreadcrumbRowLock::checkParent($manager, null, null, $collectionId));
            self::assertSame(BreadcrumbRowLock::PARENT_OK, BreadcrumbRowLock::checkParent($manager, (int) $grandchild->getId(), null, $collectionId), 'a new item under any item of the collection');
            self::assertSame(BreadcrumbRowLock::PARENT_OK, BreadcrumbRowLock::checkParent($manager, (int) $child->getId(), (int) $grandchild->getId(), $collectionId));
            self::assertSame(BreadcrumbRowLock::PARENT_CYCLE, BreadcrumbRowLock::checkParent($manager, (int) $grandchild->getId(), (int) $root->getId(), $collectionId), 'the root under its own grandchild');
            self::assertSame(BreadcrumbRowLock::PARENT_CYCLE, BreadcrumbRowLock::checkParent($manager, (int) $root->getId(), (int) $root->getId(), $collectionId), 'an item under itself');
            self::assertSame(BreadcrumbRowLock::PARENT_MISSING, BreadcrumbRowLock::checkParent($manager, 999, null, $collectionId), 'deleted meanwhile');
            self::assertSame(BreadcrumbRowLock::PARENT_MISSING, BreadcrumbRowLock::checkParent($manager, (int) $foreign->getId(), null, $collectionId), 'another collection');
        });
    }

    public function testCheckParentStopsAtACycleThatAlreadyExists(): void
    {
        $store = new BreadcrumbStore();
        $collection = $this->collection($store, 'main');
        $first = $this->item($store, $collection, null, 'first');
        $second = $this->item($store, $collection, $first, 'second');
        $store->connection()->executeStatement('UPDATE dashboard_breadcrumb_item SET parent_id = ? WHERE id = ?', [$second->getId(), $first->getId()]);

        $store->runner->run(static function () use ($store, $collection, $second): void {
            self::assertSame(BreadcrumbRowLock::PARENT_CYCLE, BreadcrumbRowLock::checkParent($store->runner->manager(), (int) $second->getId(), null, (int) $collection->getId()));
        });
    }

    private function collection(BreadcrumbStore $store, string $code): BreadcrumbCollection
    {
        $collection = (new BreadcrumbCollection())->setCode($code);
        $store->entityManager->persist($collection);
        $store->entityManager->flush();

        return $collection;
    }

    private function item(BreadcrumbStore $store, BreadcrumbCollection $collection, ?BreadcrumbItem $parent, string $route): BreadcrumbItem
    {
        $item = (new BreadcrumbItem())->setCollection($collection)->setParent($parent)->setRouteName($route);
        $store->entityManager->persist($item);
        $store->entityManager->flush();

        return $item;
    }
}
