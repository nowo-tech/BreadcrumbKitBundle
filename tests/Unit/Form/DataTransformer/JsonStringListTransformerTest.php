<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Form\DataTransformer;

use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonStringListTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class JsonStringListTransformerTest extends TestCase
{
    private JsonStringListTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new JsonStringListTransformer();
    }

    public function testTransformReturnsEmptyStringForEmptyOrInvalidValue(): void
    {
        self::assertSame('', $this->transformer->transform([]));
        self::assertSame('', $this->transformer->transform(null));
    }

    public function testTransformFiltersNonStringsAndEncodesList(): void
    {
        $json = $this->transformer->transform(['id', '', 5, 'slug']);

        self::assertStringContainsString('"id"', $json);
        self::assertStringContainsString('"slug"', $json);
        self::assertStringNotContainsString('5', $json);
    }

    public function testReverseTransformReturnsNullForBlankString(): void
    {
        self::assertNull($this->transformer->reverseTransform(''));
        self::assertNull($this->transformer->reverseTransform('   '));
    }

    public function testReverseTransformReturnsNullForNonString(): void
    {
        self::assertNull($this->transformer->reverseTransform(['id']));
    }

    public function testReverseTransformDecodesStringList(): void
    {
        $result = $this->transformer->reverseTransform('["id","slug"]');

        self::assertSame(['id', 'slug'], $result);
    }

    public function testReverseTransformReturnsNullWhenOnlyEmptyStrings(): void
    {
        self::assertNull($this->transformer->reverseTransform('["",""]'));
    }

    public function testReverseTransformThrowsForInvalidJson(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('JSON inválido');

        $this->transformer->reverseTransform('not-json');
    }

    public function testReverseTransformExtractsStringValuesFromJsonObject(): void
    {
        $result = $this->transformer->reverseTransform('{"id":"slug"}');

        self::assertSame(['slug'], $result);
    }
}
