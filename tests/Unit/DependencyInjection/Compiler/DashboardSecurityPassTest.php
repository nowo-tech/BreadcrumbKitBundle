<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\DashboardSecurityPass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Configuration;
use Nowo\BreadcrumbKitBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbKitAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Security\ConfigurableBreadcrumbKitAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class DashboardSecurityPassTest extends TestCase
{
    public function testNoOpWhenDashboardDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS.'.dashboard.enabled', false);
        $container->setParameter(Configuration::ALIAS.'.security.allow_unauthenticated', false);
        $container->setParameter(Configuration::ALIAS.'.security.access_roles', ['ROLE_ADMIN']);
        $container->setParameter(Configuration::ALIAS.'.security.custom_access_checker', false);

        (new DashboardSecurityPass())->process($container);

        self::assertFalse($container->hasDefinition(DashboardAccessSubscriber::class));
    }

    public function testFailsWithoutSecurityWhenNotAllowUnauthenticated(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS.'.dashboard.enabled', true);
        $container->setParameter(Configuration::ALIAS.'.security.allow_unauthenticated', false);
        $container->setParameter(Configuration::ALIAS.'.security.access_roles', ['ROLE_ADMIN']);
        $container->setParameter(Configuration::ALIAS.'.security.custom_access_checker', false);

        $this->expectException(InvalidConfigurationException::class);
        (new DashboardSecurityPass())->process($container);
    }

    public function testSkipsWhenAllowUnauthenticated(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS.'.dashboard.enabled', true);
        $container->setParameter(Configuration::ALIAS.'.security.allow_unauthenticated', true);
        $container->setParameter(Configuration::ALIAS.'.security.access_roles', ['ROLE_ADMIN']);
        $container->setParameter(Configuration::ALIAS.'.security.custom_access_checker', false);

        (new DashboardSecurityPass())->process($container);

        self::assertFalse($container->hasDefinition(DashboardAccessSubscriber::class));
    }

    public function testRegistersSubscriberWhenSecurityPresent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS.'.dashboard.enabled', true);
        $container->setParameter(Configuration::ALIAS.'.security.allow_unauthenticated', false);
        $container->setParameter(Configuration::ALIAS.'.security.access_roles', ['ROLE_ADMIN']);
        $container->setParameter(Configuration::ALIAS.'.security.custom_access_checker', false);
        $container->setDefinition('security.authorization_checker', new Definition());
        $container->setDefinition('security.token_storage', new Definition());
        $container->setDefinition('nowo_breadcrumb_kit.access_checker.default', new Definition(ConfigurableBreadcrumbKitAccessChecker::class));
        $container->setAlias(BreadcrumbKitAccessCheckerInterface::class, 'nowo_breadcrumb_kit.access_checker.default');

        (new DashboardSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(DashboardAccessSubscriber::class));
    }

    public function testNoOpWhenDashboardEnabledParameterMissing(): void
    {
        $container = new ContainerBuilder();
        (new DashboardSecurityPass())->process($container);
        self::assertFalse($container->hasDefinition(DashboardAccessSubscriber::class));
    }

    public function testNoOpWhenAccessRolesEmptyWithoutCustomChecker(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS.'.dashboard.enabled', true);
        $container->setParameter(Configuration::ALIAS.'.security.allow_unauthenticated', false);
        $container->setParameter(Configuration::ALIAS.'.security.access_roles', []);
        $container->setParameter(Configuration::ALIAS.'.security.custom_access_checker', false);
        $container->setDefinition('security.authorization_checker', new Definition());

        (new DashboardSecurityPass())->process($container);

        self::assertFalse($container->hasDefinition(DashboardAccessSubscriber::class));
    }

    public function testNoOpWhenSubscriberAlreadyRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS.'.dashboard.enabled', true);
        $container->setParameter(Configuration::ALIAS.'.security.allow_unauthenticated', false);
        $container->setParameter(Configuration::ALIAS.'.security.access_roles', ['ROLE_ADMIN']);
        $container->setParameter(Configuration::ALIAS.'.security.custom_access_checker', false);
        $container->setDefinition('security.authorization_checker', new Definition());
        $container->setDefinition(DashboardAccessSubscriber::class, new Definition(DashboardAccessSubscriber::class));

        (new DashboardSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(DashboardAccessSubscriber::class));
    }
}
