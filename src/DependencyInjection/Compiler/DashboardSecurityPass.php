<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler;

use Nowo\BreadcrumbKitBundle\DependencyInjection\Configuration;
use Nowo\BreadcrumbKitBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbKitAccessCheckerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Requires SecurityBundle when the dashboard is enabled (unless allow_unauthenticated).
 * Registers DashboardAccessSubscriber for role/checker enforcement (REQ-UI-002).
 */
final class DashboardSecurityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(Configuration::ALIAS.'.dashboard.enabled')) {
            return;
        }
        if (!$container->getParameter(Configuration::ALIAS.'.dashboard.enabled')) {
            return;
        }

        $allowUnauthenticated = (bool) $container->getParameter(Configuration::ALIAS.'.security.allow_unauthenticated');
        $hasSecurity = $container->has('security.authorization_checker');

        if (!$hasSecurity && !$allowUnauthenticated) {
            throw new InvalidConfigurationException(
                'nowo_breadcrumb_kit.dashboard.enabled requires symfony/security-bundle (security.authorization_checker), '
                .'or set nowo_breadcrumb_kit.security.allow_unauthenticated: true (dev/demo only — never in production).'
            );
        }

        if ($allowUnauthenticated) {
            return;
        }

        /** @var list<string> $accessRoles */
        $accessRoles = $container->getParameter(Configuration::ALIAS.'.security.access_roles');
        $customChecker = (bool) $container->getParameter(Configuration::ALIAS.'.security.custom_access_checker');
        // Empty access_roles with the default checker = no bundle-level enforcement (firewall only).
        if ($accessRoles === [] && !$customChecker) {
            return;
        }

        if ($container->hasDefinition(DashboardAccessSubscriber::class)) {
            return;
        }

        $definition = $container->register(DashboardAccessSubscriber::class, DashboardAccessSubscriber::class)
            ->setArgument('$accessChecker', new Reference(BreadcrumbKitAccessCheckerInterface::class))
            ->addTag('kernel.event_subscriber');

        if ($container->has('security.token_storage')) {
            $definition->setArgument('$tokenStorage', new Reference('security.token_storage'));
        }
    }
}
