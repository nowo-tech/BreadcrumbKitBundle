<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Dto;

use InvalidArgumentException;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbCollapse;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BreadcrumbCollapseTest extends TestCase
{
    public function testDefaultsAreDisabled(): void
    {
        $collapse = new BreadcrumbCollapse();

        self::assertFalse($collapse->enabled);
        self::assertSame(3, $collapse->minItems);
        self::assertSame('40rem', $collapse->breakpoint);
        self::assertFalse($collapse->appliesTo(10));
    }

    public function testAppliesFromMinItems(): void
    {
        $collapse = new BreadcrumbCollapse(true, 4, '48rem');

        self::assertFalse($collapse->appliesTo(3));
        self::assertTrue($collapse->appliesTo(4));
        self::assertTrue($collapse->appliesTo(9));
    }

    public function testRejectsMinItemsBelowFloor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('min_items must be >= 2');

        new BreadcrumbCollapse(true, 1);
    }

    public function testRejectsUnsafeBreakpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BreadcrumbCollapse(true, 3, '40rem) { body { display: none }');
    }

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function breakpoints(): iterable
    {
        yield 'rem' => ['40rem', '40rem'];
        yield 'decimal em' => ['37.5em', '37.5em'];
        yield 'px' => ['640px', '640px'];
        yield 'trim + lowercase' => [' 48REM ', '48rem'];
        yield 'integer' => [768, '768px'];
        yield 'numeric string' => ['600', '600px'];
        yield 'zero' => [0, '0px'];
        yield 'percent' => ['50%', null];
        yield 'vw' => ['60vw', null];
        yield 'negative' => ['-40rem', null];
        yield 'injection' => ['40rem"]{}', null];
        yield 'empty' => ['', null];
        yield 'float' => [40.5, null];
        yield 'null' => [null, null];
        yield 'array' => [['40rem'], null];
    }

    #[DataProvider('breakpoints')]
    public function testNormalizeBreakpoint(mixed $value, ?string $expected): void
    {
        self::assertSame($expected, BreadcrumbCollapse::normalizeBreakpoint($value));
    }

    public function testBooleanOverrideTogglesAndKeepsGlobalValues(): void
    {
        $global = new BreadcrumbCollapse(false, 5, '50rem');

        $on = $global->withOverride(true);
        self::assertTrue($on->enabled);
        self::assertSame(5, $on->minItems);
        self::assertSame('50rem', $on->breakpoint);

        self::assertFalse((new BreadcrumbCollapse(true))->withOverride(false)->enabled);
    }

    public function testObjectOverrideMergesKeyByKey(): void
    {
        $global = new BreadcrumbCollapse(true, 3, '40rem');

        $override = $global->withOverride(['min_items' => 6, 'breakpoint' => 900]);
        self::assertTrue($override->enabled, 'enabled is inherited when the object omits it');
        self::assertSame(6, $override->minItems);
        self::assertSame('900px', $override->breakpoint);

        $disabled = $global->withOverride(['enabled' => false]);
        self::assertFalse($disabled->enabled);
        self::assertSame(3, $disabled->minItems);
    }

    public function testInvalidOverrideValuesAreIgnored(): void
    {
        $global = new BreadcrumbCollapse(false, 3, '40rem');

        $override = $global->withOverride(['enabled' => 'yes', 'min_items' => 1, 'breakpoint' => '50%']);
        self::assertEquals($global, $override);

        self::assertSame($global, $global->withOverride(null));
        self::assertSame($global, $global->withOverride('on'));
        self::assertEquals(new BreadcrumbCollapse(false, 3, '40rem'), $global->withOverride(['min_items' => '7']));
    }

    public function testTrailViewIsCollapsibleOnlyWithEnoughCrumbs(): void
    {
        $nodes = [
            new BreadcrumbNode('Home', '/', true, false),
            new BreadcrumbNode('Shop', '/shop', true, false),
            new BreadcrumbNode('Product', null, false, true),
        ];

        self::assertFalse((new BreadcrumbTrailView($nodes))->isCollapsible());
        self::assertTrue((new BreadcrumbTrailView($nodes, collapse: new BreadcrumbCollapse(true)))->isCollapsible());
        self::assertFalse((new BreadcrumbTrailView($nodes, collapse: new BreadcrumbCollapse(true, 4)))->isCollapsible());
    }
}
