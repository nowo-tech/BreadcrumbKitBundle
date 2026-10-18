<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Dashboard;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FilterField;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Builds the dashboard pages on the UiKit page contract (REQ-UI-003): the view models of every page, the per-page
 * template ({@code web_ui.templates.<page>}) and the layout override for the view models ({@code
 * web_ui.layout_template}; null = {@code nowo_ui_layout.admin}; a 1.x layout that only defines
 * {@code nowo_breadcrumb_kit_content} is wrapped by {@see self::LEGACY_LAYOUT_BRIDGE}).
 *
 * Stateless and request-agnostic (FrankenPHP worker safe): every input comes in as an argument.
 */
final readonly class DashboardPages
{
    public const BUNDLE = NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN;

    /**
     * Page id => default template (override with {@code web_ui.templates.<page>}).
     *
     * @var array<string, string>
     */
    public const TEMPLATES = [
        'collections_index' => '@NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig',
        'collection_form' => '@NowoBreadcrumbKitBundle/dashboard/collection/form.html.twig',
        'collection_delete' => '@NowoBreadcrumbKitBundle/dashboard/collection/delete.html.twig',
        'items_index' => '@NowoBreadcrumbKitBundle/dashboard/item/index.html.twig',
        'item_form' => '@NowoBreadcrumbKitBundle/dashboard/item/form.html.twig',
        'item_delete' => '@NowoBreadcrumbKitBundle/dashboard/item/delete.html.twig',
        'import' => '@NowoBreadcrumbKitBundle/dashboard/import.html.twig',
    ];

    /** Bridge used when {@code web_ui.layout_template} does not render the UiKit canonical blocks (deprecated since 2.7). */
    public const LEGACY_LAYOUT_BRIDGE = '@NowoBreadcrumbKitBundle/dashboard/_legacy_layout.html.twig';

    /** @var list<string> */
    public const DIALOG_SIZES = ['sm', 'md', 'lg', 'xl'];

    public const DIALOG_COLLECTION_FORM = 'bk-collection-form-dialog';
    public const DIALOG_ITEM_FORM = 'bk-item-form-dialog';
    public const DIALOG_IMPORT = 'bk-import-dialog';

    /**
     * Breadcrumbs of the dashboard page headers (UiKit 2.2 {@see PageHeader::$breadcrumbsMode} `auto`): the
     * BreadcrumbKit trail the host configured for the dashboard routes when there is one, else the dashboard's own
     * trail (Collections › code › page) — UiKit 2.2 falls back to it when the kit trail is empty, so every page keeps a
     * way back and the "←" header actions of 2.7 are gone.
     */
    public const BREADCRUMBS_MODE = 'auto';

    public const ROUTE_COLLECTIONS = 'nowo_breadcrumb_kit_dashboard_collections_index';
    public const ROUTE_COLLECTION_NEW = 'nowo_breadcrumb_kit_dashboard_collections_new';
    public const ROUTE_COLLECTION_EDIT = 'nowo_breadcrumb_kit_dashboard_collections_edit';
    public const ROUTE_COLLECTION_DELETE = 'nowo_breadcrumb_kit_dashboard_collections_delete';
    public const ROUTE_COLLECTION_EXPORT = 'nowo_breadcrumb_kit_dashboard_collections_export';
    public const ROUTE_ITEMS = 'nowo_breadcrumb_kit_dashboard_items_index';
    public const ROUTE_ITEM_NEW = 'nowo_breadcrumb_kit_dashboard_items_new';
    public const ROUTE_ITEM_EDIT = 'nowo_breadcrumb_kit_dashboard_items_edit';
    public const ROUTE_ITEM_DELETE = 'nowo_breadcrumb_kit_dashboard_items_delete';
    public const ROUTE_IMPORT = 'nowo_breadcrumb_kit_dashboard_import';
    public const ROUTE_EXPORT_ALL = 'nowo_breadcrumb_kit_dashboard_export_all';

    /** @var array{collection_form: string, item_form: string, import: string} */
    private array $dialogSizes;

    /**
     * @param array<string, string> $templates   web_ui.templates (page id => template)
     * @param array<string, string> $dialogSizes web_ui.dialog_sizes
     */
    public function __construct(
        private UrlGeneratorInterface $urls,
        private Environment $twig,
        private ?string $layoutTemplate = null,
        private array $templates = [],
        array $dialogSizes = [],
    ) {
        $size = static fn (string $key, string $default): string => \in_array($dialogSizes[$key] ?? null, self::DIALOG_SIZES, true) ? $dialogSizes[$key] : $default;
        $this->dialogSizes = [
            'collection_form' => $size('collection_form', 'lg'),
            'item_form' => $size('item_form', 'lg'),
            'import' => $size('import', 'md'),
        ];
    }

    /**
     * Translation key of the bundle domain, translated by the UiKit scaffolds.
     *
     * @param array<string, mixed> $params
     */
    public static function label(string $key, array $params = []): Label
    {
        return new Label($key, self::BUNDLE, $params);
    }

    /**
     * The single pagination shape of the dashboard lists (null when pagination is off).
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $routeParams
     */
    public static function pagination(bool $enabled, int $page, int $perPage, int $total, string $route, array $query = [], array $routeParams = []): ?Pagination
    {
        return $enabled ? new Pagination($page, $perPage, $total, route: $route, query: $query, routeParams: $routeParams) : null;
    }

    public function template(string $page): string
    {
        if (!\array_key_exists($page, self::TEMPLATES)) {
            throw new \InvalidArgumentException(\sprintf('Unknown BreadcrumbKit dashboard page "%s" (%s).', $page, implode(', ', array_keys(self::TEMPLATES))));
        }

        return $this->templates[$page] ?? self::TEMPLATES[$page];
    }

    /**
     * The view-model layout: null (UiKit admin layout), the configured layout when it renders the canonical blocks,
     * or the legacy bridge around a 1.x layout.
     */
    public function layout(): ?string
    {
        if (null === $this->layoutTemplate) {
            return null;
        }

        return $this->isLegacyLayout() ? self::LEGACY_LAYOUT_BRIDGE : $this->layoutTemplate;
    }

    /**
     * Dialog size of a dashboard dialog ({@code collection_form}, {@code item_form}, {@code import}).
     */
    public function dialogSize(string $dialog): string
    {
        return $this->dialogSizes[$dialog] ?? 'md';
    }

    /**
     * @param array<string, mixed> $context page context (at least `page`, the view model)
     */
    public function render(string $page, array $context, int $status = Response::HTTP_OK): Response
    {
        $legacy = null !== $this->layoutTemplate && $this->isLegacyLayout();
        if ($legacy) {
            trigger_deprecation('nowo-tech/breadcrumb-kit-bundle', '2.7', 'The layout "%s" (nowo_breadcrumb_kit.web_ui.layout_template) does not render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content…); it is wrapped in "%s". Render the canonical blocks in your layout (see docs/UPGRADING.md) or set web_ui.layout_template to null.', (string) $this->layoutTemplate, self::LEGACY_LAYOUT_BRIDGE);
        }

        return new Response($this->twig->render($this->template($page), $context + [
            'nowo_breadcrumb_kit_legacy_layout' => $legacy ? $this->layoutTemplate : null,
            'nowo_breadcrumb_kit_dialog_sizes' => $this->dialogSizes,
        ]), $status);
    }

    /**
     * Collections index (list scaffold).
     *
     * @param iterable<BreadcrumbCollection> $collections
     * @param array<int, int>                $itemCounts  collection id => number of items
     * @param callable(int): FormView        $deleteForm  CSRF-only delete form of one collection
     */
    public function collectionsIndex(iterable $collections, array $itemCounts, FormView $filters, string $search, ?Pagination $pagination, callable $deleteForm): ListPage
    {
        $rows = [];
        foreach ($collections as $collection) {
            $id = (int) $collection->getId();
            $rows[] = [
                'id' => $id,
                'code' => $collection->getCode(),
                'name' => $collection->getName(),
                'contextKey' => $collection->getContextKey(),
                'items' => $itemCounts[$id] ?? 0,
            ];
        }

        $newUrl = $this->url(self::ROUTE_COLLECTION_NEW);
        $header = new PageHeader(self::label('dashboard.collections_heading'), self::label('dashboard.collections_lead'), [
            new Action('export', self::label('dashboard.export_all'), $this->url(self::ROUTE_EXPORT_ALL), attributes: ['data-testid' => 'bk-export-all']),
            new Action('import', self::label('dashboard.import'), $this->url(self::ROUTE_IMPORT), attributes: [
                'data-nowo-ui-dialog-open' => self::DIALOG_IMPORT,
                'aria-haspopup' => 'dialog',
                'data-testid' => 'bk-import-open',
            ]),
            new Action('create', self::label('dashboard.new_collection'), $newUrl, variant: 'primary', attributes: ['data-testid' => 'bk-collection-new'], dialogId: self::DIALOG_COLLECTION_FORM),
        ], [new Crumb(self::label('dashboard.collections_heading'))], breadcrumbsMode: self::BREADCRUMBS_MODE);

        return new ListPage(
            $header,
            [
                new Column('code', self::label('dashboard.table_code'), required: true),
                new Column('name', self::label('dashboard.table_name')),
                new Column('items', self::label('dashboard.table_items'), align: 'end', priority: 2),
                new Column('contextKey', self::label('dashboard.table_context'), priority: 3),
            ],
            $rows,
            $filters,
            function (array $row) use ($deleteForm): array {
                $id = (int) $row['id'];
                $editUrl = $this->url(self::ROUTE_COLLECTION_EDIT, ['id' => $id]);
                $deleteUrl = $this->url(self::ROUTE_COLLECTION_DELETE, ['id' => $id]);

                return [
                    Action::view($this->url(self::ROUTE_ITEMS, ['collectionId' => $id]), self::label('dashboard.link_items')),
                    new Action('export', self::label('dashboard.link_export_json'), $this->url(self::ROUTE_COLLECTION_EXPORT, ['id' => $id])),
                    new Action('edit', self::label('dashboard.link_edit'), $editUrl, dialogId: self::DIALOG_COLLECTION_FORM),
                    new Action('delete', self::label('dashboard.delete'), $deleteUrl, method: 'POST', confirm: $this->collectionDeleteConfirm((string) $row['code']), form: $deleteForm($id), fallbackHref: $deleteUrl),
                ];
            },
            $pagination,
            new EmptyState(message: self::label('' !== $search ? 'dashboard.empty_collections_search' : 'dashboard.empty_collections')),
            '' !== $search ? $this->url(self::ROUTE_COLLECTIONS) : null,
            bundle: self::BUNDLE,
            layout: $this->layout(),
            attributes: ['data-testid' => 'breadcrumb-kit-collections-table'],
            actionsLabel: self::label('dashboard.table_actions'),
            filterFields: [new FilterField('q', self::label('dashboard.search_placeholder'), 'lg', 'nowo-ui-input')],
            filtersSubmit: new Action('filter', self::label('dashboard.filter')),
            filtersClear: new Action('cancel', self::label('dashboard.clear_filters'), $this->url(self::ROUTE_COLLECTIONS)),
        );
    }

    /**
     * Collection create / edit page (form scaffold). Edit pages add the items / export links and the danger zone.
     */
    public function collectionForm(FormView $form, ?BreadcrumbCollection $collection, ?FormView $deleteForm = null): FormPage
    {
        $id = $collection?->getId();
        $indexUrl = $this->url(self::ROUTE_COLLECTIONS);
        $crumbs = [new Crumb(self::label('dashboard.collections_heading'), $indexUrl)];
        $secondary = [];
        $delete = null;
        if (null !== $collection && null !== $id) {
            $title = self::label('dashboard.edit_collection_modal_title', ['%code%' => $collection->getCode()]);
            $crumbs[] = new Crumb($collection->getCode(), $this->url(self::ROUTE_ITEMS, ['collectionId' => $id]));
            $secondary = [
                Action::view($this->url(self::ROUTE_ITEMS, ['collectionId' => $id]), self::label('dashboard.link_items')),
                new Action('export', self::label('dashboard.export_collection'), $this->url(self::ROUTE_COLLECTION_EXPORT, ['id' => $id])),
            ];
            if (null !== $deleteForm) {
                $deleteUrl = $this->url(self::ROUTE_COLLECTION_DELETE, ['id' => $id]);
                $delete = new Action('delete', self::label('dashboard.delete'), $deleteUrl, method: 'POST', confirm: $this->collectionDeleteConfirm($collection->getCode()), form: $deleteForm, fallbackHref: $deleteUrl);
            }
        } else {
            $title = self::label('dashboard.new_collection_modal_title');
        }
        $crumbs[] = new Crumb($title);

        return new FormPage(
            new PageHeader($title, null, [], $crumbs, breadcrumbsMode: self::BREADCRUMBS_MODE),
            $form,
            self::label(null !== $id ? 'dashboard.form_update' : 'dashboard.form_create'),
            $indexUrl,
            self::label('dashboard.cancel'),
            $secondary,
            $delete,
            self::BUNDLE,
            $this->layout(),
            ['data-testid' => 'breadcrumb-kit-collection-form'],
        );
    }

    /**
     * No-JS confirmation of a collection delete (confirm scaffold): GET of the delete route.
     */
    public function collectionDelete(BreadcrumbCollection $collection, FormView $deleteForm): ConfirmPage
    {
        $id = (int) $collection->getId();

        return new ConfirmPage(
            new PageHeader(self::label('dashboard.confirm_delete'), null, [], [
                new Crumb(self::label('dashboard.collections_heading'), $this->url(self::ROUTE_COLLECTIONS)),
                new Crumb($collection->getCode()),
            ], breadcrumbsMode: self::BREADCRUMBS_MODE),
            $this->collectionDeleteConfirm($collection->getCode()),
            new Action('delete', self::label('dashboard.delete'), $this->url(self::ROUTE_COLLECTION_DELETE, ['id' => $id]), method: 'POST', form: $deleteForm),
            $this->url(self::ROUTE_COLLECTIONS),
            self::BUNDLE,
            $this->layout(),
            ['data-testid' => 'breadcrumb-kit-collection-delete'],
        );
    }

    /**
     * Items of one collection (list scaffold).
     *
     * @param iterable<BreadcrumbItem> $items
     * @param callable(int): FormView  $deleteForm CSRF-only delete form of one item
     */
    public function itemsIndex(BreadcrumbCollection $collection, iterable $items, FormView $filters, string $search, ?Pagination $pagination, callable $deleteForm): ListPage
    {
        $collectionId = (int) $collection->getId();
        $code = $collection->getCode();
        $itemsUrl = $this->url(self::ROUTE_ITEMS, ['collectionId' => $collectionId]);
        $editUrl = $this->url(self::ROUTE_COLLECTION_EDIT, ['id' => $collectionId]);
        $newUrl = $this->url(self::ROUTE_ITEM_NEW, ['collectionId' => $collectionId]);
        $name = $collection->getName();

        $header = new PageHeader(
            self::label('dashboard.items_title_with_code', ['%code%' => $code]),
            null !== $name && '' !== $name ? $name : null,
            [
                new Action('export', self::label('dashboard.export_collection'), $this->url(self::ROUTE_COLLECTION_EXPORT, ['id' => $collectionId])),
                new Action('config', self::label('dashboard.collection_styles'), $editUrl, attributes: ['data-testid' => 'bk-collection-styles'], dialogId: self::DIALOG_COLLECTION_FORM),
                new Action('create', self::label('dashboard.new_item'), $newUrl, variant: 'primary', attributes: ['data-testid' => 'bk-item-new'], dialogId: self::DIALOG_ITEM_FORM),
            ],
            [new Crumb(self::label('dashboard.collections_heading'), $this->url(self::ROUTE_COLLECTIONS)), new Crumb($code)],
            breadcrumbsMode: self::BREADCRUMBS_MODE,
        );

        $empty = '' !== $search
            ? new EmptyState(message: self::label('dashboard.empty_items_search'), action: new Action('view', self::label('dashboard.view_all_link'), $itemsUrl, variant: 'link'))
            : new EmptyState(message: self::label('dashboard.empty_items_none'), action: new Action('create', self::label('dashboard.add_item_button'), $newUrl, variant: 'primary', dialogId: self::DIALOG_ITEM_FORM));

        return new ListPage(
            $header,
            [
                new Column('routeName', self::label('dashboard.table_route'), required: true),
                new Column('label', self::label('dashboard.table_label')),
                new Column('parent', self::label('dashboard.table_parent'), priority: 3),
                new Column('linkEnabled', self::label('dashboard.table_link'), priority: 2),
            ],
            $items,
            $filters,
            function (BreadcrumbItem $item) use ($collectionId, $deleteForm): array {
                $id = (int) $item->getId();
                $editUrl = $this->url(self::ROUTE_ITEM_EDIT, ['collectionId' => $collectionId, 'id' => $id]);
                $deleteUrl = $this->url(self::ROUTE_ITEM_DELETE, ['collectionId' => $collectionId, 'id' => $id]);

                return [
                    new Action('filter', self::label('dashboard.filter_by_route'), $this->url(self::ROUTE_ITEMS, ['collectionId' => $collectionId, 'q' => $item->getRouteName()])),
                    new Action('edit', self::label('dashboard.link_edit'), $editUrl, dialogId: self::DIALOG_ITEM_FORM),
                    new Action('delete', self::label('dashboard.delete'), $deleteUrl, method: 'POST', confirm: $this->itemDeleteConfirm($item), form: $deleteForm($id), fallbackHref: $deleteUrl),
                ];
            },
            $pagination,
            $empty,
            '' !== $search ? $itemsUrl : null,
            caption: '' !== $search ? self::label('dashboard.search_results', ['%count%' => $pagination->total ?? (is_countable($items) ? \count($items) : 0), '%query%' => $search]) : null,
            bundle: self::BUNDLE,
            layout: $this->layout(),
            attributes: ['data-testid' => 'breadcrumb-kit-items-table'],
            actionsLabel: self::label('dashboard.table_actions'),
            filterFields: [new FilterField('q', self::label('dashboard.search_placeholder'), 'lg', 'nowo-ui-input')],
            filtersSubmit: new Action('filter', self::label('dashboard.filter')),
            filtersClear: new Action('cancel', self::label('dashboard.clear_filters'), $itemsUrl),
        );
    }

    /**
     * Item create / edit page (form scaffold); edit pages add the danger zone.
     */
    public function itemForm(FormView $form, BreadcrumbCollection $collection, ?BreadcrumbItem $item, ?FormView $deleteForm = null): FormPage
    {
        $collectionId = (int) $collection->getId();
        $itemsUrl = $this->url(self::ROUTE_ITEMS, ['collectionId' => $collectionId]);
        $id = $item?->getId();
        $title = null !== $id
            ? self::label('dashboard.edit_item_modal_title', ['%id%' => (string) $id])
            : self::label('dashboard.item_new_page_title');
        $delete = null;
        if (null !== $item && null !== $id && null !== $deleteForm) {
            $deleteUrl = $this->url(self::ROUTE_ITEM_DELETE, ['collectionId' => $collectionId, 'id' => $id]);
            $delete = new Action('delete', self::label('dashboard.delete'), $deleteUrl, method: 'POST', confirm: $this->itemDeleteConfirm($item), form: $deleteForm, fallbackHref: $deleteUrl);
        }

        return new FormPage(
            new PageHeader($title, null, [], breadcrumbsMode: self::BREADCRUMBS_MODE, breadcrumbs: [
                new Crumb(self::label('dashboard.collections_heading'), $this->url(self::ROUTE_COLLECTIONS)),
                new Crumb($collection->getCode(), $itemsUrl),
                new Crumb($title),
            ]),
            $form,
            self::label(null !== $id ? 'dashboard.form_update' : 'dashboard.form_create'),
            $itemsUrl,
            self::label('dashboard.cancel'),
            [],
            $delete,
            self::BUNDLE,
            $this->layout(),
            ['data-testid' => 'breadcrumb-kit-item-form'],
        );
    }

    /**
     * No-JS confirmation of an item delete (confirm scaffold): GET of the delete route.
     */
    public function itemDelete(BreadcrumbCollection $collection, BreadcrumbItem $item, FormView $deleteForm): ConfirmPage
    {
        $collectionId = (int) $collection->getId();
        $itemsUrl = $this->url(self::ROUTE_ITEMS, ['collectionId' => $collectionId]);

        return new ConfirmPage(
            new PageHeader(self::label('dashboard.confirm_delete'), null, [], [
                new Crumb(self::label('dashboard.collections_heading'), $this->url(self::ROUTE_COLLECTIONS)),
                new Crumb($collection->getCode(), $itemsUrl),
                new Crumb('#'.(string) $item->getId()),
            ], breadcrumbsMode: self::BREADCRUMBS_MODE),
            $this->itemDeleteConfirm($item),
            new Action('delete', self::label('dashboard.delete'), $this->url(self::ROUTE_ITEM_DELETE, ['collectionId' => $collectionId, 'id' => (int) $item->getId()]), method: 'POST', form: $deleteForm),
            $itemsUrl,
            self::BUNDLE,
            $this->layout(),
            ['data-testid' => 'breadcrumb-kit-item-delete'],
        );
    }

    /**
     * Full-page JSON import (form scaffold; the collections index opens the same form in a dialog).
     */
    public function import(FormView $form): FormPage
    {
        $indexUrl = $this->url(self::ROUTE_COLLECTIONS);

        return new FormPage(
            new PageHeader(self::label('dashboard.import_page_title'), null, [], breadcrumbsMode: self::BREADCRUMBS_MODE, breadcrumbs: [
                new Crumb(self::label('dashboard.collections_heading'), $indexUrl),
                new Crumb(self::label('dashboard.import')),
            ]),
            $form,
            self::label('dashboard.import_submit'),
            $indexUrl,
            self::label('dashboard.cancel'),
            bundle: self::BUNDLE,
            layout: $this->layout(),
            attributes: ['data-testid' => 'breadcrumb-kit-import-form'],
        );
    }

    /**
     * Attributes of an action that opens a dashboard dialog and loads its form ({@code ?_partial=1}) into it
     * (js/dashboard.js); without JavaScript the link opens the full-page form.
     *
     * @deprecated since 2.8, the dashboard actions use the UiKit 2.2 remote dialog ({@see Action::$dialogId} with the
     *             form URL: nowo-ui-dialog.js fetches it with X-Requested-With, the controllers answer with the form
     *             partial); kept for host templates that still print these attributes (js/dashboard.js loads them)
     *
     * @return array<string, string>
     */
    public static function dialogOpener(string $dialog, string $url, ?string $testId = null): array
    {
        $attributes = [
            'data-nowo-ui-dialog-open' => $dialog,
            'data-bk-dialog-url' => $url.(str_contains($url, '?') ? '&' : '?').'_partial=1',
            'aria-haspopup' => 'dialog',
        ];
        if (null !== $testId) {
            $attributes['data-testid'] = $testId;
        }

        return $attributes;
    }

    private function collectionDeleteConfirm(string $code): Confirm
    {
        return new Confirm(self::label('dashboard.confirm_delete'), self::label('dashboard.delete_collection_confirm', ['%code%' => $code]), self::label('dashboard.delete'));
    }

    private function itemDeleteConfirm(BreadcrumbItem $item): Confirm
    {
        return new Confirm(self::label('dashboard.confirm_delete'), self::label('dashboard.delete_item_confirm', ['%id%' => (string) $item->getId(), '%route%' => $item->getRouteName()]), self::label('dashboard.delete'));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function url(string $route, array $params = []): string
    {
        return $this->urls->generate($route, $params);
    }

    private function isLegacyLayout(): bool
    {
        return !$this->twig->load((string) $this->layoutTemplate)->hasBlock('nowo_ui_page_header');
    }
}
