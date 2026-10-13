<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Templates;

use Nowo\BreadcrumbKitBundle\Tests\Support\DashboardTemplateRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Spec 003: a host can repaint the dashboard with config (class map, partial paths, options), a layout that pulls
 * the slot blocks, and `@!NowoBreadcrumbKitBundle` block overrides — without copying any page.
 */
final class DashboardChromeHooksTest extends TestCase
{
    private const HOST_LAYOUT = <<<'TWIG'
        {% set bk_page_actions_in_layout = true %}
        {% set bk_page_title_in_layout = true %}
        {% set bk_title_suffix = ' — Host' %}
        {% set host_actions %}{% if block('bk_back_link') is defined %}{{ block('bk_back_link') }}{% endif %}{% if block('bk_page_actions') is defined %}{{ block('bk_page_actions') }}{% endif %}{% endset %}
        <title>{% block title %}{% endblock %}</title>
        {% block stylesheets %}{% endblock %}
        <header class="host-header"><h1>{{ block('bk_page_title') }}</h1><p class="host-intro">{% if block('bk_page_intro') is defined %}{{ block('bk_page_intro') }}{% endif %}</p><div class="host-actions">{{ host_actions }}</div></header>
        <main>{% block nowo_breadcrumb_kit_content %}{% endblock %}</main>
        {% block javascripts %}{% endblock %}
        TWIG;

    private const HOST_MODAL_SHELL = <<<'TWIG'
        <dialog class="confirm-dialog" id="{{ modal_id }}" data-size="{{ modal_size }}" aria-labelledby="{{ modal_id }}-label">
            <h2 class="confirm-dialog__title" id="{{ modal_id }}-label">{{ modal_title }}</h2>
            {% if modal_body_id %}
                <div id="{{ modal_body_id }}" class="kit-modal__body"><div class="confirm-dialog__content">{% block bk_modal_body %}<p class="host-loading">…</p>{% endblock %}</div></div>
            {% else %}
                <div class="confirm-dialog__content">{{ block('bk_modal_body') }}</div>
            {% endif %}
            {% block bk_modal_footer %}{% endblock %}
        </dialog>
        TWIG;

    private const HOST_PAGINATION = '<nav class="host-pagination" data-route="{{ route }}" data-params="{{ route_params|merge(query)|json_encode }}" data-total="{{ pagination.total }}" data-count="{{ item_count }}" aria-label="{{ nav_label|trans({}, nav_trans_domain) }}"></nav>';

    /**
     * @var array<string, string>
     */
    private const HOST_CLASSES = [
        'btn_primary' => 'btn-primary',
        'btn_secondary' => 'btn-ghost',
        'btn_danger' => 'btn-danger',
        'btn_link' => 'btn-ghost no-underline',
        'action_view' => 'btn-ghost btn-icon',
        'action_edit' => 'btn-ghost btn-icon',
        'action_delete' => 'btn-danger btn-icon',
        'row_actions' => 'inline-flex gap-1 nowo-ui-row-actions nowo-ui-row-actions--icon',
        'search_form' => 'beacon-toolbar mb-4',
        'search_input' => 'input min-w-56 flex-1',
        'results' => 'host-results',
        'table_wrap' => 'panel overflow-x-auto',
        'breadcrumb_link' => 'host-crumb',
        'breadcrumb_separator' => 'host-sep',
        'table' => 'data-table w-full',
        'th' => 'px-2 py-2',
        'th_actions' => 'px-2 py-2 text-end',
        'td' => 'px-2 py-3',
        'td_actions' => 'px-2 py-3 text-end whitespace-nowrap',
        'row' => 'row-hover',
        'badge' => 'badge',
        'badge_yes' => 'badge badge--ok',
        'badge_no' => 'badge badge--off',
        'empty' => 'panel empty',
        'page_intro' => 'host-item-intro',
        'modal_message' => 'kit-modal__lead',
        'modal_actions' => 'confirm-dialog__actions',
        'modal_form' => 'kit-modal__panel',
        'modal_form_body' => 'confirm-dialog__content',
        'modal_hint' => 'kit-modal__lead',
        'form_style_grid' => 'grid gap-3 sm:grid-cols-2',
        'form_panel' => 'panel max-w-3xl',
        'form_actions' => 'mt-2 flex gap-2',
    ];

    public function testCollectionsIndexWithHostConfigNeedsNoPageFork(): void
    {
        $html = $this->hostRenderer()->render('@NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig', DashboardDefaultOutputTest::collectionIndexContext([
            ['id' => 1, 'code' => 'main', 'name' => 'Main trail', 'contextKey' => null],
        ], ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'total_pages' => 1]));

        self::assertStringContainsString('<title>Collections — Host</title>', $html);
        self::assertMatchesRegularExpression('#<div class="host-actions">\s*<a [^>]*class="btn-ghost"[^>]*>Export all</a>\s*<button type="button" class="btn-ghost btn-bk-import" data-nowo-modal-open data-nowo-modal-target="modal-bk-import"#', $html);
        self::assertStringContainsString('class="btn-primary btn-bk-collection-form"', $html);
        self::assertStringContainsString('<p class="host-intro">Per-collection presentation styles', $html);
        self::assertStringNotContainsString('nowo-ui-toolbar', $html, 'Toolbar actions moved to the layout header.');
        self::assertStringNotContainsString('nowo-ui.css', $html, 'ui_kit_stylesheet: false.');
        self::assertStringContainsString('<form-start name="" options="{&quot;attr&quot;:{&quot;class&quot;:&quot;beacon-toolbar mb-4&quot;', $html);

        // collections_view: table, row_actions_position: end
        self::assertMatchesRegularExpression('#<section class="host-results" data-testid="breadcrumb-kit-collections-table">\s*<div class="panel overflow-x-auto">\s*<table class="data-table w-full">#', $html);
        self::assertMatchesRegularExpression('#<nav class="host-pagination"[^>]*></nav>\s*</section>#', $html, 'Pagination sits inside the results wrapper.');
        self::assertMatchesRegularExpression('#<th class="px-2 py-2">Context</th>\s*<th class="px-2 py-2 text-end">Actions</th>#', $html);
        self::assertMatchesRegularExpression('#<td class="px-2 py-3">—</td>\s*<td class="px-2 py-3 text-end whitespace-nowrap"><div class="inline-flex gap-1 nowo-ui-row-actions nowo-ui-row-actions--icon" aria-label="Actions">#', $html);
        self::assertStringContainsString('<span class="badge">4</span>', $html);

        // action_inner partial (UiKit) replaces the bundle SVG icons; kit JS hooks unchanged
        self::assertStringContainsString('class="btn-danger btn-icon btn-bk-delete" data-id="1"', $html);
        self::assertStringContainsString('data-token="csrf-delete_collection_1"', $html);
        self::assertStringContainsString('<span class="visually-hidden">Delete</span>', $html);
        self::assertStringNotContainsString('viewBox="0 0 16 16"', $html);

        // pagination partial
        self::assertStringContainsString('<nav class="host-pagination" data-route="nowo_breadcrumb_kit_dashboard_collections_index" data-params="{&quot;q&quot;:&quot;ma&quot;}" data-total="1" data-count="1" aria-label="Collections pagination">', $html);

        // modal_shell partial: ids kept, body placeholders from the shell, delete form painted by the class map
        self::assertMatchesRegularExpression('#<dialog class="confirm-dialog" id="modal-bk-collection-form" data-size="collection_form"[^>]*>\s*<h2 class="confirm-dialog__title" id="modal-bk-collection-form-label">Collection</h2>\s*<div id="modal-bk-collection-form-body" class="kit-modal__body"><div class="confirm-dialog__content"><p class="host-loading">#', $html);
        self::assertStringContainsString('id="modal-bk-item-form-body"', $html);
        self::assertStringContainsString('id="modal-bk-import-body"', $html);
        self::assertMatchesRegularExpression('#<div class="confirm-dialog__content">\s*<p id="modal-bk-delete-message" class="kit-modal__lead">Confirm this deletion\?</p>\s*</div>\s*<form-start name="" options="{&quot;attr&quot;:{&quot;id&quot;:&quot;form-bk-delete-confirm&quot;}}">#', $html);
        self::assertMatchesRegularExpression('#<div class="confirm-dialog__actions">\s*<button type="button" class="btn-ghost" data-nowo-modal-close>Cancel</button>\s*<button type="submit" class="btn-danger">Delete</button>#', $html);
        self::assertStringNotContainsString('modal fade', $html);
    }

    public function testItemsIndexWithHostConfig(): void
    {
        $html = $this->hostRenderer()->render('@NowoBreadcrumbKitBundle/dashboard/item/index.html.twig', DashboardDefaultOutputTest::itemIndexContext([
            ['id' => 10, 'routeName' => 'app_home', 'label' => 'Home', 'parent' => null, 'linkEnabled' => true],
            ['id' => 11, 'routeName' => 'app_blog', 'label' => null, 'parent' => null, 'linkEnabled' => false],
        ], '', ['current_page' => 1, 'per_page' => 20, 'total' => 2, 'total_pages' => 1]));

        self::assertStringContainsString('<title>Items · main — Host</title>', $html);
        self::assertMatchesRegularExpression('#<div class="host-actions">\s*<a href="/r/nowo_breadcrumb_kit_dashboard_collections_index" class="btn-ghost no-underline">← Collections</a>\s*<a [^>]*class="btn-ghost"[^>]*>Export collection</a>#u', $html);
        self::assertStringContainsString('<p class="host-item-intro"><code>main</code> — Main trail</p>', $html);
        self::assertStringContainsString('<span class="badge badge--ok">yes</span>', $html);
        self::assertStringContainsString('<span class="badge badge--off">no</span>', $html);
        self::assertMatchesRegularExpression('#<th class="px-2 py-2">Link</th>\s*<th class="px-2 py-2 text-end">Actions</th>#', $html);
        self::assertStringContainsString('data-route="nowo_breadcrumb_kit_dashboard_items_index" data-params="{&quot;collectionId&quot;:3}"', $html);
        self::assertStringNotContainsString('nowo-ui-page-title', $html);
        self::assertStringContainsString('<section class="host-results" data-testid="breadcrumb-kit-items-table">', $html);
    }

    public function testEmptyStatesUseTheClassMap(): void
    {
        $renderer = $this->hostRenderer();

        $collections = $renderer->render('@NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig', DashboardDefaultOutputTest::collectionIndexContext([], null));
        self::assertStringContainsString('<p class="panel empty">No collections yet.', $collections);

        $items = $renderer->render('@NowoBreadcrumbKitBundle/dashboard/item/index.html.twig', DashboardDefaultOutputTest::itemIndexContext([], '', null));
        self::assertMatchesRegularExpression('#<p class="panel empty">\s*No items\.\s*<button type="button" class="btn-ghost no-underline btn-bk-item-form"#', $items);
    }

    public function testModalFormPartialsWithHostConfig(): void
    {
        $renderer = $this->hostRenderer();

        $collection = DashboardTemplateRenderer::normalize($renderer->render('@NowoBreadcrumbKitBundle/dashboard/_collection_form_partial.html.twig', [
            'form' => DashboardTemplateRenderer::form('breadcrumb_collection', ['code', 'classList', 'name', 'classItem', 'responsiveConfigJson']),
            'is_edit' => false,
        ]));
        self::assertSame(<<<'HTML'
            <div id="modal-bk-collection-form-container">
            <form-start name="breadcrumb_collection" options="{&quot;attr&quot;:{&quot;class&quot;:&quot;bk-dashboard-form kit-modal__panel&quot;}}">
            <input type="hidden" name="_modal" value="1">
            <div class="confirm-dialog__content">
            <form-row name="code">
            <form-row name="name">
            <div class="grid gap-3 sm:grid-cols-2">
            <form-row name="classList">
            <form-row name="classItem">
            </div>
            <form-row name="responsiveConfigJson">
            </div>
            <div class="confirm-dialog__actions">
            <button type="button" class="btn-ghost" data-nowo-modal-close>Cancel</button>
            <button type="submit" class="btn-primary">Create</button>
            </div>
            <form-end name="breadcrumb_collection">
            </div>

            HTML, $collection);

        $import = DashboardTemplateRenderer::normalize($renderer->render('@NowoBreadcrumbKitBundle/dashboard/_import_partial.html.twig', [
            'form' => DashboardTemplateRenderer::form('import', ['file']),
        ]));
        self::assertMatchesRegularExpression('#<input type="hidden" name="_modal" value="1">\n<div class="confirm-dialog__content">\n<p class="kit-modal__lead">.*</p>\n<form-row name="file">\n</div>\n<div class="confirm-dialog__actions">\n<button type="button" class="btn-ghost" data-nowo-modal-close>Cancel</button>\n<button type="submit" class="btn-primary">Import</button>#s', $import);
        self::assertStringStartsWith("<div id=\"modal-bk-import-container\">\n<form-start", $import, 'Intro moves inside the form when modal_form_body is set.');
    }

    public function testFullPageFormWithHostConfig(): void
    {
        $html = $this->hostRenderer()->render('@NowoBreadcrumbKitBundle/dashboard/collection/form.html.twig', [
            'form' => DashboardTemplateRenderer::form('breadcrumb_collection', ['code']),
            'collection' => null,
            'title' => 'New collection',
            'dashboard_routes' => DashboardDefaultOutputTest::collectionRoutes(),
        ]);

        self::assertMatchesRegularExpression('#<section class="panel max-w-3xl" data-testid="breadcrumb-kit-collection-form">\s*<form-start name="breadcrumb_collection"#', $html);
        self::assertStringContainsString('class="host-crumb">Collections</a>', $html);
        self::assertStringContainsString('<span class="host-sep"> / </span>', $html);
        self::assertStringContainsString('<title>New collection — Host</title>', $html);
        self::assertStringContainsString('<h1>New collection</h1>', $html, 'Title comes from the layout header.');
        self::assertSame(1, substr_count($html, '<h1'), 'bk_page_title_in_layout skips the page <h1>.');
        self::assertMatchesRegularExpression('#<div class="mt-2 flex gap-2">\s*<button type="submit" class="btn-primary">Create</button>\s*<a [^>]*class="btn-ghost">Cancel</a>#', $html);
    }

    public function testFullPageFormsFollowTheCssFrameworkWhenNotBootstrap(): void
    {
        $html = (new DashboardTemplateRenderer([], ['nowo_ui_kit_css_framework' => 'tailwind', 'nowo_breadcrumb_kit_css_framework' => 'tailwind']))
            ->render('@NowoBreadcrumbKitBundle/dashboard/item/form.html.twig', [
                'form' => DashboardTemplateRenderer::form('breadcrumb_item', ['routeName']),
                'collection' => ['id' => 3, 'code' => 'main'],
                'title' => 'New item',
            ]);

        self::assertStringNotContainsString('btn btn-primary', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--primary inline-flex', $html);
        self::assertStringContainsString('class="nowo-ui-row-actions flex gap-1"', $html);
    }

    public function testBangNamespaceLetsAnAppOverrideExtendTheOriginalPage(): void
    {
        $override = <<<'TWIG'
            {% extends '@!NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig' %}
            {% block bk_empty_state %}<p class="host-empty">{{ 'dashboard.empty_collections'|trans({}, 'NowoBreadcrumbKitBundle') }}</p>{% endblock %}
            TWIG;
        $renderer = new DashboardTemplateRenderer(['@NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig' => $override]);

        $html = $renderer->render('@NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig', DashboardDefaultOutputTest::collectionIndexContext([], null));

        self::assertStringContainsString('<p class="host-empty">No collections yet.', $html);
        self::assertStringContainsString('nowo-ui-toolbar', $html, 'Everything else still comes from the bundle page.');
        self::assertStringNotContainsString('nowo-ui-empty', $html);
    }

    public function testCancelFirstWithoutClassMapKeepsDefaultClasses(): void
    {
        $html = DashboardTemplateRenderer::normalize((new DashboardTemplateRenderer([], ['nowo_breadcrumb_kit_cancel_first' => true]))
            ->render('@NowoBreadcrumbKitBundle/dashboard/_item_form_partial.html.twig', ['form' => DashboardTemplateRenderer::form('breadcrumb_item', ['routeName'])]));

        self::assertMatchesRegularExpression('#style="justify-content:flex-start;margin-top:1rem;">\n<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>\n<button type="submit"#', $html);
    }

    private function hostRenderer(): DashboardTemplateRenderer
    {
        return new DashboardTemplateRenderer([
            'host/layout.html.twig' => self::HOST_LAYOUT,
            'host/_modal_shell.html.twig' => self::HOST_MODAL_SHELL,
            'host/_pagination.html.twig' => self::HOST_PAGINATION,
        ], [
            'nowo_breadcrumb_kit_layout_template' => 'host/layout.html.twig',
            'nowo_breadcrumb_kit_css_framework' => 'tailwind',
            'nowo_ui_kit_css_framework' => 'tailwind',
            'nowo_ui_kit_row_actions_display' => 'icon',
            'nowo_breadcrumb_kit_classes' => self::HOST_CLASSES,
            'nowo_breadcrumb_kit_partials' => [
                'modal_shell' => 'host/_modal_shell.html.twig',
                'pagination' => 'host/_pagination.html.twig',
                'action_inner' => '@NowoUiKitBundle/partials/_action_inner.html.twig',
            ],
            'nowo_breadcrumb_kit_ui_kit_stylesheet' => false,
            'nowo_breadcrumb_kit_collections_view' => 'table',
            'nowo_breadcrumb_kit_row_actions_position' => 'end',
            'nowo_breadcrumb_kit_cancel_first' => true,
        ]);
    }
}
