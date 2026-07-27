<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Security;

use Nowo\BreadcrumbKitBundle\Security\ConfigurableBreadcrumbKitAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ConfigurableBreadcrumbKitAccessCheckerTest extends TestCase
{
    public function testAllowsAccessWhenNoRolesConfigured(): void
    {
        $checker = new ConfigurableBreadcrumbKitAccessChecker(
            $this->createMock(AuthorizationCheckerInterface::class),
            [],
        );

        self::assertTrue($checker->canAccess(new \stdClass()));
    }

    public function testAllowsAccessWhenUserHasConfiguredRole(): void
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturnMap([
            ['ROLE_USER', false],
            ['ROLE_ADMIN', true],
        ]);

        $checker = new ConfigurableBreadcrumbKitAccessChecker($authorization, ['ROLE_USER', 'ROLE_ADMIN']);

        self::assertTrue($checker->canAccess(new \stdClass()));
    }

    public function testDeniesAccessWhenNoRoleMatches(): void
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn(false);

        $checker = new ConfigurableBreadcrumbKitAccessChecker($authorization, ['ROLE_ADMIN']);

        self::assertFalse($checker->canAccess(new \stdClass()));
    }
}
