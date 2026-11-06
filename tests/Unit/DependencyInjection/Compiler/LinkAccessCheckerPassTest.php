<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\DependencyInjection\Compiler;

use InvalidArgumentException;
use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbLinkAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\LinkAccessCheckerPass;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

final class LinkAccessCheckerPassTest extends TestCase
{
    public function testNothingToCheck(): void
    {
        (new LinkAccessCheckerPass())->process(new ContainerBuilder());
        (new LinkAccessCheckerPass())->process($this->container(null));

        $this->addToAssertionCount(1);
    }

    public function testImplementationPasses(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker', LinkAccessCheckerPassTestChecker::class);

        (new LinkAccessCheckerPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testClassFromAParameterAliasAndParentAreResolved(): void
    {
        $container = $this->container('app.alias');
        $container->setParameter('app.checker.class', LinkAccessCheckerPassTestChecker::class);
        $container->register('app.parent', '%app.checker.class%');
        $container->setDefinition('app.child', new ChildDefinition('app.parent'));
        $container->setAlias('app.alias', 'app.child');

        (new LinkAccessCheckerPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testServiceWithoutAKnownClassIsLeftToTheConstructor(): void
    {
        $container = $this->container('app.checker');
        $container->setDefinition('app.checker', new Definition());

        (new LinkAccessCheckerPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testWrongTypeIsRefused(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker', stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"app.checker"');

        (new LinkAccessCheckerPass())->process($container);
    }

    public function testMissingClassIsRefused(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker', 'App\\DoesNotExist');

        $this->expectException(InvalidArgumentException::class);

        (new LinkAccessCheckerPass())->process($container);
    }

    public function testMissingServiceIsRefused(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        (new LinkAccessCheckerPass())->process($this->container('app.missing'));
    }

    private function container(?string $checker): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(BreadcrumbLoader::class, BreadcrumbLoader::class)
            ->setArgument('$linkAccessChecker', null === $checker ? null : new Reference($checker));

        return $container;
    }
}

final class LinkAccessCheckerPassTestChecker implements BreadcrumbLinkAccessCheckerInterface
{
    public function canLink(string $route, array $params): bool
    {
        return true;
    }
}
