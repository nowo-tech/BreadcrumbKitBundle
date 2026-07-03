<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\BreadcrumbInlineEditAccessLocatorPass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class NowoBreadcrumbKitBundleTest extends TestCase
{
    public function testBuildRegistersCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        (new NowoBreadcrumbKitBundle())->build($container);

        $classNames = $this->extractBeforeOptimizationPassClasses($container);

        self::assertContains(TwigPathsPass::class, $classNames);
        self::assertContains(BreadcrumbInlineEditAccessLocatorPass::class, $classNames);
    }

    /**
     * @return list<class-string>
     */
    private function extractBeforeOptimizationPassClasses(ContainerBuilder $container): array
    {
        $compilerRef = new \ReflectionProperty($container, 'compiler');
        $compiler = $compilerRef->getValue($container);

        $passConfigRef = new \ReflectionProperty($compiler, 'passConfig');
        $passConfig = $passConfigRef->getValue($compiler);

        $beforeRef = new \ReflectionProperty($passConfig, 'beforeOptimizationPasses');
        /** @var array<int, list<object>> $beforeOptimization */
        $beforeOptimization = $beforeRef->getValue($passConfig);

        $classes = [];
        foreach ($beforeOptimization[0] ?? [] as $pass) {
            $classes[] = $pass::class;
        }

        return $classes;
    }

    public function testGetContainerExtensionReturnsBreadcrumbKitExtension(): void
    {
        $bundle = new NowoBreadcrumbKitBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(BreadcrumbKitExtension::class, $extension);
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }

    public function testTranslationDomainConstant(): void
    {
        self::assertSame('NowoBreadcrumbKitBundle', NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN);
    }
}
