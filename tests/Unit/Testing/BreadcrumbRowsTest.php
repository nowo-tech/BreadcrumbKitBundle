<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Testing;

use InvalidArgumentException;
use Nowo\BreadcrumbKitBundle\Testing\BreadcrumbRows;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class BreadcrumbRowsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/nowo-breadcrumb-kit-rows-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testExportShapeWithNestedChildren(): void
    {
        $rows = BreadcrumbRows::fromArray([
            'collection' => ['code' => 'main'],
            'items' => [
                ['routeName' => 'home', 'children' => [
                    ['routeName' => 'items', 'children' => [['routeName' => 'item_show']]],
                    // A row without a route keeps its children under the nearest named ancestor.
                    ['label' => 'Group', 'children' => [['routeName' => 'reports']]],
                ]],
                'not an item',
            ],
        ]);

        self::assertSame(['home' => null, 'items' => 'home', 'item_show' => 'items', 'reports' => 'home'], $rows);
    }

    public function testSeveralCollectionsAndListsOfBlocks(): void
    {
        $blocks = [
            ['collection' => ['code' => 'a'], 'items' => [['routeName' => 'home'], ['routeName' => 'dup', 'pathPattern' => '^/x']]],
            ['collection' => ['code' => 'b'], 'items' => [['routeName' => 'admin'], ['routeName' => 'dup', 'parent' => 'admin']]],
        ];

        self::assertSame(['home' => null, 'dup' => null, 'admin' => null], BreadcrumbRows::fromArray(['collections' => $blocks]));
        self::assertSame(['home' => null, 'dup' => null, 'admin' => null], BreadcrumbRows::fromArray($blocks));
    }

    public function testFlatRowsNamingTheirParent(): void
    {
        self::assertSame(
            ['home' => null, 'items' => 'home', 'item_show' => 'items'],
            BreadcrumbRows::fromArray([
                ['route' => ' home ', 'parent' => null],
                ['route' => 'items', 'parent' => 'home'],
                ['routeName' => 'item_show', 'parent' => 'items'],
                ['route' => '', 'parent' => 'home'],
            ]),
        );
        self::assertSame(['home' => null], BreadcrumbRows::fromArray(['items' => [['route' => 'home', 'parent' => '']]]));
    }

    public function testJsonAndYamlFiles(): void
    {
        file_put_contents($this->dir.'/rows.json', '{"items":[{"route":"home"},{"route":"items","parent":"home"}]}');
        file_put_contents($this->dir.'/rows.yml', "- { route: home }\n- { route: items, parent: home }\n");

        self::assertSame(['home' => null, 'items' => 'home'], BreadcrumbRows::fromFile($this->dir.'/rows.json'));
        self::assertSame(['home' => null, 'items' => 'home'], BreadcrumbRows::fromFile($this->dir.'/rows.yml'));
    }

    public function testMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a readable file');

        BreadcrumbRows::fromFile($this->dir.'/missing.json');
    }

    public function testInvalidJson(): void
    {
        file_put_contents($this->dir.'/bad.json', '{');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be parsed');

        BreadcrumbRows::fromFile($this->dir.'/bad.json');
    }

    public function testInvalidYaml(): void
    {
        file_put_contents($this->dir.'/bad.yaml', "items: [\n");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be parsed');

        BreadcrumbRows::fromFile($this->dir.'/bad.yaml');
    }

    public function testScalarRoot(): void
    {
        file_put_contents($this->dir.'/scalar.json', '"rows"');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must hold an object or a list');

        BreadcrumbRows::fromFile($this->dir.'/scalar.json');
    }
}
