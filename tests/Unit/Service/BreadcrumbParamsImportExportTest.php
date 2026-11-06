<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbExporter;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * BK-2: `params` in the collection JSON, stored inside dynamicParamKeys (no new column).
 */
final class BreadcrumbParamsImportExportTest extends TestCase
{
    public function testParamsAreStoredWithTheDynamicKeys(): void
    {
        [$items, $result] = $this->import([
            'routeName' => 'project_show',
            'dynamicParamKeys' => ['id'],
            'params' => ['id' => ['@projectId', '@spaceSlug|before:--'], 'tab' => '@section'],
        ]);

        self::assertSame([], $result['errors']);
        self::assertSame(['id', 'id=@projectId,@spaceSlug|before:--', 'tab=@section'], $items[0]->getDynamicParamKeys());
    }

    public function testParamsAloneAreEnough(): void
    {
        [$items] = $this->import(['routeName' => 'a', 'params' => ['id' => '@projectId']]);

        self::assertSame(['id=@projectId'], $items[0]->getDynamicParamKeys());
    }

    public function testItemsWithoutParamsAreStoredAsBefore(): void
    {
        [$items, $result] = $this->import(['routeName' => 'a'], ['routeName' => 'b', 'dynamicParamKeys' => ['id', '', 5]], ['routeName' => 'c', 'dynamicParamKeys' => 'id']);

        self::assertSame([], $result['errors']);
        self::assertNull($items[0]->getDynamicParamKeys());
        self::assertSame(['id'], $items[1]->getDynamicParamKeys());
        self::assertNull($items[2]->getDynamicParamKeys());
    }

    public function testInvalidPairsAreDroppedAndCounted(): void
    {
        [$items, $result] = $this->import(
            ['routeName' => 'a', 'params' => ['id' => '@ok', 'source' => '@_controller', 'literal' => '5', '_fragment' => '@x']],
            ['routeName' => 'b', 'params' => 'id'],
        );

        self::assertSame(['id=@ok'], $items[0]->getDynamicParamKeys());
        self::assertNull($items[1]->getDynamicParamKeys());
        self::assertSame(['import.error.params_rejected{"%code%":"main","%count%":"4"}'], $result['errors']);
    }

    public function testExportWritesParamsAndImportReadsThemBack(): void
    {
        $collection = (new BreadcrumbCollection())->setCode('main');
        $item = (new BreadcrumbItem())->setCollection($collection)->setRouteName('project_show')
            ->setDynamicParamKeys(['id', 'id=@projectId,@spaceSlug|before:--', 'tab=@section']);

        $exported = $this->exporter([$item])->exportCollection($collection)['items'][0];

        self::assertSame(['id'], $exported['dynamicParamKeys']);
        self::assertSame(['id' => ['@projectId', '@spaceSlug|before:--'], 'tab' => '@section'], $exported['params']);

        [$items] = $this->import($exported);
        self::assertSame($item->getDynamicParamKeys(), $items[0]->getDynamicParamKeys());
    }

    public function testExportOfAnItemWithoutMappingsIsUnchanged(): void
    {
        $collection = (new BreadcrumbCollection())->setCode('main');
        $plain = (new BreadcrumbItem())->setCollection($collection)->setRouteName('a')->setDynamicParamKeys(['id']);
        $none = (new BreadcrumbItem())->setCollection($collection)->setRouteName('b');
        $inert = (new BreadcrumbItem())->setCollection($collection)->setRouteName('c')->setDynamicParamKeys(['id=5']);

        $items = $this->exporter([$plain, $none, $inert])->exportCollection($collection)['items'];

        self::assertSame(['routeName' => 'a', 'dynamicParamKeys' => ['id'], 'linkEnabled' => true], $items[0]);
        self::assertSame(['routeName' => 'b', 'linkEnabled' => true], $items[1]);
        self::assertSame(['routeName' => 'c', 'dynamicParamKeys' => ['id=5'], 'linkEnabled' => true], $items[2]);
    }

    /**
     * @param list<BreadcrumbItem> $items
     */
    private function exporter(array $items): BreadcrumbExporter
    {
        foreach ($items as $i => $item) {
            (new ReflectionProperty(BreadcrumbItem::class, 'id'))->setValue($item, $i + 1);
        }
        $repository = $this->createMock(BreadcrumbItemRepository::class);
        $repository->method('findAllForCollection')->willReturn($items);

        return new BreadcrumbExporter($this->createMock(BreadcrumbCollectionRepository::class), $repository);
    }

    /**
     * @param array<string, mixed> ...$rows
     *
     * @return array{list<BreadcrumbItem>, array{created: int, updated: int, skipped: int, errors: list<string>}}
     */
    private function import(array ...$rows): array
    {
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            if ($entity instanceof BreadcrumbItem) {
                $persisted[] = $entity;
            }
        });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $params = []): string => $id.json_encode($params));
        $collections = $this->createMock(BreadcrumbCollectionRepository::class);
        $collections->method('findOneByCodeAndContextKey')->willReturn(null);

        $importer = new BreadcrumbImporter($collections, $this->createMock(BreadcrumbItemRepository::class), $em, $translator);
        $result = $importer->import(['collection' => ['code' => 'main'], 'items' => array_values($rows)]);

        return [$persisted, $result];
    }
}
