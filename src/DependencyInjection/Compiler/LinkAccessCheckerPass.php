<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler;

use InvalidArgumentException;
use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbLinkAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Refuses to compile when `link_access_checker` names a service that does not implement the interface: at runtime that
 * is a TypeError on every page that builds Twig, since Twig builds all its extensions and one of them needs the loader.
 * A service whose class cannot be known here (no class, synthetic) is left to the constructor's type.
 */
final class LinkAccessCheckerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(BreadcrumbLoader::class)) {
            return;
        }
        $arguments = $container->getDefinition(BreadcrumbLoader::class)->getArguments();
        $reference = $arguments['$linkAccessChecker'] ?? null;
        if (!$reference instanceof Reference) {
            return;
        }

        $id = (string) $reference;
        $definition = $container->findDefinition($id);
        while (null === $definition->getClass() && $definition instanceof ChildDefinition) {
            $definition = $container->findDefinition($definition->getParent());
        }
        if (null === $definition->getClass()) {
            return;
        }

        $class = $container->getParameterBag()->resolveValue($definition->getClass());
        if (!\is_string($class) || !is_subclass_of($class, BreadcrumbLinkAccessCheckerInterface::class)) {
            throw new InvalidArgumentException(\sprintf('NowoBreadcrumbKitBundle: the service "%s" set in nowo_breadcrumb_kit.link_access_checker must implement "%s".', $id, BreadcrumbLinkAccessCheckerInterface::class));
        }
    }
}
