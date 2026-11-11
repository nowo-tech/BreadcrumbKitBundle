<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\AfterCommitMiddlewarePass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\BreadcrumbEntityManagerPass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\BreadcrumbInlineEditAccessLocatorPass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\DashboardSecurityPass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\LinkAccessCheckerPass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
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
        $container->addCompilerPass(new DashboardSecurityPass());
        $container->addCompilerPass(new CsrfProtectionPass());
        // After decoration: a decorator of the checker is what the loader receives.
        $container->addCompilerPass(new LinkAccessCheckerPass(), PassConfig::TYPE_BEFORE_REMOVING);
        // doctrine.entity_manager (2.16): the entities on that manager only.
        $container->addCompilerPass(new BreadcrumbEntityManagerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
        // Before DoctrineBundle's MiddlewaresPass: the after-commit middleware on the bundle's connection only.
        $container->addCompilerPass(new AfterCommitMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
    }

    public function getContainerExtension(): ExtensionInterface
    {
        // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
        $this->extension ??= new BreadcrumbKitExtension();

        /** @var ExtensionInterface $extension */
        $extension = $this->extension;

        return $extension;
    }
}
