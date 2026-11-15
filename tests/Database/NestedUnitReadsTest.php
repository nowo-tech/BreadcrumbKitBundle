<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Database;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbRowLock;
use PHPUnit\Framework\TestCase;

/**
 * Audit QR regression tests (QR-M1, QR-L1): reads after the collection lock, inside a host transaction on MySQL
 * REPEATABLE READ (the host's earlier plain read fixed the snapshot). Two connections, one process, deterministic.
 * PostgreSQL's default READ COMMITTED runs the same tests as a control.
 */
final class NestedUnitReadsTest extends TestCase
{
    private BreadcrumbStore $a;

    private BreadcrumbStore $b;

    protected function setUp(): void
    {
        $url = BreadcrumbStore::serverUrl();
        if (null === $url) {
            self::markTestSkipped('needs BREADCRUMB_TEST_DATABASE_URL');
        }
        $this->a = new BreadcrumbStore($url);
        $this->b = new BreadcrumbStore($url, createSchema: false);
    }

    /** @return array<string, mixed> */
    private static function tree(string $tag): array
    {
        $items = [];
        for ($i = 0; $i < 10; ++$i) {
            $items[] = ['routeName' => $tag.$i];
        }

        return ['collection' => ['code' => 'main'], 'items' => $items];
    }

    private function itemCount(): int
    {
        return (int) $this->b->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item');
    }

    /** Control: outermost, the lock-first order holds. */
    public function testControlOutermostReplaceKeepsOneTree(): void
    {
        $this->a->importer()->import(self::tree('s'));
        $this->b->importer()->import(self::tree('b'), BreadcrumbImporter::STRATEGY_REPLACE);
        $this->a->importer()->import(self::tree('a'), BreadcrumbImporter::STRATEGY_REPLACE);
        self::assertSame(10, $this->itemCount());
    }

    /** QR-M1: the importer nested in a host transaction (documented as supported) keeps one tree. */
    public function testReplaceInsideAHostTransactionKeepsOneTree(): void
    {
        $this->a->importer()->import(self::tree('s'));
        self::assertSame(10, $this->itemCount());

        $this->a->connection()->beginTransaction();
        // The host's own work earlier in its transaction: any plain read fixes the REPEATABLE READ snapshot.
        $this->a->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item');

        // Another request replaces the collection and commits.
        $this->b->importer()->import(self::tree('b'), BreadcrumbImporter::STRATEGY_REPLACE);

        $result = $this->a->importer()->import(self::tree('a'), BreadcrumbImporter::STRATEGY_REPLACE);
        $this->a->connection()->commit();

        self::assertSame(1, $result['updated']);
        // The old items are read with a locking read: the snapshot of the host's earlier read does not hide b0..b9.
        self::assertSame(10, $this->itemCount(), 'one tree after a nested replace');
    }

    /** QR-L1: the dashboard's parent / cycle check reads the parent chain with locking reads. */
    public function testCycleCheckInsideAHostTransactionSeesTheCommittedChain(): void
    {
        $this->a->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'x'], ['routeName' => 'y']]]);
        $c = (int) $this->a->connection()->fetchOne('SELECT id FROM dashboard_breadcrumb_collection');
        $x = (int) $this->a->connection()->fetchOne("SELECT id FROM dashboard_breadcrumb_item WHERE routeName = 'x'");
        $y = (int) $this->a->connection()->fetchOne("SELECT id FROM dashboard_breadcrumb_item WHERE routeName = 'y'");

        $this->a->connection()->beginTransaction();
        $this->a->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item');

        // Admin B: x under y (committed).
        $this->b->connection()->executeStatement('UPDATE dashboard_breadcrumb_item SET parent_id = ? WHERE id = ?', [$y, $x]);

        // Admin A (nested): y under x. Lock the collection, then the same check as ItemCrudController::lockCollectionAndParent().
        $em = $this->a->entityManager;
        self::assertTrue(BreadcrumbRowLock::lock($em, BreadcrumbCollection::class, $c));
        self::assertTrue(BreadcrumbRowLock::lock($em, BreadcrumbItem::class, $y));
        $verdict = BreadcrumbRowLock::checkParent($em, $x, $y, $c);
        $this->a->connection()->rollBack();

        self::assertSame(BreadcrumbRowLock::PARENT_CYCLE, $verdict, 'the committed x -> y is seen: y under x would close a cycle');
    }

    /** Control for QR-L1: outermost, the same race answers CYCLE. */
    public function testControlOutermostCycleCheckSeesTheCycle(): void
    {
        $this->a->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'x'], ['routeName' => 'y']]]);
        $c = (int) $this->a->connection()->fetchOne('SELECT id FROM dashboard_breadcrumb_collection');
        $x = (int) $this->a->connection()->fetchOne("SELECT id FROM dashboard_breadcrumb_item WHERE routeName = 'x'");
        $y = (int) $this->a->connection()->fetchOne("SELECT id FROM dashboard_breadcrumb_item WHERE routeName = 'y'");
        $this->a->connection()->beginTransaction();
        $this->b->connection()->executeStatement('UPDATE dashboard_breadcrumb_item SET parent_id = ? WHERE id = ?', [$y, $x]);
        $em = $this->a->entityManager;
        BreadcrumbRowLock::lock($em, BreadcrumbCollection::class, $c);
        $verdict = BreadcrumbRowLock::checkParent($em, $x, $y, $c);
        $this->a->connection()->rollBack();
        self::assertSame(BreadcrumbRowLock::PARENT_CYCLE, $verdict);
    }
}
