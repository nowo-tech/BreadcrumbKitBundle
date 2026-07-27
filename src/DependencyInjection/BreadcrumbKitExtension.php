<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DependencyInjection;

use Nowo\BreadcrumbKitBundle\Security\BreadcrumbKitAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Security\ConfigurableBreadcrumbKitAccessChecker;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Twig\BreadcrumbExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * {@see PrependExtensionInterface}: define `dashboard.path_prefix` before TwigBundle loads
 * `twig.globals` that reference `%nowo_breadcrumb_kit.dashboard.path_prefix%` (bundle order).
 */
final class BreadcrumbKitExtension extends Extension implements PrependExtensionInterface
{
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(Configuration::ALIAS.'.dashboard.path_prefix')) {
            $container->setParameter(Configuration::ALIAS.'.dashboard.path_prefix', '/breadcrumb-kit-admin');
        }
        if (!$container->hasParameter(Configuration::ALIAS.'.dashboard.enabled')) {
            $container->setParameter(Configuration::ALIAS.'.dashboard.enabled', false);
        }
        if (!$container->hasParameter(Configuration::ALIAS.'.inline_edit.query_param')) {
            $container->setParameter(Configuration::ALIAS.'.inline_edit.query_param', null);
        }
        if (!$container->hasParameter(Configuration::ALIAS.'.inline_edit.access_services')) {
            $container->setParameter(Configuration::ALIAS.'.inline_edit.access_services', []);
        }
        if (!$container->hasParameter(Configuration::ALIAS.'.dashboard.layout_template')) {
            $container->setParameter(Configuration::ALIAS.'.dashboard.layout_template', '@NowoBreadcrumbKitBundle/dashboard/layout.html.twig');
        }
        if (!$container->hasParameter(Configuration::ALIAS.'.dashboard.import_max_bytes')) {
            $container->setParameter(Configuration::ALIAS.'.dashboard.import_max_bytes', 2_097_152);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $locales = $config['locales'] ?? [];
        $defaultLocale = $config['default_locale'] ?? null;
        if (null === $defaultLocale && [] !== $locales) {
            $defaultLocale = $locales[0];
        }

        $container->setParameter(Configuration::ALIAS.'.project', $config['project']);
        $container->setParameter(Configuration::ALIAS.'.doctrine.connection', $config['doctrine']['connection']);
        $container->setParameter(Configuration::ALIAS.'.doctrine.table_prefix', $config['doctrine']['table_prefix']);
        $container->setParameter(Configuration::ALIAS.'.table_prefix', $config['doctrine']['table_prefix']);
        $container->setParameter(Configuration::ALIAS.'.locales', $locales);
        $container->setParameter(Configuration::ALIAS.'.default_locale_resolved', $defaultLocale ?? 'en');
        $container->setParameter(Configuration::ALIAS.'.default_collection', $config['default_collection']);
        $container->setParameter(Configuration::ALIAS.'.cache.ttl', (int) $config['cache']['ttl']);

        $presentation = \is_array($config['presentation'] ?? null) ? $config['presentation'] : [];
        $defaultHomeIcon = $presentation['home_icon'] ?? null;
        $defaultHomeIcon = \is_string($defaultHomeIcon) && '' !== $defaultHomeIcon ? $defaultHomeIcon : null;
        $container->setParameter(Configuration::ALIAS.'.presentation.home_icon', $defaultHomeIcon);
        $container->setParameter(Configuration::ALIAS.'.presentation.home_icon_replaces_label', (bool) ($presentation['home_icon_replaces_label'] ?? true));
        $container->setParameter(Configuration::ALIAS.'.presentation.hide_when_single_root', (bool) ($presentation['hide_when_single_root'] ?? false));

        $dashboard = \is_array($config['dashboard'] ?? null) ? $config['dashboard'] : [];
        $pathPrefix = trim((string) ($dashboard['path_prefix'] ?? '/breadcrumb-kit-admin'));
        if ('' === $pathPrefix || !str_starts_with($pathPrefix, '/')) {
            $pathPrefix = '/breadcrumb-kit-admin';
        }
        $container->setParameter(Configuration::ALIAS.'.dashboard.path_prefix', $pathPrefix);
        $container->setParameter(Configuration::ALIAS.'.dashboard.enabled', (bool) ($dashboard['enabled'] ?? false));
        $layoutTemplate = trim((string) ($dashboard['layout_template'] ?? '@NowoBreadcrumbKitBundle/dashboard/layout.html.twig'));
        if ('' === $layoutTemplate) {
            $layoutTemplate = '@NowoBreadcrumbKitBundle/dashboard/layout.html.twig';
        }
        $container->setParameter(Configuration::ALIAS.'.dashboard.layout_template', $layoutTemplate);
        $cssFramework = (string) ($dashboard['css_framework'] ?? 'bootstrap5');
        if ($cssFramework === 'bootstrap') {
            $cssFramework = 'bootstrap5';
        }
        $container->setParameter(Configuration::ALIAS.'.dashboard.css_framework', $cssFramework);
        $container->setParameter(Configuration::ALIAS.'.dashboard.icon_set', (string) ($dashboard['icon_set'] ?? 'bootstrap-icons'));
        $container->setParameter(Configuration::ALIAS.'.dashboard.import_max_bytes', (int) ($dashboard['import_max_bytes'] ?? 2_097_152));
        $pagination = \is_array($dashboard['pagination'] ?? null) ? $dashboard['pagination'] : [];
        $container->setParameter(Configuration::ALIAS.'.dashboard.pagination.enabled', (bool) ($pagination['enabled'] ?? true));
        $container->setParameter(Configuration::ALIAS.'.dashboard.pagination.per_page', (int) ($pagination['per_page'] ?? 20));
        $container->setParameter(Configuration::ALIAS.'.dashboard.modals', $dashboard['modals'] ?? [
            'collection_form' => 'lg',
            'item_form' => 'lg',
            'import' => 'normal',
            'delete' => 'normal',
        ]);

        $security = \is_array($config['security'] ?? null) ? $config['security'] : [];
        /** @var list<string> $accessRoles */
        $accessRoles = [];
        foreach ($security['access_roles'] ?? ['ROLE_ADMIN'] as $role) {
            if (\is_string($role) && $role !== '') {
                $accessRoles[] = $role;
            }
        }
        $accessCheckerId = $security['access_checker'] ?? null;
        $customAccessChecker = \is_string($accessCheckerId) && $accessCheckerId !== '';
        $container->setParameter(Configuration::ALIAS.'.security.access_roles', $accessRoles);
        $container->setParameter(Configuration::ALIAS.'.security.allow_unauthenticated', (bool) ($security['allow_unauthenticated'] ?? false));
        $container->setParameter(Configuration::ALIAS.'.security.custom_access_checker', $customAccessChecker);

        $inlineEdit = \is_array($config['inline_edit'] ?? null) ? $config['inline_edit'] : [];
        $queryParam = $inlineEdit['query_param'] ?? null;
        $queryParam = \is_string($queryParam) && '' !== $queryParam ? $queryParam : null;
        $accessServices = \is_array($inlineEdit['access_services'] ?? null) ? $inlineEdit['access_services'] : [];
        $sanitizedAccess = [];
        foreach ($accessServices as $logicalKey => $serviceId) {
            if (!\is_string($logicalKey) || '' === $logicalKey || !\is_string($serviceId) || '' === $serviceId) {
                continue;
            }
            $sanitizedAccess[$logicalKey] = $serviceId;
        }
        $container->setParameter(Configuration::ALIAS.'.inline_edit.query_param', $queryParam);
        $container->setParameter(Configuration::ALIAS.'.inline_edit.access_services', $sanitizedAccess);

        $container->register('nowo_breadcrumb_kit.inline_edit.access_checker_locator', ServiceLocator::class)
            ->setArguments([[]])
            ->addTag('container.service_locator');

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        if (($dashboard['enabled'] ?? false) === true) {
            $loader->load('services_dashboard.yaml');
            $this->registerAccessChecker($container, $accessRoles, $customAccessChecker ? (string) $accessCheckerId : null);
        }

        if (class_exists('Symfony\\Bundle\\WebProfilerBundle\\WebProfilerBundle')) {
            $loader->load('services_profiler.yaml');
        }

        $poolId = trim((string) ($config['cache']['pool'] ?? ''));
        if ('' !== $poolId && $container->has($poolId)) {
            $container->getDefinition(BreadcrumbLoader::class)
                ->setArgument('$cachePool', new Reference($poolId))
                ->setArgument('$cacheTtl', (int) $config['cache']['ttl']);
        }

        $container->getDefinition(BreadcrumbLoader::class)
            ->setArgument('$hideWhenSingleRoot', '%nowo_breadcrumb_kit.presentation.hide_when_single_root%')
            ->setArgument('$homeIconReplacesLabel', '%nowo_breadcrumb_kit.presentation.home_icon_replaces_label%')
            ->setArgument('$defaultHomeIcon', '%nowo_breadcrumb_kit.presentation.home_icon%');

        $container->getDefinition(BreadcrumbExtension::class)
            ->setArgument('$defaultCollection', $config['default_collection']);
    }

    /**
     * @param list<string> $accessRoles
     */
    private function registerAccessChecker(ContainerBuilder $container, array $accessRoles, ?string $accessCheckerId): void
    {
        if ($accessCheckerId === null || $accessCheckerId === '') {
            $accessCheckerId = 'nowo_breadcrumb_kit.access_checker.default';
            $container->setDefinition($accessCheckerId, (new Definition(ConfigurableBreadcrumbKitAccessChecker::class))
                ->setAutowired(true)
                ->setArgument('$accessRoles', $accessRoles));
        }

        $container->setAlias(BreadcrumbKitAccessCheckerInterface::class, $accessCheckerId);
    }
}
