<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Form;

use Nowo\BreadcrumbKitBundle\Form\BreadcrumbIconConstraint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class BreadcrumbIconConstraintTest extends TestCase
{
    public function testAcceptsSafeIconsAndEmptyValues(): void
    {
        $validator = Validation::createValidator();

        foreach ([null, '', '⌂', '<i class="bi bi-house"></i>'] as $value) {
            self::assertCount(0, $validator->validate($value, BreadcrumbIconConstraint::create()));
        }
    }

    public function testRejectsUnsafeMarkup(): void
    {
        $violations = Validation::createValidator()->validate('<img src=x onerror=alert(1)>', BreadcrumbIconConstraint::create());

        self::assertCount(1, $violations);
        self::assertSame(BreadcrumbIconConstraint::MESSAGE, $violations->get(0)->getMessageTemplate());
    }
}
