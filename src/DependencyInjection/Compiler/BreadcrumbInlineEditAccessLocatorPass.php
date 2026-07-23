<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler;

use Nowo\BreadcrumbKitBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires logical access keys from config to checker service instances (after app services are registered).
 */
final class BreadcrumbInlineEditAccessLocatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('nowo_breadcrumb_kit.inline_edit.access_checker_locator')) {
            return;
        }

        /** @var array<string, string> $map */
        $map = $container->getParameter(Configuration::ALIAS.'.inline_edit.access_services');

        $refs = [];
        foreach ($map as $logicalKey => $serviceId) {
            if ('' === $logicalKey || '' === $serviceId) {
                continue;
            }
            $refs[$logicalKey] = new Reference($serviceId);
        }

        $container->getDefinition('nowo_breadcrumb_kit.inline_edit.access_checker_locator')->setArgument(0, $refs);
    }
}
