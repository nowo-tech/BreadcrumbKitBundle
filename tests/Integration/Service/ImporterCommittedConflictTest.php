<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Service;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * 2.23.0 (REQ-RUNTIME-003 rule 6): a conflict after an earlier collection of the same file committed is CONFLICT_002;
 * a conflict of the first collection (or inside a transaction of the caller) is CONFLICT_001.
 */
final class ImporterCommittedConflictTest extends TestCase
{
    private const TWO_COLLECTIONS = ['collections' => [
        ['collection' => ['code' => 'first'], 'items' => []],
        ['collection' => ['code' => 'second'], 'items' => [['routeName' => 'one']]],
    ]];

    public function testAConflictAfterAnEarlierCollectionCommittedIsConflict002(): void
    {
        $store = new BreadcrumbStore();
        $this->failItems($store, static fn (): Throwable => new TransientStorageFailure(1, 'deadlock'));

        try {
            $store->importer()->import(self::TWO_COLLECTIONS);
            self::fail('The second collection conflicts.');
        } catch (TransientStorageFailure $failure) {
            self::assertInstanceOf(ConcurrencyConflict::class, $failure);
            self::assertTrue($failure->committed);
            self::assertSame(ConflictCode::Committed, ConflictCode::forFailure($failure));
        }

        self::assertSame(1, (int) $store->connection()->fetchOne("SELECT COUNT(*) FROM dashboard_breadcrumb_collection WHERE code = 'first'"), 'the first collection stays committed');
        self::assertSame(0, $store->connection()->getTransactionNestingLevel());
    }

    public function testAConflictOfTheFirstCollectionIsConflict001(): void
    {
        $store = new BreadcrumbStore();
        $this->failItems($store, static fn (): Throwable => new TransientStorageFailure(1, 'deadlock'));

        try {
            $store->importer()->import(['collections' => [['collection' => ['code' => 'second'], 'items' => [['routeName' => 'one']]]]]);
            self::fail('The collection conflicts.');
        } catch (TransientStorageFailure $failure) {
            self::assertSame(ConflictCode::RetrySafe, ConflictCode::forFailure($failure));
        }
    }

    public function testAConflictInsideATransactionOfTheCallerIsConflict001(): void
    {
        $store = new BreadcrumbStore();
        $this->failItems($store, static fn (): Throwable => new TransientStorageFailure(1, 'deadlock'));

        try {
            $store->connection()->transactional(static fn (): array => $store->importer()->import(self::TWO_COLLECTIONS));
            self::fail('The second collection conflicts.');
        } catch (TransientStorageFailure $failure) {
            self::assertSame(ConflictCode::RetrySafe, ConflictCode::forFailure($failure), 'nothing is committed before the caller commits');
        }
    }

    public function testAnAlreadyCommittedConflictIsKept(): void
    {
        $store = new BreadcrumbStore();
        $conflict = new ConcurrencyConflict(1, TransientFailureKind::Deadlock, null, new RuntimeException('deadlock'), true);
        $this->failItems($store, static fn (): Throwable => $conflict);

        try {
            $store->importer()->import(self::TWO_COLLECTIONS);
            self::fail('The second collection conflicts.');
        } catch (TransientStorageFailure $failure) {
            self::assertSame(ConflictCode::Committed, ConflictCode::forFailure($failure));
        }
    }

    /**
     * @param callable(): Throwable $failure
     */
    private function failItems(BreadcrumbStore $store, callable $failure): void
    {
        $store->entityManager->getEventManager()->addEventListener(Events::prePersist, new class($failure) {
            /** @param callable(): Throwable $failure */
            public function __construct(private $failure)
            {
            }

            public function prePersist(PrePersistEventArgs $args): void
            {
                if ($args->getObject() instanceof BreadcrumbItem) {
                    throw ($this->failure)();
                }
            }
        });
    }
}
