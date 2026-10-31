<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Testing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Testing\BreadcrumbRouteCoverageTrait;
use Nowo\BreadcrumbKitBundle\Testing\BreadcrumbRows;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The coverage trait with its defaults: rows read from the bundle tables, the default non-page filter.
 */
final class BreadcrumbRouteCoverageDatabaseTest extends KernelTestCase
{
    use BreadcrumbRouteCoverageTrait;

    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return CoverageTestKernel::class;
    }

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(BreadcrumbCollection::class),
            $entityManager->getClassMetadata(BreadcrumbItem::class),
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(CoverageTestKernel::baseDir());
        parent::tearDownAfterClass();
    }

    public function testPassesWithRowsFromTheDatabase(): void
    {
        $this->seed(['app_home' => null, 'app_items' => 'app_home', 'app_item_show' => 'app_items', 'app_items_fragment' => 'app_items']);

        $this->assertEveryPageRouteHasBreadcrumb();
    }

    public function testFailsWhenAPageHasNoDatabaseRow(): void
    {
        $this->seed(['app_home' => null, 'app_items' => 'app_home', 'app_item_show' => 'app_items']);

        try {
            $this->assertEveryPageRouteHasBreadcrumb();
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('Add them to the breadcrumb tables', $e->getMessage());
            self::assertStringContainsString('  - app_items_fragment ANY /items/table-fragment', $e->getMessage());

            return;
        }
        self::fail('A page without a row must fail the coverage assertion.');
    }

    public function testRowsCanBeLimitedToOneCollectionAndKeepTheFirstRowOfARoute(): void
    {
        $this->seed(['app_home' => null, 'app_items' => 'app_home']);
        $this->seed(['app_items' => null, '' => null], 'admin');

        self::assertSame(['app_items' => null], BreadcrumbRows::fromEntityManager($this->entityManager, 'admin'));
        self::assertSame(['app_home' => null, 'app_items' => 'app_home'], BreadcrumbRows::fromEntityManager($this->entityManager));
    }

    /**
     * @param array<string, string|null> $rows route => parent route (parents listed first)
     */
    private function seed(array $rows, string $code = 'main'): void
    {
        $collection = (new BreadcrumbCollection())->setCode($code);
        $this->entityManager->persist($collection);
        $items = [];
        foreach ($rows as $route => $parent) {
            $item = (new BreadcrumbItem())->setCollection($collection)->setRouteName((string) $route)->setLabel((string) $route);
            if (null !== $parent) {
                $item->setParent($items[$parent]);
            }
            $this->entityManager->persist($item);
            $items[$route] = $item;
        }
        $this->entityManager->flush();
    }
}
