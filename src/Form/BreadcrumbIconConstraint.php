<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form;

use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbIconSanitizer;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Validation for the editor-supplied icon fields (item `icon`, collection `homeIcon`): the value must pass
 * {@see BreadcrumbIconSanitizer} (text/emoji, inline SVG or simple inline HTML without script).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BreadcrumbIconConstraint
{
    public const MESSAGE = 'form.icon.invalid';

    public static function create(): Callback
    {
        return new Callback(static function (mixed $value, ExecutionContextInterface $context): void {
            if (\is_string($value) && !BreadcrumbIconSanitizer::isAllowed($value)) {
                $context->buildViolation(self::MESSAGE)
                    ->setTranslationDomain(NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN)
                    ->addViolation();
            }
        });
    }
}
