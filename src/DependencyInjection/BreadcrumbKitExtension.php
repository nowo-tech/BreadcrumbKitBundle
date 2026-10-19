<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DependencyInjection;

use Nowo\BreadcrumbKitBundle\Dashboard\DashboardChrome;
use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbCollapse;
use Nowo\BreadcrumbKitBundle\Enum\CssFramework;
use Nowo\BreadcrumbKitBundle\Enum\IconSet;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbItemType;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbKitAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Security\ConfigurableBreadcrumbKitAccessChecker;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCacheInvalidator;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\ConfiguredLocales;
use Nowo\BreadcrumbKitBundle\Twig\BreadcrumbExtension;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'assets' => [
                    'packages' => [
                        Configuration::ALIAS => [
                            'base_path' => '/bundles/nowobreadcrumbkit',
                        ],
                    ],
                ],
            ]);
        }

        foreach ([
            'dashboard.path_prefix' => Configuration::DEFAULT_PATH_PREFIX,
            'dashboard.enabled' => false,
            'web_ui.path_prefix' => Configuration::DEFAULT_PATH_PREFIX,
            'web_ui.enabled' => false,
            'inline_edit.query_param' => null,
            'inline_edit.access_services' => [],
            'dashboard.layout_template' => '',
            'dashboard.import_max_bytes' => Configuration::DEFAULT_IMPORT_MAX_BYTES,
        ] as $name => $default) {
            if (!$container->hasParameter(Configuration::ALIAS.'.'.$name)) {
                $container->setParameter(Configuration::ALIAS.'.'.$name, $default);
            }
        }

        $this->prependUiKitDefaults($container);
        $this->prependFormKitDefaults($container);
    }

    /**
     * When FormKit is installed, register the {@code breadcrumb_kit} profile (labels domain +
     * Bootstrap/UiKit-friendly field classes). Forms select it via {@code #[FormKitConfig]}.
     * Does not change the host {@code default_profile}.
     */
    private function prependFormKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasBreadcrumbProfile = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (\array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            $profiles = $cfg['profiles'] ?? null;
            if (\is_array($profiles) && \array_key_exists('breadcrumb_kit', $profiles)) {
                $hostHasBreadcrumbProfile = true;
            }
        }

        $seed = [];

        if (!$hostHasCssFramework) {
            $dashboard = $this->deprecatedDashboardConfig($container);
            $raw = $dashboard['css_framework'] ?? null;
            $fw = CssFramework::from(\is_string($raw) ? $raw : CssFramework::Bootstrap5->value)->normalized();
            // FormKit accepts only bootstrap|tailwind|foundation|none (not bootstrap5 / tabler / …).
            $seed['css_framework'] = match ($fw) {
                CssFramework::Tailwind => 'tailwind',
                CssFramework::Foundation => 'foundation',
                CssFramework::None => 'none',
                default => 'bootstrap',
            };
        }

        if (!$hostHasBreadcrumbProfile) {
            $seed['profiles'] = [
                'breadcrumb_kit' => [
                    'alias' => 'breadcrumb_kit',
                    'translation_domain' => 'NowoBreadcrumbKitBundle',
                    'defaults' => [
                        'attr' => ['class' => 'nowo-ui-input nowo-ui-input--full form-control'],
                        'row_attr' => ['class' => 'mb-2'],
                    ],
                    'field_types' => [
                        'checkbox' => [
                            'attr' => ['class' => 'form-check-input'],
                            'row_attr' => ['class' => 'form-check mb-2'],
                        ],
                        'choice' => [
                            'attr' => ['class' => 'nowo-ui-input nowo-ui-input--full form-control'],
                        ],
                        'file' => [
                            'attr' => ['class' => 'nowo-ui-input nowo-ui-input--full form-control'],
                        ],
                        'textarea' => [
                            'attr' => ['class' => 'nowo-ui-input nowo-ui-input--full form-control'],
                        ],
                    ],
                ],
            ];
        }

        if ([] !== $seed) {
            $container->prependExtensionConfig('nowo_form_kit', $seed);
        }
    }

    /**
     * When UiKit is installed: seed nowo_ui_kit.css_framework / icon_set from the deprecated dashboard keys when the
     * host set them (and not the nowo_ui_kit ones), and bridge the deprecated dashboard.classes map into
     * nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle (REQ-UI-003). Prepended: the host's own UiKit config wins.
     */
    private function prependUiKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_ui_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasIconSet = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (\array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            if (\array_key_exists('icon_set', $cfg)) {
                $hostHasIconSet = true;
            }
        }

        $dashboard = $this->deprecatedDashboardConfig($container);
        $defaults = [];

        $fw = $dashboard['css_framework'] ?? null;
        if (!$hostHasCssFramework && \is_string($fw)) {
            $defaults['css_framework'] = CssFramework::from($fw)->normalized()->value;
        }
        $iconSet = $dashboard['icon_set'] ?? null;
        if (!$hostHasIconSet && \is_string($iconSet)) {
            $defaults['icon_set'] = IconSet::from($iconSet)->value;
        }

        $bridge = DashboardChrome::themeBridge(\is_array($dashboard['classes'] ?? null) ? $dashboard['classes'] : []);
        if ([] !== $bridge) {
            $defaults['theme'] = ['bundles' => [DashboardPages::BUNDLE => $bridge]];
        }

        if ([] !== $defaults) {
            $container->prependExtensionConfig('nowo_ui_kit', $defaults);
        }
    }

    /**
     * The processed deprecated dashboard.* keys, the only ones prepend() needs. prepend() sees the raw host config,
     * where any other key may still be a "%parameter%" or env placeholder (e.g. locales: '%kernel.enabled_locales%',
     * defined by FrameworkExtension::load() after prepend), so the rest of the tree is not processed here.
     *
     * @return array<array-key, mixed>
     */
    private function deprecatedDashboardConfig(ContainerBuilder $container): array
    {
        $configs = [];
        foreach ($container->getExtensionConfig($this->getAlias()) as $cfg) {
            if (\is_array($cfg['dashboard'] ?? null)) {
                $configs[] = ['dashboard' => $cfg['dashboard']];
            }
        }
        $config = $this->processConfiguration(new Configuration(), $configs);

        return \is_array($config['dashboard'] ?? null) ? $config['dashboard'] : [];
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        // locales may be a parameter (%app.locales%, %kernel.enabled_locales%): the kernel resolves it before load(),
        // a direct load() caller may not. An env var (%env(json:APP_LOCALES)%) stays a placeholder until runtime.
        $locales = $container->getParameterBag()->resolveValue($config['locales'] ?? []);
        $defaultLocale = \is_string($config['default_locale'] ?? null) ? $config['default_locale'] : null;
        $usedEnvs = [];
        $container->resolveEnvPlaceholders($locales, null, $usedEnvs);
        $runtimeLocales = [] !== $usedEnvs;
        if (!$runtimeLocales) {
            $locales = ConfiguredLocales::normalize($locales);
        }

        $container->setParameter(Configuration::ALIAS.'.project', $config['project']);
        $container->setParameter(Configuration::ALIAS.'.doctrine.connection', $config['doctrine']['connection']);
        $container->setParameter(Configuration::ALIAS.'.doctrine.table_prefix', $config['doctrine']['table_prefix']);
        $container->setParameter(Configuration::ALIAS.'.table_prefix', $config['doctrine']['table_prefix']);
        $container->setParameter(Configuration::ALIAS.'.locales', $locales);
        // With runtime (env) locales and no default_locale, the parameter holds the fallback; BreadcrumbLoader gets the
        // first locale from a factory below.
        $container->setParameter(Configuration::ALIAS.'.default_locale_resolved', ConfiguredLocales::defaultLocale($runtimeLocales ? [] : $locales, $defaultLocale));
        $container->setParameter(Configuration::ALIAS.'.default_collection', $config['default_collection']);
        $container->setParameter(Configuration::ALIAS.'.cache.ttl', (int) $config['cache']['ttl']);

        $presentation = \is_array($config['presentation'] ?? null) ? $config['presentation'] : [];
        $defaultHomeIcon = $presentation['home_icon'] ?? null;
        $defaultHomeIcon = \is_string($defaultHomeIcon) && '' !== $defaultHomeIcon ? $defaultHomeIcon : null;
        $container->setParameter(Configuration::ALIAS.'.presentation.home_icon', $defaultHomeIcon);
        $container->setParameter(Configuration::ALIAS.'.presentation.home_icon_replaces_label', (bool) ($presentation['home_icon_replaces_label'] ?? true));
        $container->setParameter(Configuration::ALIAS.'.presentation.hide_when_single_root', (bool) ($presentation['hide_when_single_root'] ?? false));
        $collapse = \is_array($presentation['collapse'] ?? null) ? $presentation['collapse'] : [];
        $container->setParameter(Configuration::ALIAS.'.presentation.collapse.enabled', (bool) ($collapse['enabled'] ?? false));
        $container->setParameter(Configuration::ALIAS.'.presentation.collapse.min_items', (int) ($collapse['min_items'] ?? BreadcrumbCollapse::DEFAULT_MIN_ITEMS));
        $container->setParameter(Configuration::ALIAS.'.presentation.collapse.breakpoint', BreadcrumbCollapse::normalizeBreakpoint($collapse['breakpoint'] ?? null) ?? BreadcrumbCollapse::DEFAULT_BREAKPOINT);

        $webUi = \is_array($config['web_ui'] ?? null) ? $config['web_ui'] : [];
        $dashboard = \is_array($config['dashboard'] ?? null) ? $config['dashboard'] : [];
        $pathPrefix = trim((string) ($webUi['path_prefix'] ?? Configuration::DEFAULT_PATH_PREFIX));
        if ('' === $pathPrefix || !str_starts_with($pathPrefix, '/')) {
            $pathPrefix = Configuration::DEFAULT_PATH_PREFIX;
        }
        $dashboardEnabled = (bool) ($webUi['enabled'] ?? false);
        $layoutTemplate = \is_string($webUi['layout_template'] ?? null) && '' !== trim($webUi['layout_template']) ? trim($webUi['layout_template']) : null;
        $pagination = \is_array($webUi['pagination'] ?? null) ? $webUi['pagination'] : [];

        // Canonical parameters (REQ-UI-003) + the 1.x names kept for host routing imports (%…dashboard.path_prefix%).
        foreach (['web_ui', 'dashboard'] as $group) {
            $container->setParameter(Configuration::ALIAS.'.'.$group.'.path_prefix', $pathPrefix);
            $container->setParameter(Configuration::ALIAS.'.'.$group.'.enabled', $dashboardEnabled);
        }
        $container->setParameter(Configuration::ALIAS.'.web_ui.layout_template', $layoutTemplate);
        $container->setParameter(Configuration::ALIAS.'.web_ui.templates', \is_array($webUi['templates'] ?? null) ? $webUi['templates'] : []);
        $container->setParameter(Configuration::ALIAS.'.web_ui.dialog_sizes', \is_array($webUi['dialog_sizes'] ?? null) ? $webUi['dialog_sizes'] : []);
        $container->setParameter(Configuration::ALIAS.'.dashboard.import_max_bytes', (int) ($webUi['import_max_bytes'] ?? Configuration::DEFAULT_IMPORT_MAX_BYTES));
        $container->setParameter(Configuration::ALIAS.'.dashboard.pagination.enabled', (bool) ($pagination['enabled'] ?? true));
        $container->setParameter(Configuration::ALIAS.'.dashboard.pagination.per_page', (int) ($pagination['per_page'] ?? 20));

        // Deprecated 1.x Twig globals (BreadcrumbKitDashboardGlobalsExtension), unused by the pages since 2.7.
        $container->setParameter(Configuration::ALIAS.'.dashboard.layout_template', $layoutTemplate ?? '');
        $cssFramework = \is_string($dashboard['css_framework'] ?? null) ? $dashboard['css_framework'] : CssFramework::Bootstrap5->value;
        $container->setParameter(Configuration::ALIAS.'.dashboard.css_framework', CssFramework::from($cssFramework)->normalized()->value);
        $container->setParameter(Configuration::ALIAS.'.dashboard.icon_set', \is_string($dashboard['icon_set'] ?? null) ? $dashboard['icon_set'] : IconSet::BootstrapIcons->value);
        $container->setParameter(Configuration::ALIAS.'.dashboard.classes', DashboardChrome::clean(\is_array($dashboard['classes'] ?? null) ? $dashboard['classes'] : []));
        $container->setParameter(Configuration::ALIAS.'.dashboard.partials', DashboardChrome::clean(\is_array($dashboard['partials'] ?? null) ? $dashboard['partials'] : []));
        $container->setParameter(Configuration::ALIAS.'.dashboard.ui_kit_stylesheet', (bool) ($dashboard['ui_kit_stylesheet'] ?? true));
        $container->setParameter(Configuration::ALIAS.'.dashboard.collections_view', (string) ($dashboard['collections_view'] ?? 'list'));
        $container->setParameter(Configuration::ALIAS.'.dashboard.row_actions_position', (string) ($dashboard['row_actions_position'] ?? 'start'));
        $container->setParameter(Configuration::ALIAS.'.dashboard.cancel_first', (bool) ($dashboard['cancel_first'] ?? false));

        $security = \is_array($config['security'] ?? null) ? $config['security'] : [];
        /** @var list<string> $accessRoles */
        $accessRoles = [];
        foreach ($security['access_roles'] ?? ['ROLE_ADMIN'] as $role) {
            if (\is_string($role) && '' !== $role) {
                $accessRoles[] = $role;
            }
        }
        $accessCheckerId = $security['access_checker'] ?? null;
        $customAccessChecker = \is_string($accessCheckerId) && '' !== $accessCheckerId;
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

        if ($dashboardEnabled) {
            $loader->load('services_dashboard.yaml');
            $this->registerAccessChecker($container, $accessRoles, $customAccessChecker ? (string) $accessCheckerId : null);
        }

        if (class_exists(WebProfilerBundle::class)) {
            $loader->load('services_profiler.yaml');
        }

        // load() runs on an isolated container (MergeExtensionConfigurationPass): has() cannot see pools such as
        // cache.app registered by FrameworkBundle, so wire an optional reference resolved at compile time.
        $poolId = trim((string) ($config['cache']['pool'] ?? ''));
        if ('' !== $poolId) {
            $poolReference = new Reference($poolId, ContainerInterface::NULL_ON_INVALID_REFERENCE);
            $container->getDefinition(BreadcrumbLoader::class)
                ->setArgument('$cachePool', $poolReference)
                ->setArgument('$cacheTtl', (int) $config['cache']['ttl']);
            $container->getDefinition(BreadcrumbCacheInvalidator::class)
                ->setArgument('$cachePool', $poolReference);
        }

        $container->getDefinition(BreadcrumbLoader::class)
            ->setArgument('$hideWhenSingleRoot', '%nowo_breadcrumb_kit.presentation.hide_when_single_root%')
            ->setArgument('$homeIconReplacesLabel', '%nowo_breadcrumb_kit.presentation.home_icon_replaces_label%')
            ->setArgument('$defaultHomeIcon', '%nowo_breadcrumb_kit.presentation.home_icon%')
            ->setArgument('$defaultCollapse', new Definition(BreadcrumbCollapse::class, [
                '%nowo_breadcrumb_kit.presentation.collapse.enabled%',
                '%nowo_breadcrumb_kit.presentation.collapse.min_items%',
                '%nowo_breadcrumb_kit.presentation.collapse.breakpoint%',
            ]));

        if ($runtimeLocales && null === $defaultLocale) {
            $container->getDefinition(BreadcrumbLoader::class)->setArgument('$defaultLocale', (new Definition('string'))
                ->setFactory([ConfiguredLocales::class, 'defaultLocale'])
                ->setArguments(['%'.Configuration::ALIAS.'.locales%']));
        }

        $container->getDefinition(BreadcrumbExtension::class)
            ->setArgument('$defaultCollection', $config['default_collection']);

        // 2.8: the item form edits the label translations as locale tabs (FormKit TranslatableMapType); an empty
        // locales list lets FormKit use its own locales (framework.enabled_locales).
        if ($container->hasDefinition(BreadcrumbItemType::class)) {
            $container->getDefinition(BreadcrumbItemType::class)
                ->setArgument('$locales', (new Definition('array'))
                    ->setFactory([ConfiguredLocales::class, 'normalize'])
                    ->setArguments(['%'.Configuration::ALIAS.'.locales%']))
                ->setArgument('$defaultLocale', match (true) {
                    // default_locale null = first configured locale (as for the trail labels)
                    null !== $defaultLocale => $defaultLocale,
                    $runtimeLocales => (new Definition('string'))
                        ->setFactory([ConfiguredLocales::class, 'defaultLocale'])
                        ->setArguments(['%'.Configuration::ALIAS.'.locales%']),
                    [] !== $locales => '%'.Configuration::ALIAS.'.default_locale_resolved%',
                    default => null,
                });
        }
    }

    /**
     * @param list<string> $accessRoles
     */
    private function registerAccessChecker(ContainerBuilder $container, array $accessRoles, ?string $accessCheckerId): void
    {
        if (null === $accessCheckerId || '' === $accessCheckerId) {
            $accessCheckerId = 'nowo_breadcrumb_kit.access_checker.default';
            $container->setDefinition($accessCheckerId, (new Definition(ConfigurableBreadcrumbKitAccessChecker::class))
                ->setAutowired(true)
                ->setArgument('$accessRoles', $accessRoles));
        }

        $container->setAlias(BreadcrumbKitAccessCheckerInterface::class, $accessCheckerId);
    }
}
