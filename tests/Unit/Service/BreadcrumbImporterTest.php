<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BreadcrumbImporterTest extends TestCase
{
    public function testNormalizeImportPayloadWrapsListOfBlocks(): void
    {
        $payload = [
            ['collection' => ['code' => 'a'], 'items' => []],
            ['collection' => ['code' => 'b'], 'items' => []],
        ];

        $normalized = BreadcrumbImporter::normalizeImportPayload($payload);

        self::assertArrayHasKey('collections', $normalized);
        self::assertCount(2, $normalized['collections']);
    }

    public function testNormalizeImportPayloadLeavesNonListUntouched(): void
    {
        $payload = ['collection' => ['code' => 'solo'], 'items' => []];

        self::assertSame($payload, BreadcrumbImporter::normalizeImportPayload($payload));
    }

    public function testNormalizeImportPayloadLeavesInvalidListUntouched(): void
    {
        $payload = [['foo' => 'bar']];

        self::assertSame($payload, BreadcrumbImporter::normalizeImportPayload($payload));
    }

    public function testImportCreatesNewCollectionWithTree(): void
    {
        $store = new BreadcrumbStore();

        $result = $store->importer()->import([
            'collection' => ['code' => 'demo', 'name' => 'Demo'],
            'items' => [
                [
                    'routeName' => 'home',
                    'label' => 'Home',
                    'children' => [
                        ['routeName' => 'child', 'label' => 'Child', 'staticRouteParams' => ['id' => 1]],
                    ],
                ],
            ],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertSame([], $result['errors']);
        $collection = $store->collections->findOneByCodeAndContextKey('demo');
        self::assertInstanceOf(BreadcrumbCollection::class, $collection);
        self::assertSame('Demo', $collection->getName());
        self::assertSame(['home', 'child'], array_map(static fn (BreadcrumbItem $i): string => $i->getRouteName(), $store->items->findAllForCollection($collection)));
        self::assertSame(0, $store->connection()->getTransactionNestingLevel());
    }

    public function testImportSkipsExistingCollectionWhenStrategyIsSkipExisting(): void
    {
        $store = new BreadcrumbStore();
        $store->importer()->import(['collection' => ['code' => 'demo'], 'items' => [['routeName' => 'home']]]);

        $result = $store->importer()->import([
            'collection' => ['code' => 'demo'],
            'items' => [['routeName' => 'other']],
        ], BreadcrumbImporter::STRATEGY_SKIP_EXISTING);

        self::assertSame(0, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(1, $result['skipped']);
        $collection = $store->collections->findOneByCodeAndContextKey('demo');
        self::assertInstanceOf(BreadcrumbCollection::class, $collection);
        self::assertSame(['home'], array_map(static fn (BreadcrumbItem $i): string => $i->getRouteName(), $store->items->findAllForCollection($collection)));
    }

    public function testImportPersistsPathPatternAndMatchAttributes(): void
    {
        $store = new BreadcrumbStore();

        $result = $store->importer()->import([
            'collection' => ['code' => 'demo'],
            'items' => [[
                'routeName' => '*',
                'pathPattern' => '^/shop',
                'matchAttributes' => ['tenant' => 'a', 1 => 'skip', 'x' => ['bad']],
                'label' => 'Shop',
            ]],
        ]);

        self::assertSame(1, $result['created']);
        $item = $this->items($store, 'demo')[0];
        self::assertSame('^/shop', $item->getPathPattern());
        self::assertSame(['tenant' => 'a'], $item->getMatchAttributes());
    }

    public function testImportReplacesExistingCollectionAndRemovesOldItems(): void
    {
        $store = new BreadcrumbStore();
        $store->importer()->import(['collection' => ['code' => 'demo'], 'items' => [['routeName' => 'old']]]);

        $result = $store->importer()->import([
            'collection' => ['code' => 'demo', 'name' => 'Updated'],
            'items' => [['routeName' => 'new_home', 'label' => 'New']],
        ], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame(0, $result['created']);
        self::assertSame(1, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertSame(['new_home'], array_map(static fn (BreadcrumbItem $i): string => $i->getRouteName(), $this->items($store, 'demo')));
        $collection = $store->collections->findOneByCodeAndContextKey('demo');
        self::assertInstanceOf(BreadcrumbCollection::class, $collection);
        self::assertSame('Updated', $collection->getName());
    }

    public function testImportCollectionsArrayReportsErrorsForInvalidEntries(): void
    {
        $result = (new BreadcrumbStore())->importer()->import([
            'collections' => [
                ['collection' => ['code' => ''], 'items' => []],
                ['collection' => ['code' => 'ok'], 'items' => 'not-array'],
                ['foo' => 'bar'],
            ],
        ]);

        self::assertNotEmpty($result['errors']);
    }

    public function testImportReportsInvalidShape(): void
    {
        $result = (new BreadcrumbStore())->importer()->import(['unexpected' => true]);

        self::assertCount(1, $result['errors']);
    }

    public function testImportSkipsDuplicateCollectionSignatureInSamePayload(): void
    {
        $store = new BreadcrumbStore();

        $result = $store->importer()->import([
            'collections' => [
                ['collection' => ['code' => 'dup'], 'items' => [['routeName' => 'a']]],
                ['collection' => ['code' => 'dup'], 'items' => [['routeName' => 'b']]],
            ],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame(['a'], array_map(static fn (BreadcrumbItem $i): string => $i->getRouteName(), $this->items($store, 'dup')));
    }

    public function testImportSkipsItemsWithEmptyRouteName(): void
    {
        $store = new BreadcrumbStore();

        $result = $store->importer()->import([
            'collection' => ['code' => 'demo'],
            'items' => [['routeName' => ''], ['label' => 'no route']],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame([], $this->items($store, 'demo'));
    }

    public function testImportLogsStartAndSuccessWithCounts(): void
    {
        $logger = $this->recordingLogger();
        $store = new BreadcrumbStore();
        $importer = new BreadcrumbImporter($store->collections, $store->items, $store->entityManager, $this->translator(), $logger, $store->invalidator, $store->runner);

        $importer->import(['collection' => ['code' => 'demo'], 'items' => []], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame([
            ['info', 'Breadcrumb import started.', ['bundle' => 'NowoBreadcrumbKitBundle', 'action' => 'import', 'strategy' => 'replace']],
            ['info', 'Breadcrumb import finished.', ['bundle' => 'NowoBreadcrumbKitBundle', 'action' => 'import', 'strategy' => 'replace', 'created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => 0]],
        ], $logger->records);
    }

    public function testImportLogsWarningWhenThePayloadHasErrors(): void
    {
        $logger = $this->recordingLogger();
        $store = new BreadcrumbStore();
        $importer = new BreadcrumbImporter($store->collections, $store->items, $store->entityManager, $this->translator(), $logger, $store->invalidator, $store->runner);

        $importer->import(['unexpected' => true]);

        self::assertSame('warning', $logger->records[1][0]);
        self::assertSame('Breadcrumb import finished with errors.', $logger->records[1][1]);
        self::assertSame(1, $logger->records[1][2]['errors']);
    }

    public function testImportDropsUnsafeIconsAndReportsThem(): void
    {
        $store = new BreadcrumbStore();
        $importer = new BreadcrumbImporter($store->collections, $store->items, $store->entityManager, $this->translator(), changeNotifier: $store->invalidator, transactionRunner: $store->runner);

        $result = $importer->import([
            'collection' => ['code' => 'demo', 'homeIcon' => '<img src=x onerror=alert(1)>'],
            'items' => [
                ['routeName' => 'home', 'icon' => '<i class="bi bi-house" onclick="alert(1)"></i>'],
                ['routeName' => 'blog', 'icon' => '<i class="bi bi-journal" style="x"></i>'],
            ],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame(['import.error.icon_rejected{"%code%":"demo","%count%":"2"}'], $result['errors']);

        $collection = $store->collections->findOneByCodeAndContextKey('demo');
        self::assertInstanceOf(BreadcrumbCollection::class, $collection);
        self::assertNull($collection->getHomeIcon());
        $items = $this->items($store, 'demo');
        self::assertNull($items[0]->getIcon());
        self::assertSame('<i class="bi bi-journal"></i>', $items[1]->getIcon());
    }

    public function testImportDropsInvalidPathPatternsAndReportsThem(): void
    {
        $store = new BreadcrumbStore();
        $importer = new BreadcrumbImporter($store->collections, $store->items, $store->entityManager, $this->translator(), changeNotifier: $store->invalidator, transactionRunner: $store->runner);

        $result = $importer->import([
            'collection' => ['code' => 'demo'],
            'items' => [
                ['routeName' => '*', 'pathPattern' => '^/(a+)+$'],
                ['routeName' => 'shop', 'pathPattern' => '^/shop/.+'],
            ],
        ]);

        self::assertSame(['import.error.path_pattern_rejected{"%code%":"demo","%count%":"1"}'], $result['errors']);
        $items = $this->items($store, 'demo');
        self::assertNull($items[0]->getPathPattern());
        self::assertSame('^/shop/.+', $items[1]->getPathPattern());
    }

    public function testImportLogsErrorAndRethrowsOnFailure(): void
    {
        $logger = $this->recordingLogger();
        $store = new BreadcrumbStore(createSchema: false);
        $importer = new BreadcrumbImporter($store->collections, $store->items, $store->entityManager, $this->translator(), $logger, $store->invalidator, $store->runner);

        try {
            $importer->import(['collection' => ['code' => 'demo'], 'items' => []]);
            self::fail('Expected the missing schema to fail the import.');
        } catch (DriverException $e) {
            self::assertStringContainsString('no such table', $e->getMessage());
        }

        self::assertSame('error', $logger->records[1][0]);
        self::assertSame('Breadcrumb import failed.', $logger->records[1][1]);
        self::assertSame(TableNotFoundException::class, $logger->records[1][2]['exception']);
    }

    /**
     * @return list<BreadcrumbItem>
     */
    private function items(BreadcrumbStore $store, string $code): array
    {
        $collection = $store->collections->findOneByCodeAndContextKey($code);
        self::assertInstanceOf(BreadcrumbCollection::class, $collection);

        return $store->items->findAllForCollection($collection);
    }

    private function recordingLogger(): RecordingLogger
    {
        return new RecordingLogger();
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = [], ?string $domain = null): string => $id
                .(NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN === $domain ? '' : '')
                .json_encode($params),
        );

        return $translator;
    }
}

/**
 * Minimal PSR-3 spy: keeps level, message and context of every record.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [\is_string($level) ? $level : 'unknown', (string) $message, $context];
    }
}
