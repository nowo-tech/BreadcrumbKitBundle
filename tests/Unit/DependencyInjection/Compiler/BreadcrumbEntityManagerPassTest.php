<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\BreadcrumbEntityManagerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class BreadcrumbEntityManagerPassTest extends TestCase
{
    public function testWithoutTheOptionNothingChanges(): void
    {
        $container = $this->container(null);
        (new BreadcrumbEntityManagerPass())->process($container);
        (new BreadcrumbEntityManagerPass())->process(new ContainerBuilder());

        self::assertCount(2, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
        self::assertCount(0, $container->getDefinition('doctrine.orm.breadcrumb_metadata_driver')->getMethodCalls());
    }

    public function testMapsTheEntitiesOnTheConfiguredManagerOnly(): void
    {
        $container = $this->container('breadcrumb');
        (new BreadcrumbEntityManagerPass())->process($container);

        $default = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame(['App\\Entity'], array_map(static fn (array $call): mixed => $call[1][1], $default));
        $bundle = $container->getDefinition('doctrine.orm.breadcrumb_metadata_driver')->getMethodCalls();
        self::assertCount(1, $bundle);
        self::assertInstanceOf(Definition::class, $bundle[0][1][0]);
        self::assertSame(AttributeDriver::class, $bundle[0][1][0]->getClass());

        (new BreadcrumbEntityManagerPass())->process($container);
        self::assertCount(1, $container->getDefinition('doctrine.orm.breadcrumb_metadata_driver')->getMethodCalls(), 'idempotent');
    }

    public function testUnknownManagerFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"reports", but no such Doctrine entity manager');
        (new BreadcrumbEntityManagerPass())->process($this->container('reports'));
    }

    public function testNoManagersAtAllFailsTheBuild(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(BreadcrumbKitExtension::ENTITY_MANAGER_PARAMETER, 'breadcrumb');

        $this->expectException(InvalidConfigurationException::class);
        (new BreadcrumbEntityManagerPass())->process($container);
    }

    private function container(?string $name): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(BreadcrumbKitExtension::ENTITY_MANAGER_PARAMETER, $name);
        $container->setParameter('doctrine.entity_managers', [
            'default' => 'doctrine.orm.default_entity_manager',
            'breadcrumb' => 'doctrine.orm.breadcrumb_entity_manager',
            'no_chain' => 'doctrine.orm.no_chain_entity_manager',
        ]);
        $container->setDefinition('doctrine.orm.default_metadata_driver', (new Definition())
            ->addMethodCall('addDriver', [new Reference('attribute'), 'App\\Entity'])
            ->addMethodCall('addDriver', [new Reference('attribute'), BreadcrumbEntityManagerPass::ENTITY_NAMESPACE]));
        $container->setDefinition('doctrine.orm.breadcrumb_metadata_driver', new Definition());

        return $container;
    }
}
