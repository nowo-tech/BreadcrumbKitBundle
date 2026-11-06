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
    }

    #[DataProvider('notMappings')]
    public function testAnythingElseIsAPlainName(string $entry): void
    {
        self::assertSame([[$entry], []], BreadcrumbParamMap::split([$entry]));
    }

    public function testFirstSourceWithAValueWins(): void
    {
        $request = new Request(attributes: ['empty' => '', 'tenantSlug' => 'tenant', 'projectId' => 'project', 'number' => 7]);
        $map = ['id' => ['@missing', '@empty', '@tenantSlug', '@projectId'], 'count' => ['@number'], 'gone' => ['@missing']];

        self::assertSame(['id' => 'tenant', 'count' => 7], BreadcrumbParamMap::resolve($map, $request));
    }

    public function testSourceIsReadFromRouteParamsWhenTheAttributeIsNotSet(): void
    {
        $request = new Request(attributes: ['_route_params' => ['projectId' => 'p1']]);

        self::assertSame(['id' => 'p1'], BreadcrumbParamMap::resolve(['id' => ['@projectId']], $request));
    }

    public function testValuesThatCannotBeARouteParameterAreSkipped(): void
    {
        $request = new Request(attributes: ['object' => new stdClass(), 'list' => ['a'], 'flag' => true, 'null' => null, 'ok' => 'yes']);

        self::assertSame(['id' => 'yes'], BreadcrumbParamMap::resolve(['id' => ['@object', '@list', '@flag', '@null', '@ok']], $request));
    }

    public function testBeforeKeepsWhatPrecedesTheSeparator(): void
    {
        $request = new Request(attributes: ['named' => 'uuid--Team wiki', 'bare' => 'uuid', 'lead' => '--name', 'number' => 7]);

        self::assertSame(
            ['a' => 'uuid', 'b' => 'uuid', 'c' => 'fallback', 'd' => 7],
            BreadcrumbParamMap::resolve([
                'a' => ['@named|before:--'],
                'b' => ['@bare|before:--'],
                'c' => ['@lead|before:--', '@fallback'],
                'd' => ['@number|before:--'],
            ], $request->duplicate(attributes: $request->attributes->all() + ['fallback' => 'fallback'])),
        );
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
