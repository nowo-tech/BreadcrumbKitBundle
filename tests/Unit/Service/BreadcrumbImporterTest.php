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
