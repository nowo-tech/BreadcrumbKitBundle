<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
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
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);

        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new BreadcrumbImporter($colRepo, $itemRepo, $em, $this->translator());

        $data = [
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
        ];

        $result = $importer->import($data);

        self::assertSame(1, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertSame([], $result['errors']);
    }

    public function testImportSkipsExistingCollectionWhenStrategyIsSkipExisting(): void
    {
        $existing = new BreadcrumbCollection();
        $existing->setCode('demo');
        $existing->setContextKey('');

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $importer = new BreadcrumbImporter(
            $colRepo,
            $this->createMock(BreadcrumbItemRepository::class),
            $em,
            $this->translator(),
        );

        $result = $importer->import([
            'collection' => ['code' => 'demo'],
            'items' => [['routeName' => 'home']],
        ], BreadcrumbImporter::STRATEGY_SKIP_EXISTING);

        self::assertSame(0, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(1, $result['skipped']);
    }

    public function testImportPersistsPathPatternAndMatchAttributes(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                if ($entity instanceof BreadcrumbItem) {
                    $persisted = $entity;
                }
            },
        );
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new BreadcrumbImporter(
            $colRepo,
            $this->createMock(BreadcrumbItemRepository::class),
            $em,
            $this->translator(),
        );

        $result = $importer->import([
            'collection' => ['code' => 'demo'],
            'items' => [[
                'routeName' => '*',
                'pathPattern' => '^/shop',
                'matchAttributes' => ['tenant' => 'a', 1 => 'skip', 'x' => ['bad']],
                'label' => 'Shop',
            ]],
        ]);

        self::assertSame(1, $result['created']);
        self::assertInstanceOf(BreadcrumbItem::class, $persisted);
        self::assertSame('^/shop', $persisted->getPathPattern());
        self::assertSame(['tenant' => 'a'], $persisted->getMatchAttributes());
    }

    public function testImportReplacesExistingCollectionAndRemovesOldItems(): void
    {
        $existing = new BreadcrumbCollection();
        $existing->setCode('demo');
        $existing->setContextKey('');

        $oldItem = new BreadcrumbItem();
        $oldItem->setRouteName('old');

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($existing);

        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->with($existing)->willReturn([$oldItem]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($oldItem);
        $em->expects(self::atLeastOnce())->method('flush');
        $em->expects(self::atLeastOnce())->method('persist')->with(self::isInstanceOf(BreadcrumbItem::class));

        $importer = new BreadcrumbImporter($colRepo, $itemRepo, $em, $this->translator());

        $result = $importer->import([
            'collection' => ['code' => 'demo', 'name' => 'Updated'],
            'items' => [['routeName' => 'new_home', 'label' => 'New']],
        ], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame(0, $result['created']);
        self::assertSame(1, $result['updated']);
        self::assertSame(0, $result['skipped']);
    }

    public function testImportCollectionsArrayReportsErrorsForInvalidEntries(): void
    {
        $importer = new BreadcrumbImporter(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->translator(),
        );

        $result = $importer->import([
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
        $importer = new BreadcrumbImporter(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->translator(),
        );

        $result = $importer->import(['unexpected' => true]);

        self::assertCount(1, $result['errors']);
    }

    public function testImportSkipsDuplicateCollectionSignatureInSamePayload(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->expects(self::once())
            ->method('findOneByCodeAndContextKey')
            ->with('dup', '')
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new BreadcrumbImporter(
            $colRepo,
            $this->createMock(BreadcrumbItemRepository::class),
            $em,
            $this->translator(),
        );

        $result = $importer->import([
            'collections' => [
                ['collection' => ['code' => 'dup'], 'items' => [['routeName' => 'a']]],
                ['collection' => ['code' => 'dup'], 'items' => [['routeName' => 'b']]],
            ],
        ]);

        self::assertSame(1, $result['created']);
    }

    public function testImportSkipsItemsWithEmptyRouteName(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new BreadcrumbImporter(
            $colRepo,
            $this->createMock(BreadcrumbItemRepository::class),
            $em,
            $this->translator(),
        );

        $result = $importer->import([
            'collection' => ['code' => 'demo'],
            'items' => [['routeName' => ''], ['label' => 'no route']],
        ]);

        self::assertSame(1, $result['created']);
        self::assertCount(1, $persisted);
        self::assertInstanceOf(BreadcrumbCollection::class, $persisted[0]);
    }

    public function testImportLogsStartAndSuccessWithCounts(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);
        $logger = $this->recordingLogger();

        $importer = new BreadcrumbImporter($colRepo, $this->createMock(BreadcrumbItemRepository::class), $this->createMock(EntityManagerInterface::class), $this->translator(), $logger);
        $importer->import(['collection' => ['code' => 'demo'], 'items' => []], BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame([
            ['info', 'Breadcrumb import started.', ['bundle' => 'NowoBreadcrumbKitBundle', 'action' => 'import', 'strategy' => 'replace']],
            ['info', 'Breadcrumb import finished.', ['bundle' => 'NowoBreadcrumbKitBundle', 'action' => 'import', 'strategy' => 'replace', 'created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => 0]],
        ], $logger->records);
    }

    public function testImportLogsWarningWhenThePayloadHasErrors(): void
    {
        $logger = $this->recordingLogger();
        $importer = new BreadcrumbImporter($this->createMock(BreadcrumbCollectionRepository::class), $this->createMock(BreadcrumbItemRepository::class), $this->createMock(EntityManagerInterface::class), $this->translator(), $logger);

        $importer->import(['unexpected' => true]);

        self::assertSame('warning', $logger->records[1][0]);
        self::assertSame('Breadcrumb import finished with errors.', $logger->records[1][1]);
        self::assertSame(1, $logger->records[1][2]['errors']);
    }

    public function testImportDropsUnsafeIconsAndReportsThem(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);

        /** @var list<object> $persisted */
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $importer = new BreadcrumbImporter($colRepo, $this->createMock(BreadcrumbItemRepository::class), $em, $this->translator());

        $result = $importer->import([
            'collection' => ['code' => 'demo', 'homeIcon' => '<img src=x onerror=alert(1)>'],
            'items' => [
                ['routeName' => 'home', 'icon' => '<i class="bi bi-house" onclick="alert(1)"></i>'],
                ['routeName' => 'blog', 'icon' => '<i class="bi bi-journal" style="x"></i>'],
            ],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame(['import.error.icon_rejected{"%code%":"demo","%count%":"2"}'], $result['errors']);

        self::assertInstanceOf(BreadcrumbCollection::class, $persisted[0]);
        self::assertNull($persisted[0]->getHomeIcon());
        self::assertInstanceOf(BreadcrumbItem::class, $persisted[1]);
        self::assertNull($persisted[1]->getIcon());
        self::assertInstanceOf(BreadcrumbItem::class, $persisted[2]);
        self::assertSame('<i class="bi bi-journal"></i>', $persisted[2]->getIcon());
    }

    public function testImportDropsInvalidPathPatternsAndReportsThem(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);

        /** @var list<object> $persisted */
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $importer = new BreadcrumbImporter($colRepo, $this->createMock(BreadcrumbItemRepository::class), $em, $this->translator());

        $result = $importer->import([
            'collection' => ['code' => 'demo'],
            'items' => [
                ['routeName' => '*', 'pathPattern' => '^/(a+)+$'],
                ['routeName' => 'shop', 'pathPattern' => '^/shop/.+'],
            ],
        ]);

        self::assertSame(['import.error.path_pattern_rejected{"%code%":"demo","%count%":"1"}'], $result['errors']);
        self::assertInstanceOf(BreadcrumbItem::class, $persisted[1]);
        self::assertNull($persisted[1]->getPathPattern());
        self::assertInstanceOf(BreadcrumbItem::class, $persisted[2]);
        self::assertSame('^/shop/.+', $persisted[2]->getPathPattern());
    }

    public function testImportLogsErrorAndRethrowsOnFailure(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willThrowException(new RuntimeException('db down'));
        $logger = $this->recordingLogger();
        $importer = new BreadcrumbImporter($colRepo, $this->createMock(BreadcrumbItemRepository::class), $this->createMock(EntityManagerInterface::class), $this->translator(), $logger);

        try {
            $importer->import(['collection' => ['code' => 'demo'], 'items' => []]);
            self::fail('Expected the repository failure to propagate.');
        } catch (RuntimeException $e) {
            self::assertSame('db down', $e->getMessage());
        }

        self::assertSame('error', $logger->records[1][0]);
        self::assertSame('Breadcrumb import failed.', $logger->records[1][1]);
        self::assertSame(RuntimeException::class, $logger->records[1][2]['exception']);
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
