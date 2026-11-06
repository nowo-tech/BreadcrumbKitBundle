<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler;

use InvalidArgumentException;
use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbLinkAccessCheckerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Refuses to compile when `link_access_checker` names a service that does not implement the interface: at runtime that
 * is a TypeError on every page that builds Twig, since Twig builds all its extensions and one of them needs the loader.
 *
 * Registered at TYPE_BEFORE_REMOVING: decorators and child definitions are resolved by then, so the id leads to what the
 * loader will receive. A service whose class cannot be known here (a synthetic service with no class) is left to the
 * constructor's type.
 */
final class LinkAccessCheckerPass implements CompilerPassInterface
{
    public const PARAMETER = 'nowo_breadcrumb_kit.link_access_checker';

    public function process(ContainerBuilder $container): void
    {
        $id = $container->hasParameter(self::PARAMETER) ? $container->getParameter(self::PARAMETER) : null;
        if (!\is_string($id)) {
            return;
        }

        $declared = $container->findDefinition($id)->getClass();
        if (null === $declared) {
            return;
        }

        $class = $container->getParameterBag()->resolveValue($declared);
        // is_a: a factory service may declare the interface itself as its class.
        if (!\is_string($class) || !is_a($class, BreadcrumbLinkAccessCheckerInterface::class, true)) {
            throw new InvalidArgumentException(\sprintf('NowoBreadcrumbKitBundle: the service "%s" set in nowo_breadcrumb_kit.link_access_checker must implement "%s".', $id, BreadcrumbLinkAccessCheckerInterface::class));
        }
    }
}
