<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\DependencyInjection\Compiler;

use InvalidArgumentException;
use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbLinkAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\LinkAccessCheckerPass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Real compilation with the pass at the stage the bundle registers it, so decorators and child definitions are resolved.
 */
final class LinkAccessCheckerPassTest extends TestCase
{
    public function testNothingToCheck(): void
    {
        (new LinkAccessCheckerPass())->process(new ContainerBuilder());
        $this->container(null)->compile();

        $this->addToAssertionCount(1);
    }

    public function testImplementationPasses(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker', LinkAccessCheckerPassTestChecker::class);

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testClassFromAParameterAliasAndParentAreResolved(): void
    {
        $container = $this->container('app.alias');
        $container->setParameter('app.checker.class', LinkAccessCheckerPassTestChecker::class);
        $container->register('app.parent', '%app.checker.class%')->setAbstract(true);
        $container->setDefinition('app.child', new ChildDefinition('app.parent'));
        $container->setAlias('app.alias', 'app.child');

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testFactoryDeclaringTheInterfaceAsItsClassPasses(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker', BreadcrumbLinkAccessCheckerInterface::class)
            ->setFactory([LinkAccessCheckerPassTestChecker::class, 'create']);

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testImplementingDecoratorPasses(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker', LinkAccessCheckerPassTestChecker::class);
        $container->register('app.decorator', LinkAccessCheckerPassTestChecker::class)->setDecoratedService('app.checker');

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testDecoratorOfTheWrongTypeIsRefused(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker', LinkAccessCheckerPassTestChecker::class);
        $container->register('app.decorator', stdClass::class)->setDecoratedService('app.checker');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"app.checker"');

        $container->compile();
    }

    public function testSyntheticServiceWithoutAClassIsLeftToTheConstructor(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker')->setSynthetic(true)->setPublic(true);

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testWrongTypeIsRefused(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker', stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"app.checker"');

        $container->compile();
    }

    public function testMissingClassIsRefused(): void
    {
        $container = $this->container('app.checker');
        $container->register('app.checker', 'App\\DoesNotExist');

        $this->expectException(InvalidArgumentException::class);

        $container->compile();
    }

    public function testMissingServiceIsRefused(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        $this->container('app.missing')->compile();
    }

    private function container(?string $checker): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(LinkAccessCheckerPass::PARAMETER, $checker);
        $container->addCompilerPass(new LinkAccessCheckerPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $loader = $container->register('app.loader', stdClass::class)->setPublic(true);
        if (null !== $checker) {
            $loader->setProperty('checker', new Reference($checker));
        }

        return $container;
    }
}

final class LinkAccessCheckerPassTestChecker implements BreadcrumbLinkAccessCheckerInterface
{
    public static function create(): self
    {
        return new self();
    }

    public function canLink(string $route, array $params): bool
    {
        return true;
    }
}
