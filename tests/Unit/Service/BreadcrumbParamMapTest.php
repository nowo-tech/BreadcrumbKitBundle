<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbParamMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

final class BreadcrumbParamMapTest extends TestCase
{
    public function testNullAndPlainKeysAreLeftAsTheyAre(): void
    {
        self::assertSame([null, []], BreadcrumbParamMap::split(null));
        self::assertSame([[], []], BreadcrumbParamMap::split([]));
        self::assertSame([['id', 'slug'], []], BreadcrumbParamMap::split(['id', 'slug']));
    }

    public function testMappingEntriesAreSeparatedFromPlainKeys(): void
    {
        self::assertSame(
            [['id'], ['id' => ['@projectId', '@spaceSlug|before:--'], 'tab' => ['@section']]],
            BreadcrumbParamMap::split(['id', 'id=@projectId,@spaceSlug|before:--', 'tab=@section', 'tab=@ignored']),
        );
    }

    /**
     * Anything else stays a plain name: inert, since no route parameter has such a name (the behaviour before 2.14).
     *
     * @return iterable<string, array{string}>
     */
    public static function notMappings(): iterable
    {
        yield 'no source' => ['id='];
        yield 'literal value' => ['id=5'];
        yield 'no at sign in the chain' => ['id=@a,b'];
        yield 'reserved source' => ['id=@_controller'];
        yield 'reserved target' => ['_fragment=@a'];
        yield 'target with a space' => ['a b=@c'];
        yield 'unknown modifier' => ['id=@a|after:-'];
        yield 'empty separator' => ['id=@a|before:'];
        yield 'separator too long' => ['id=@a|before:'.str_repeat('-', 17)];
        yield 'trailing newline' => ["id=@a\n"];
        yield 'more than 16 sources' => ['id='.implode(',', array_fill(0, 17, '@a'))];
    }

    #[DataProvider('notMappings')]
    public function testAnythingElseIsAPlainName(string $entry): void
    {
        self::assertSame([[$entry], []], BreadcrumbParamMap::split([$entry]));
    }

    public function testEntriesThatAreNotStringsAreSkipped(): void
    {
        self::assertSame([['id'], ['a' => ['@b']]], BreadcrumbParamMap::split([1, 'id', null, ['x'], 'a=@b']));
    }

    public function testSixteenSourcesAreAccepted(): void
    {
        $sources = array_fill(0, 16, '@a');

        self::assertSame([[], ['id' => $sources]], BreadcrumbParamMap::split(['id='.implode(',', $sources)]));
        self::assertNull(BreadcrumbParamMap::encode('id', [...$sources, '@a']));
    }

    public function testFirstSourceWithAValueWins(): void
    {
        $request = new Request(attributes: ['_route_params' => ['empty' => '', 'tenantSlug' => 'tenant', 'projectId' => 'project', 'number' => 7]]);
        $map = ['id' => ['@missing', '@empty', '@tenantSlug', '@projectId'], 'count' => ['@number'], 'gone' => ['@missing']];

        self::assertSame(['id' => 'tenant', 'count' => 7], BreadcrumbParamMap::resolve($map, $request));
    }

    public function testRequestAttributesAreNotReadUnlessDeclared(): void
    {
        $request = new Request(attributes: ['csp_nonce' => 'secret', 'projectId' => 'attribute', '_route_params' => []]);
        $map = ['n' => ['@csp_nonce'], 'id' => ['@projectId']];

        self::assertSame([], BreadcrumbParamMap::resolve($map, $request));
        self::assertSame(['id' => 'attribute'], BreadcrumbParamMap::resolve($map, $request, ['projectId']));
    }

    public function testARouteParameterWinsOverADeclaredAttribute(): void
    {
        $request = new Request(attributes: ['slug' => 'attribute', '_route_params' => ['slug' => 'route']]);

        self::assertSame(['s' => 'route'], BreadcrumbParamMap::resolve(['s' => ['@slug']], $request, ['slug']));
    }

    public function testValuesThatCannotBeARouteParameterAreSkipped(): void
    {
        $request = new Request(attributes: ['_route_params' => ['object' => new stdClass(), 'list' => ['a'], 'flag' => true, 'null' => null, 'ok' => 'yes']]);

        self::assertSame(['id' => 'yes'], BreadcrumbParamMap::resolve(['id' => ['@object', '@list', '@flag', '@null', '@ok']], $request));
    }

    public function testBeforeKeepsWhatPrecedesTheSeparator(): void
    {
        $request = new Request(attributes: ['_route_params' => ['named' => 'uuid--Team wiki', 'bare' => 'uuid', 'lead' => '--name', 'number' => 7, 'fallback' => 'fallback']]);

        self::assertSame(
            ['a' => 'uuid', 'b' => 'uuid', 'c' => 'fallback', 'd' => 7],
            BreadcrumbParamMap::resolve([
                'a' => ['@named|before:--'],
                'b' => ['@bare|before:--'],
                'c' => ['@lead|before:--', '@fallback'],
                'd' => ['@number|before:--'],
            ], $request),
        );
    }

    public function testIsName(): void
    {
        self::assertTrue(BreadcrumbParamMap::isName('projectId'));
        self::assertFalse(BreadcrumbParamMap::isName('_route'));
        self::assertFalse(BreadcrumbParamMap::isName("a\n"));
        self::assertFalse(BreadcrumbParamMap::isName(5));
    }

    public function testEncodeAndExportAreReverses(): void
    {
        self::assertSame('id=@projectId', BreadcrumbParamMap::encode('id', '@projectId'));
        self::assertSame('id=@a,@b|before:--', BreadcrumbParamMap::encode('id', ['@a', '@b|before:--']));

        [, $map] = BreadcrumbParamMap::split(['id=@projectId', 'tab=@a,@b|before:--']);
        self::assertSame(['id' => '@projectId', 'tab' => ['@a', '@b|before:--']], BreadcrumbParamMap::export($map));
    }

    /**
     * @return iterable<string, array{int|string, mixed}>
     */
    public static function invalidPairs(): iterable
    {
        yield 'numeric target' => [0, '@a'];
        yield 'reserved target' => ['_fragment', '@a'];
        yield 'literal' => ['id', '5'];
        yield 'not a source' => ['id', 5];
        yield 'empty list' => ['id', []];
        yield 'non-string in the list' => ['id', ['@a', 5]];
        yield 'reserved source' => ['id', '@_route'];
    }

    #[DataProvider('invalidPairs')]
    public function testEncodeRefusesInvalidPairs(int|string $target, mixed $sources): void
    {
        self::assertNull(BreadcrumbParamMap::encode($target, $sources));
    }
}
