<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dashboard;

/**
 * Admin chrome hooks (spec 003): the keys accepted by {@code dashboard.classes} and {@code dashboard.partials}.
 *
 * Each class key replaces the class list the templates would otherwise compute (UiKit macro or legacy class);
 * an unset key keeps the default markup. Each partial key replaces one included template.
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
}
