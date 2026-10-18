<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DependencyInjection;

use Nowo\BreadcrumbKitBundle\Dashboard\DashboardChrome;
use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbCollapse;
use Nowo\BreadcrumbKitBundle\Enum\CssFramework;
use Nowo\BreadcrumbKitBundle\Enum\IconSet;
use Nowo\BreadcrumbKitBundle\Enum\ModalSize;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * YAML defaults; trail definitions live in the database (collections + items).
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_breadcrumb_kit';

    public const DEFAULT_PATH_PREFIX = '/breadcrumb-kit-admin';

    public const DEFAULT_IMPORT_MAX_BYTES = 2_097_152;

    /** 1.x default of dashboard.layout_template (removed CDN layout); read as null (UiKit layout). */
    public const LEGACY_LAYOUT_TEMPLATE = '@NowoBreadcrumbKitBundle/dashboard/layout.html.twig';

    private const DEPRECATION_PACKAGE = 'nowo-tech/breadcrumb-kit-bundle';

    private const DEPRECATION_VERSION = '2.7';

    /** 1.x modal size => UiKit dialog size. */
    private const MODAL_TO_DIALOG = ['normal' => 'md', 'lg' => 'lg', 'xl' => 'xl'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root = $treeBuilder->getRootNode();

        $root
            ->beforeNormalization()
                ->ifArray()
                ->then(static fn (array $v): array => self::mapDeprecatedDashboardKeys($v))
            ->end()
            ->children()
                ->scalarNode('project')
                    ->info('Optional project id when several apps share one DB.')
                    ->defaultNull()
                ->end()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')->defaultValue('default')->end()
                        ->scalarNode('table_prefix')
                            ->info('Prefix prepended to table names (dashboard_breadcrumb_collection, dashboard_breadcrumb_item). Empty = no prefix.')
                            ->defaultValue('')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                        ->scalarNode('pool')
                            ->info('PSR-6 pool service id, e.g. cache.app. Empty disables item-list cache.')
                            ->defaultValue('cache.app')
                        ->end()
                    ->end()
                ->end()
                ->variableNode('locales')
                    ->info('Locale codes (list), or a parameter / env var holding one: "%app.locales%", "%kernel.enabled_locales%", "%env(json:APP_LOCALES)%" (resolved at runtime).')
                    ->defaultValue([])
                    ->validate()
                        ->ifTrue(static fn (mixed $v): bool => !\is_string($v) && !self::isLocaleList($v))
                        ->thenInvalid('Invalid nowo_breadcrumb_kit.locales %s: use a list of locale codes or a parameter / env placeholder holding one.')
                    ->end()
                ->end()
                ->scalarNode('default_locale')
                    ->info('Default locale; null = first entry of locales (or "en").')
                    ->defaultNull()
                ->end()
                ->scalarNode('default_collection')
                    ->info('Collection code used when none is passed to Twig/helpers.')
                    ->defaultValue('default')
                ->end()
                ->arrayNode('presentation')
                    ->info('Default trail presentation; collection homeIcon and responsiveConfig can override per collection.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('home_icon')
                            ->info('Fallback home/root icon (HTML, emoji, or app-specific token) when the collection homeIcon is empty.')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('home_icon_replaces_label')
                            ->info('When true and a home icon is set, the first crumb shows the icon instead of its text label (label stays in aria-label).')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('hide_when_single_root')
                            ->info('When true, hides the trail on pages where the only crumb is the root item and it is the current page (typical home).')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('collapse')
                            ->info('Opt-in GitHub-style trail on narrow screens: below the breakpoint only a "…" menu with the previous crumbs, one separator and the current crumb are shown. Collections override it with responsiveConfig.collapse (true/false or {enabled, min_items, breakpoint}).')
                            ->canBeEnabled()
                            ->children()
                                ->integerNode('min_items')
                                    ->info('Minimum number of crumbs (current included) before the trail collapses.')
                                    ->min(BreadcrumbCollapse::MIN_ITEMS_FLOOR)
                                    ->defaultValue(BreadcrumbCollapse::DEFAULT_MIN_ITEMS)
                                ->end()
                                ->scalarNode('breakpoint')
                                    ->info('Width of the breadcrumb wrapper (container query; viewport media query fallback) below which the trail collapses: a length in px, rem or em, or an integer (pixels).')
                                    ->defaultValue(BreadcrumbCollapse::DEFAULT_BREAKPOINT)
                                    ->validate()
                                        ->ifTrue(static fn (mixed $v): bool => null === BreadcrumbCollapse::normalizeBreakpoint($v))
                                        ->thenInvalid('Invalid presentation.collapse.breakpoint %s: use a length in px, rem or em (e.g. "40rem") or an integer (pixels).')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->info('Admin dashboard (CRUD of collections and items) on the UiKit page contract (REQ-UI-003). Import the bundle routing with path_prefix (see docs).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Registers the dashboard controllers and forms.')
                        ->end()
                        ->scalarNode('path_prefix')
                            ->defaultValue(self::DEFAULT_PATH_PREFIX)
                            ->info('URL prefix of the dashboard routes (leading slash, no trailing slash). Use the same value when importing the routing (parameter %nowo_breadcrumb_kit.web_ui.path_prefix%).')
                        ->end()
                        ->scalarNode('layout_template')
                            ->defaultNull()
                            ->info('Per-bundle layout override for the dashboard pages; null = nowo_ui_kit.layouts.admin. It must render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content…); a 1.x layout that only defines nowo_breadcrumb_kit_content is wrapped by a deprecated bridge.')
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template override (page => template): '.implode(', ', array_keys(DashboardPages::TEMPLATES)).'. Extend @!NowoBreadcrumbKitBundle/... and override a data block.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('page')
                            ->scalarPrototype()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => [] !== array_diff(array_keys($v), array_keys(DashboardPages::TEMPLATES)))
                                ->then(static function (array $v): array {
                                    throw new \InvalidArgumentException(\sprintf('Unknown nowo_breadcrumb_kit.web_ui.templates page(s): %s. Allowed: %s.', implode(', ', array_diff(array_keys($v), array_keys(DashboardPages::TEMPLATES))), implode(', ', array_keys(DashboardPages::TEMPLATES))));
                                })
                            ->end()
                        ->end()
                        ->integerNode('import_max_bytes')
                            ->defaultValue(self::DEFAULT_IMPORT_MAX_BYTES)
                            ->min(1024)
                            ->info('Max JSON upload size for the dashboard import (default 2 MiB).')
                        ->end()
                        ->arrayNode('pagination')
                            ->info('Pagination of the collections and items lists.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultTrue()->end()
                                ->integerNode('per_page')->min(1)->max(500)->defaultValue(20)->end()
                            ->end()
                        ->end()
                        ->arrayNode('dialog_sizes')
                            ->info('UiKit dialog size (sm, md, lg, xl) of the collection form, item form and import dialogs.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('collection_form')->values(DashboardPages::DIALOG_SIZES)->defaultValue('lg')->end()
                                ->enumNode('item_form')->values(DashboardPages::DIALOG_SIZES)->defaultValue('lg')->end()
                                ->enumNode('import')->values(DashboardPages::DIALOG_SIZES)->defaultValue('md')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('dashboard')
                    ->info('Deprecated since 2.7: use web_ui (REQ-UI-003) and the UiKit theme (nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle). Removed in 3.0.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Deprecated alias of web_ui.enabled.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_breadcrumb_kit.web_ui.enabled" instead.')
                        ->end()
                        ->scalarNode('path_prefix')
                            ->defaultValue(self::DEFAULT_PATH_PREFIX)
                            ->info('Deprecated alias of web_ui.path_prefix.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_breadcrumb_kit.web_ui.path_prefix" instead.')
                        ->end()
                        ->scalarNode('layout_template')
                            ->defaultNull()
                            ->info('Deprecated alias of web_ui.layout_template (the 1.x default @NowoBreadcrumbKitBundle/dashboard/layout.html.twig is read as null).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_breadcrumb_kit.web_ui.layout_template" (null = nowo_ui_kit.layouts.admin) instead.')
                        ->end()
                        ->scalarNode('css_framework')
                            ->defaultNull()
                            ->info('Deprecated: seeded into nowo_ui_kit.css_framework when set here and not in nowo_ui_kit. See Nowo\BreadcrumbKitBundle\Enum\CssFramework.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, set "nowo_ui_kit.css_framework" instead.')
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => null !== $v && !\in_array($v, CssFramework::values(), true))
                                ->thenInvalid('Invalid css_framework %s.')
                            ->end()
                        ->end()
                        ->scalarNode('icon_set')
                            ->defaultNull()
                            ->info('Deprecated: seeded into nowo_ui_kit.icon_set when set here and not in nowo_ui_kit. See Nowo\BreadcrumbKitBundle\Enum\IconSet.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, set "nowo_ui_kit.icon_set" instead.')
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => null !== $v && !\in_array($v, IconSet::values(), true))
                                ->thenInvalid('Invalid icon_set %s.')
                            ->end()
                        ->end()
                        ->integerNode('import_max_bytes')
                            ->min(1024)
                            ->info('Deprecated alias of web_ui.import_max_bytes.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_breadcrumb_kit.web_ui.import_max_bytes" instead.')
                        ->end()
                        ->arrayNode('pagination')
                            ->info('Deprecated alias of web_ui.pagination.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_breadcrumb_kit.web_ui.pagination" instead.')
                            ->children()
                                ->booleanNode('enabled')->end()
                                ->integerNode('per_page')->min(1)->max(500)->end()
                            ->end()
                        ->end()
                        ->arrayNode('modals')
                            ->info('Deprecated alias of web_ui.dialog_sizes (normal = md); "delete" has no effect (UiKit confirm dialog).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_breadcrumb_kit.web_ui.dialog_sizes" instead.')
                            ->children()
                                ->enumNode('collection_form')->values(ModalSize::values())->end()
                                ->enumNode('item_form')->values(ModalSize::values())->end()
                                ->enumNode('import')->values(ModalSize::values())->end()
                                ->enumNode('delete')->values(ModalSize::values())->end()
                            ->end()
                        ->end()
                        ->arrayNode('classes')
                            ->info('Deprecated 1.x admin chrome class map (spec 003), bridged into nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle.classes (UiKit theme slots). Keys: '.implode(', ', DashboardChrome::CLASS_KEYS).'.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle.classes" (UiKit theme slots) instead.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => [] !== DashboardChrome::unknownKeys($v, DashboardChrome::CLASS_KEYS))
                                ->then(static function (array $v): array {
                                    throw new \InvalidArgumentException(\sprintf('Unknown nowo_breadcrumb_kit.dashboard.classes key(s): %s. Allowed: %s.', implode(', ', DashboardChrome::unknownKeys($v, DashboardChrome::CLASS_KEYS)), implode(', ', DashboardChrome::CLASS_KEYS)));
                                })
                            ->end()
                        ->end()
                        ->arrayNode('partials')
                            ->info('Deprecated 1.x admin chrome partial map (spec 003): '.implode(', ', DashboardChrome::PARTIAL_KEYS).'. Ignored: the input of the UiKit partials differs (use nowo_ui_kit.theme.partials).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and ignored, use "nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle.partials" (UiKit partial names) instead.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => [] !== DashboardChrome::unknownKeys($v, DashboardChrome::PARTIAL_KEYS))
                                ->then(static function (array $v): array {
                                    throw new \InvalidArgumentException(\sprintf('Unknown nowo_breadcrumb_kit.dashboard.partials key(s): %s. Allowed: %s.', implode(', ', DashboardChrome::unknownKeys($v, DashboardChrome::PARTIAL_KEYS)), implode(', ', DashboardChrome::PARTIAL_KEYS)));
                                })
                            ->end()
                        ->end()
                        ->booleanNode('ui_kit_stylesheet')
                            ->info('Deprecated: the layout loads nowo-ui.css (only the 1.x layout bridge still reads it).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, the layout (nowo_ui_kit.layouts.admin) loads the UiKit assets.')
                        ->end()
                        ->enumNode('collections_view')
                            ->values(['list', 'table'])
                            ->info('Deprecated, no effect: the collections index is the UiKit list scaffold (table).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and has no effect: the collections index renders the UiKit list scaffold.')
                        ->end()
                        ->enumNode('row_actions_position')
                            ->values(['start', 'end'])
                            ->info('Deprecated, no effect: the UiKit list scaffold renders the actions column last.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and has no effect: the UiKit list scaffold renders the actions column last.')
                        ->end()
                        ->booleanNode('cancel_first')
                            ->info('Deprecated, no effect: the UiKit form actions render submit then cancel.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and has no effect: the UiKit form actions render submit, then cancel.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->info('Private dashboard access (REQ-UI-002). Defaults to ROLE_ADMIN; demos may set allow_unauthenticated.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('access_checker')
                            ->defaultNull()
                            ->info('Service id implementing BreadcrumbKitAccessCheckerInterface. null = built-in role checker.')
                        ->end()
                        ->arrayNode('access_roles')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                            ->info('User must be granted at least one role. Empty list is fail-closed (deny via access checker). Use allow_unauthenticated for demos only.')
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->defaultFalse()
                            ->info('DEV/DEMO only: allow dashboard without SecurityBundle / without login. Never true in production.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('inline_edit')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('query_param')
                            ->defaultNull()
                            ->info('Query parameter name; when present and truthy (1, true, yes, on), the default breadcrumb template may show an edit control if the collection enables a checker and the checker allows access.')
                        ->end()
                        ->arrayNode('access_services')
                            ->info('Logical key => service id implementing BreadcrumbInlineEditAccessCheckerInterface. Collections choose one key in the dashboard.')
                            ->useAttributeAsKey('key')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    private static function isLocaleList(mixed $v): bool
    {
        if (!\is_array($v)) {
            return false;
        }
        foreach ($v as $locale) {
            if (!\is_string($locale)) {
                return false;
            }
        }

        return true;
    }

    /**
     * REQ-UI-003 canonical keys: copies the deprecated dashboard.* aliases into web_ui.* when the canonical key is not
     * set (the canonical key wins). The 1.x default layout (a standalone CDN page, removed) is read as null.
     *
     * @param array<array-key, mixed> $v
     *
     * @return array<array-key, mixed>
     */
    private static function mapDeprecatedDashboardKeys(array $v): array
    {
        $dashboard = \is_array($v['dashboard'] ?? null) ? $v['dashboard'] : [];
        $webUi = \is_array($v['web_ui'] ?? null) ? $v['web_ui'] : [];

        foreach (['enabled', 'path_prefix', 'layout_template', 'import_max_bytes'] as $key) {
            if (\array_key_exists($key, $dashboard) && !\array_key_exists($key, $webUi)) {
                $webUi[$key] = $dashboard[$key];
            }
        }
        if (\is_array($dashboard['pagination'] ?? null)) {
            $webUi['pagination'] = (\is_array($webUi['pagination'] ?? null) ? $webUi['pagination'] : []) + $dashboard['pagination'];
        }
        if (\is_array($dashboard['modals'] ?? null)) {
            $sizes = \is_array($webUi['dialog_sizes'] ?? null) ? $webUi['dialog_sizes'] : [];
            foreach (['collection_form', 'item_form', 'import'] as $key) {
                $size = $dashboard['modals'][$key] ?? null;
                if (\is_string($size) && isset(self::MODAL_TO_DIALOG[$size]) && !\array_key_exists($key, $sizes)) {
                    $sizes[$key] = self::MODAL_TO_DIALOG[$size];
                }
            }
            $webUi['dialog_sizes'] = $sizes;
        }
        if (self::LEGACY_LAYOUT_TEMPLATE === ($webUi['layout_template'] ?? null) || '' === ($webUi['layout_template'] ?? null)) {
            $webUi['layout_template'] = null;
        }
        if ([] !== $webUi) {
            $v['web_ui'] = $webUi;
        }

        return $v;
    }
}
