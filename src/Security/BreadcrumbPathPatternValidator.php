<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Security;

/**
 * Checks editor-supplied item `pathPattern` values (PCRE body, compiled as `#…#u` on every request that reaches the
 * matcher) before they are stored.
 *
 * Rejected: patterns that do not compile (they never match and only add a warning per request) and nested
 * quantifiers such as `(a+)+` or `(\w*)*`, the usual catastrophic-backtracking shapes that burn CPU up to PCRE's
 * backtrack limit on every request.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BreadcrumbPathPatternValidator
{
    /** A group whose last token is quantified, itself followed by a quantifier: `(…+)+`, `(…*){2,}`, `(…})*`. */
    private const NESTED_QUANTIFIER = '/\((?:[^()\\\\]|\\\\.)*[+*}]\)[+*{]/';

    public static function isAllowed(?string $pattern): bool
    {
        if (null === $pattern || '' === trim($pattern)) {
            return true;
        }

        if (false === @preg_match('#'.$pattern.'#u', '')) {
            return false;
        }

        return 1 !== preg_match(self::NESTED_QUANTIFIER, $pattern);
    }
}
