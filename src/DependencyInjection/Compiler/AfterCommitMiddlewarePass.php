<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\Doctrine\AfterCommitMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Attaches {@see AfterCommitMiddleware} only to the connection of the bundle's entity manager (2.16, audit LI-BC-M1): transactions
 * on other connections never delay or drop the bundle's events. Runs before DoctrineBundle's MiddlewaresPass. Without
 * a resolvable entity manager it falls back to the default connection.
 */
final class AfterCommitMiddlewarePass implements CompilerPassInterface
{
    /**
     * Rewrites the middleware tag with the `connection` attribute.
     *
     * @param ContainerBuilder $container The container being compiled
     *
     * @return void Nothing (side effects only)
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AfterCommitMiddleware::class)) {
            return;
        }
        $manager = $container->hasParameter(BreadcrumbKitExtension::ENTITY_MANAGER_PARAMETER) ? $container->getParameter(BreadcrumbKitExtension::ENTITY_MANAGER_PARAMETER) : null;
        if (!\is_string($manager)) {
            $manager = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : 'default';
        }
        $id = \sprintf('doctrine.orm.%s_entity_manager', \is_string($manager) ? $manager : 'default');
        $argument = $container->hasDefinition($id) ? ($container->getDefinition($id)->getArguments()[0] ?? null) : null;
        $connection = $argument instanceof Reference && 1 === preg_match('/^doctrine\.dbal\.(.+)_connection$/D', (string) $argument, $match) ? $match[1] : null;
        if (null === $connection) {
            $default = $container->hasParameter('doctrine.default_connection') ? $container->getParameter('doctrine.default_connection') : null;
            $connection = \is_string($default) ? $default : 'default';
        }

        $container->getDefinition(AfterCommitMiddleware::class)
            ->clearTag('doctrine.middleware')
            ->addTag('doctrine.middleware', ['connection' => $connection]);
    }
}
