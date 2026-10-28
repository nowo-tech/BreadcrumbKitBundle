<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Security;

use Nowo\BreadcrumbKitBundle\Form\BreadcrumbPathPatternConstraint;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbPathPatternValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class BreadcrumbPathPatternValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function patterns(): iterable
    {
        yield 'empty' => [null, true];
        yield 'blank' => ['  ', true];
        yield 'prefix' => ['^/shop/.+', true];
        yield 'alternation' => ['^/(en|es)/blog/[\w-]+$', true];
        yield 'quantified group' => ['^/(docs/)+', true];
        yield 'escaped paren' => ['^/a\(b+\)+', true];
        yield 'does not compile' => ['^/shop/(', false];
        yield 'delimiter breaks it' => ['^/a#(', false];
        yield 'nested plus' => ['^/(a+)+$', false];
        yield 'nested star' => ['^(\w*)*$', false];
        yield 'nested counted' => ['^/(x{1,5}){2,}', false];
    }

    #[DataProvider('patterns')]
    public function testPatterns(?string $pattern, bool $allowed): void
    {
        self::assertSame($allowed, BreadcrumbPathPatternValidator::isAllowed($pattern));
        self::assertCount($allowed ? 0 : 1, Validation::createValidator()->validate($pattern, BreadcrumbPathPatternConstraint::create()));
    }
}
