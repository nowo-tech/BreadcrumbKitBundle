<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\DependencyInjection;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCacheInvalidator;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInvalidReferencesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Runs the extension through MergeExtensionConfigurationPass like a kernel does: load() then sees an
 * isolated container, so a pool registered by another bundle (cache.app) is invisible to has() there.
 */
final class CachePoolWiringTest extends TestCase
{
    public function testPoolFromAnotherBundleIsInjected(): void
    {
        $container = $this->build(registerPool: true);

        foreach ([BreadcrumbLoader::class, BreadcrumbCacheInvalidator::class] as $serviceId) {
            $argument = $container->getDefinition($serviceId)->getArgument('$cachePool');
            self::assertInstanceOf(Reference::class, $argument, $serviceId);
            self::assertSame('cache.app', (string) $argument);
        }
        self::assertSame(45, $container->getDefinition(BreadcrumbLoader::class)->getArgument('$cacheTtl'));
    }

    public function testMissingPoolDisablesCacheInsteadOfFailing(): void
    {
        $container = $this->build(registerPool: false);

        self::assertNull($container->getDefinition(BreadcrumbLoader::class)->getArgument('$cachePool'));
        self::assertNull($container->getDefinition(BreadcrumbCacheInvalidator::class)->getArgument('$cachePool'));
    }

    private function build(bool $registerPool): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->registerExtension(new BreadcrumbKitExtension());
        $container->loadFromExtension('nowo_breadcrumb_kit', ['cache' => ['pool' => 'cache.app', 'ttl' => 45]]);
        if ($registerPool) {
            // Registered by FrameworkBundle in the real kernel, i.e. outside this bundle's load().
            $container->register('cache.app', ArrayAdapter::class);
        }

        (new MergeExtensionConfigurationPass())->process($container);
        (new ResolveInvalidReferencesPass())->process($container);

        return $container;
    }
}
