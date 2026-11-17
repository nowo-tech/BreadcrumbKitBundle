<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Test double: every CSRF token is valid, except the value `rejected` (the HTTP tests post forms without a session).
 */
final class AcceptingCsrfTokenManager implements CsrfTokenManagerInterface
{
    public const REJECTED = 'rejected';

    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, 'accepted');
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, 'accepted');
    }

    public function removeToken(string $tokenId): ?string
    {
        return null;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        return self::REJECTED !== $token->getValue();
    }
}
