<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\BreadcrumbInlineEditAccessLocatorPass;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class BreadcrumbInlineEditAccessLocatorPassTest extends TestCase
{
    public function testProcessDoesNothingWhenLocatorNotDefined(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS.'.inline_edit.access_services', ['admin' => 'checker']);

        (new BreadcrumbInlineEditAccessLocatorPass())->process($container);

        self::assertFalse($container->hasDefinition('nowo_breadcrumb_kit.inline_edit.access_checker_locator'));
    }

    public function testProcessWiresAccessCheckerReferences(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS.'.inline_edit.access_services', [
            'admin' => 'app.checker.admin',
            'staff' => 'app.checker.staff',
            '' => 'ignored',
            'bad' => '',
        ]);

        $locatorDef = new Definition();
        $container->setDefinition('nowo_breadcrumb_kit.inline_edit.access_checker_locator', $locatorDef);

        (new BreadcrumbInlineEditAccessLocatorPass())->process($container);

        $args = $locatorDef->getArgument(0);
        self::assertIsArray($args);
        self::assertCount(2, $args);
        self::assertInstanceOf(Reference::class, $args['admin']);
        self::assertSame('app.checker.admin', (string) $args['admin']);
        self::assertInstanceOf(Reference::class, $args['staff']);
        self::assertSame('app.checker.staff', (string) $args['staff']);
    }
}
