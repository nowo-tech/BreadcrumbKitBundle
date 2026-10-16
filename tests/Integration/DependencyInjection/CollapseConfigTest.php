<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\DependencyInjection;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbCollapse;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Spec 004: presentation.collapse (opt-in "collapse on narrow screens").
 */
final class CollapseConfigTest extends TestCase
{
    public function testDefaultsAreDisabled(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->getParameter('nowo_breadcrumb_kit.presentation.collapse.enabled'));
        self::assertSame(3, $container->getParameter('nowo_breadcrumb_kit.presentation.collapse.min_items'));
        self::assertSame('40rem', $container->getParameter('nowo_breadcrumb_kit.presentation.collapse.breakpoint'));

        $argument = $container->getDefinition(BreadcrumbLoader::class)->getArgument('$defaultCollapse');
        self::assertInstanceOf(Definition::class, $argument);
        self::assertSame(BreadcrumbCollapse::class, $argument->getClass());
        self::assertSame([
            '%nowo_breadcrumb_kit.presentation.collapse.enabled%',
            '%nowo_breadcrumb_kit.presentation.collapse.min_items%',
            '%nowo_breadcrumb_kit.presentation.collapse.breakpoint%',
        ], $argument->getArguments());
    }

    public function testBooleanShorthandEnables(): void
    {
        $container = $this->load(['collapse' => true]);

        self::assertTrue($container->getParameter('nowo_breadcrumb_kit.presentation.collapse.enabled'));
        self::assertSame(3, $container->getParameter('nowo_breadcrumb_kit.presentation.collapse.min_items'));
    }

    public function testConfiguredValuesAreNormalized(): void
    {
        $container = $this->load(['collapse' => ['enabled' => true, 'min_items' => 5, 'breakpoint' => 720]]);

        self::assertTrue($container->getParameter('nowo_breadcrumb_kit.presentation.collapse.enabled'));
        self::assertSame(5, $container->getParameter('nowo_breadcrumb_kit.presentation.collapse.min_items'));
        self::assertSame('720px', $container->getParameter('nowo_breadcrumb_kit.presentation.collapse.breakpoint'));
    }

    public function testInvalidBreakpointIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('presentation.collapse.breakpoint');

        $this->load(['collapse' => ['enabled' => true, 'breakpoint' => '50%']]);
    }

    public function testMinItemsBelowTwoIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['collapse' => ['enabled' => true, 'min_items' => 1]]);
    }

    /**
     * @param array<string, mixed> $presentation
     */
    private function load(array $presentation): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new BreadcrumbKitExtension())->load([['presentation' => $presentation]], $container);

        return $container;
    }
}
