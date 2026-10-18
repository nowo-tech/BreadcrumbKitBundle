<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dashboard;

/**
 * 1.x admin chrome hooks (spec 003): the keys accepted by {@code dashboard.classes} and {@code dashboard.partials}.
 *
 * @deprecated since 2.7, the dashboard pages extend the UiKit scaffolds (REQ-UI-003) and read the UiKit theme slots.
 *             {@see self::themeBridge()} maps {@code dashboard.classes} into
 *             {@code nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle} for one minor version; removed in 3.0.
 */
final class DashboardChrome
{
    /**
     * @var list<string>
     */
    public const CLASS_KEYS = [
        // buttons and row actions
        'btn_primary', 'btn_secondary', 'btn_danger', 'btn_link',
        'action_view', 'action_edit', 'action_delete', 'row_actions',
        // page chrome
        'toolbar', 'page_title', 'page_intro', 'breadcrumb_nav', 'breadcrumb_link', 'breadcrumb_separator', 'search_form', 'search_input', 'search_summary',
        'empty', 'badge', 'badge_yes', 'badge_no', 'muted',
        // lists and tables
        'results', 'list', 'list_item', 'table_wrap', 'table', 'thead_row', 'th', 'th_actions', 'row', 'td', 'td_actions',
        // full-page forms
        'form', 'form_panel', 'form_actions',
        // modals and modal forms
        'modal_loading', 'modal_message', 'modal_hint', 'modal_form', 'modal_form_body', 'modal_actions', 'form_style_grid',
    ];

    /**
     * @var list<string>
     */
    public const PARTIAL_KEYS = ['modals', 'modal_shell', 'pagination', 'action_inner'];

    /**
     * 1.x class key => UiKit theme slot (null: no equivalent region, the value is dropped). When two keys map to the
     * same slot, the later key in this list wins.
     *
     * @var array<string, string|null>
     */
    public const CLASS_SLOTS = [
        'btn_primary' => 'btn_primary',
        'btn_secondary' => 'btn_secondary',
        'btn_danger' => 'btn_danger',
        'btn_link' => 'btn_link',
        'action_view' => 'action_neutral',
        'action_edit' => 'action_neutral',
        'action_delete' => 'action_danger',
        'row_actions' => 'row_actions',
        'toolbar' => 'toolbar',
        'page_title' => 'page_title',
        'page_intro' => 'page_intro',
        'breadcrumb_nav' => 'breadcrumbs',
        'breadcrumb_link' => 'breadcrumb_link',
        'breadcrumb_separator' => 'breadcrumb_separator',
        'search_form' => 'filters',
        'search_input' => null,
        'search_summary' => null,
        'empty' => 'empty',
        'badge' => 'badge_neutral',
        'badge_yes' => 'badge_success',
        'badge_no' => 'badge_neutral',
        'muted' => 'muted',
        'results' => null,
        'list' => null,
        'list_item' => null,
        'table_wrap' => 'table_wrap',
        'table' => 'table',
        'thead_row' => 'table_head_row',
        'th' => 'table_head_cell',
        'th_actions' => 'table_actions_head',
        'row' => 'table_row',
        'td' => 'table_cell',
        'td_actions' => 'table_actions_cell',
        'form' => 'form',
        'form_panel' => 'form_panel',
        'form_actions' => 'form_actions',
        'modal_loading' => null,
        'modal_message' => 'confirm_message',
        'modal_hint' => null,
        'modal_form' => null,
        'modal_form_body' => 'dialog_body',
        'modal_actions' => null,
        'form_style_grid' => null,
    ];

    /**
     * Row-action slots: UiKit adds their host classes to the action classes (no framework defaults to drop).
     */
    private const ADDITIVE_SLOTS = ['action_neutral', 'action_danger'];

    /**
     * @param array<array-key, mixed> $map
     * @param list<string>            $allowed
     *
     * @return list<string> keys of $map that are not in $allowed
     */
    public static function unknownKeys(array $map, array $allowed): array
    {
        return array_values(array_map(strval(...), array_diff(array_keys($map), $allowed)));
    }

    /**
     * Drops empty values so templates fall back to their defaults ({@code ??} / {@code is defined}).
     *
     * @param array<array-key, mixed> $map
     *
     * @return array<string, string>
     */
    public static function clean(array $map): array
    {
        $clean = [];
        foreach ($map as $key => $value) {
            if (\is_string($key) && \is_string($value) && '' !== trim($value)) {
                $clean[$key] = trim($value);
            }
        }

        return $clean;
    }

    /**
     * Bridges {@code dashboard.classes} into a {@code nowo_ui_kit.theme.bundles.NowoBreadcrumbKitBundle} entry. A 1.x
     * class value replaced the whole class list, so the slot drops UiKit's framework defaults
     * ({@code framework_defaults: false}) and keeps only its semantic {@code nowo-ui-*} class plus the host value
     * (row-action slots are additive). {@code dashboard.partials} is not bridged: the 1.x partial inputs differ from
     * the UiKit ones.
     *
     * @param array<array-key, mixed> $classes dashboard.classes
     *
     * @return array{classes?: array<string, array{value: string, framework_defaults: bool}|string>}
     */
    public static function themeBridge(array $classes): array
    {
        $bridge = [];
        $clean = self::clean($classes);
        foreach (self::CLASS_SLOTS as $key => $slot) {
            if (null === $slot || !isset($clean[$key])) {
                continue;
            }
            $bridge['classes'][$slot] = \in_array($slot, self::ADDITIVE_SLOTS, true)
                ? $clean[$key]
                : ['value' => $clean[$key], 'framework_defaults' => false];
        }

        return $bridge;
    }
}
