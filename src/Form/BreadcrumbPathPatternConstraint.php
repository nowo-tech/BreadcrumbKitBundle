<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form;

use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbPathPatternValidator;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Validation for the item `pathPattern` field: the value must compile as `#…#u` and avoid nested quantifiers
 * ({@see BreadcrumbPathPatternValidator}).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BreadcrumbPathPatternConstraint
{
    public const MESSAGE = 'form.breadcrumb_item.path_pattern.invalid';

    public static function create(): Callback
    {
        return new Callback(static function (mixed $value, ExecutionContextInterface $context): void {
            if (\is_string($value) && !BreadcrumbPathPatternValidator::isAllowed($value)) {
                $context->buildViolation(self::MESSAGE)
                    ->setTranslationDomain(NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN)
                    ->addViolation();
            }
        });
    }
}
