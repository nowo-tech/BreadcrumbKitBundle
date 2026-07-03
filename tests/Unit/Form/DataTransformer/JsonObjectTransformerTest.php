<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Form\DataTransformer;

use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonObjectTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class JsonObjectTransformerTest extends TestCase
{
    private JsonObjectTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new JsonObjectTransformer();
    }

    public function testTransformReturnsEmptyStringForEmptyArray(): void
    {
        self::assertSame('', $this->transformer->transform([]));
        self::assertSame('', $this->transformer->transform(null));
    }

    public function testTransformEncodesArrayAsPrettyJson(): void
    {
        $json = $this->transformer->transform(['id' => 1, 'name' => 'test']);

        self::assertStringContainsString('"id": 1', $json);
        self::assertStringContainsString('"name": "test"', $json);
    }

    public function testReverseTransformReturnsEmptyArrayForBlankString(): void
    {
        self::assertSame([], $this->transformer->reverseTransform(''));
        self::assertSame([], $this->transformer->reverseTransform('   '));
    }

    public function testReverseTransformReturnsEmptyArrayForNonString(): void
    {
        self::assertSame([], $this->transformer->reverseTransform(123));
    }

    public function testReverseTransformDecodesValidJsonObject(): void
    {
        $result = $this->transformer->reverseTransform('{"foo":"bar","n":1}');

        self::assertSame(['foo' => 'bar', 'n' => 1], $result);
    }

    public function testReverseTransformThrowsForInvalidJson(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('JSON inválido');

        $this->transformer->reverseTransform('{invalid');
    }

    public function testReverseTransformAcceptsJsonListAsArray(): void
    {
        $result = $this->transformer->reverseTransform('[1,2,3]');

        self::assertSame([1, 2, 3], $result);
    }
}
