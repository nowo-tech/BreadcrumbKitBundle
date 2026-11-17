<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Form;

use Nowo\BreadcrumbKitBundle\Form\BreadcrumbIconConstraint;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbPathPatternConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The constraint callbacks report their violations in the default `validators` domain; the messages must therefore be
 * translated from `validators.<locale>.yaml` (never rendered as raw keys).
 */
final class ConstraintMessagesTest extends TestCase
{
    private const LOCALES = ['de', 'en', 'es', 'fr', 'it', 'nl', 'pt'];

    /**
     * @return iterable<string, array{Constraint, string, string}>
     */
    public static function constraints(): iterable
    {
        yield 'icon' => [BreadcrumbIconConstraint::create(), '<img src=x onerror=alert(1)>', 'Icon not allowed'];
        yield 'path pattern' => [BreadcrumbPathPatternConstraint::create(), '^/(a+)+$', 'Invalid path pattern'];
    }

    #[DataProvider('constraints')]
    public function testEnglishTextIsRendered(Constraint $constraint, string $value, string $prefix): void
    {
        $violations = $this->validator('en')->validate($value, $constraint);

        self::assertCount(1, $violations);
        self::assertTrue(str_starts_with((string) $violations->get(0)->getMessage(), $prefix));
    }

    #[DataProvider('constraints')]
    public function testEveryShippedLocaleRendersATranslationNotTheKey(Constraint $constraint, string $value): void
    {
        foreach (self::LOCALES as $locale) {
            $message = (string) $this->validator($locale)->validate($value, $constraint)->get(0)->getMessage();

            self::assertStringNotContainsString('nowo_breadcrumb_kit.', $message, $locale);
            self::assertStringNotContainsString('form.', $message, $locale);
        }
    }

    private function validator(string $locale): ValidatorInterface
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', \dirname(__DIR__, 3).'/src/Resources/translations/validators.'.$locale.'.yaml', $locale, 'validators');

        return Validation::createValidatorBuilder()->setTranslator($translator)->setTranslationDomain('validators')->getValidator();
    }
}
