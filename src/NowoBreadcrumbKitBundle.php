<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\BreadcrumbInlineEditAccessLocatorPass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Breadcrumb trails: DB-defined items, route matching (static + dynamic params), i18n, Twig.
 */
final class NowoBreadcrumbKitBundle extends Bundle
{
    public const TRANSLATION_DOMAIN = 'NowoBreadcrumbKitBundle';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TwigPathsPass());
        $container->addCompilerPass(new BreadcrumbInlineEditAccessLocatorPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new BreadcrumbKitExtension();
        }

        /** @var ExtensionInterface $extension */
        $extension = $this->extension;

        return $extension;
    }
}
