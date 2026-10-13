<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Templates;

use Nowo\BreadcrumbKitBundle\Tests\Support\DashboardTemplateRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BC guard for the admin chrome hooks (spec 003): with no `dashboard.classes` / `dashboard.partials`
 * configured, every dashboard page and partial renders the same markup as v2.3.x (whitespace aside).
 *
 * Snapshots live in tests/fixtures/templates/. Regenerate on an intentional change with
 * `UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --filter DashboardDefaultOutputTest`.
 */
final class DashboardDefaultOutputTest extends TestCase
{
    #[DataProvider('cases')]
    public function testDefaultOutputMatchesSnapshot(string $snapshot, string $template, \Closure $context): void
    {
        $html = DashboardTemplateRenderer::normalize((new DashboardTemplateRenderer())->render($template, $context()));
        $file = \dirname(__DIR__, 2).'/fixtures/templates/'.$snapshot.'.html';

        if ('1' === getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($file, $html);
        }
        self::assertFileExists($file, 'Missing snapshot; run with UPDATE_SNAPSHOTS=1 once.');

        self::assertSame((string) file_get_contents($file), $html);
    }

    /**
     * @return iterable<string, array{string, string, \Closure(): array<string, mixed>}>
     */
    public static function cases(): iterable
    {
        yield 'collections index (empty)' => ['collection_index_empty', '@NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig', static fn (): array => self::collectionIndexContext([], null)];
        yield 'collections index (rows + pagination)' => ['collection_index_rows', '@NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig', static fn (): array => self::collectionIndexContext([
            ['id' => 1, 'code' => 'main', 'name' => 'Main trail', 'contextKey' => null],
            ['id' => 2, 'code' => 'shop', 'name' => null, 'contextKey' => 'store'],
        ], ['current_page' => 2, 'per_page' => 2, 'total' => 5, 'total_pages' => 3])];
        yield 'items index (empty)' => ['item_index_empty', '@NowoBreadcrumbKitBundle/dashboard/item/index.html.twig', static fn (): array => self::itemIndexContext([], '', null)];
        yield 'items index (empty search)' => ['item_index_empty_search', '@NowoBreadcrumbKitBundle/dashboard/item/index.html.twig', static fn (): array => self::itemIndexContext([], 'blog', null)];
        yield 'items index (rows + pagination)' => ['item_index_rows', '@NowoBreadcrumbKitBundle/dashboard/item/index.html.twig', static fn (): array => self::itemIndexContext([
            ['id' => 10, 'routeName' => 'app_home', 'label' => 'Home', 'parent' => null, 'linkEnabled' => true],
            ['id' => 11, 'routeName' => 'app_blog', 'label' => null, 'parent' => ['id' => 10, 'routeName' => 'app_home'], 'linkEnabled' => false],
        ], 'app', ['current_page' => 1, 'per_page' => 2, 'total' => 3, 'total_pages' => 2])];
        yield 'collection form (new)' => ['collection_form_new', '@NowoBreadcrumbKitBundle/dashboard/collection/form.html.twig', static fn (): array => [
            'form' => DashboardTemplateRenderer::form('breadcrumb_collection', ['code', 'name']),
            'collection' => null,
            'title' => 'New collection',
            'dashboard_routes' => self::collectionRoutes(),
        ]];
        yield 'collection form (edit)' => ['collection_form_edit', '@NowoBreadcrumbKitBundle/dashboard/collection/form.html.twig', static fn (): array => [
            'form' => DashboardTemplateRenderer::form('breadcrumb_collection', ['code', 'name']),
            'collection' => ['id' => 3, 'code' => 'main'],
            'title' => 'Edit collection',
            'dashboard_routes' => self::collectionRoutes(),
        ]];
        yield 'item form' => ['item_form', '@NowoBreadcrumbKitBundle/dashboard/item/form.html.twig', static fn (): array => [
            'form' => DashboardTemplateRenderer::form('breadcrumb_item', ['routeName', 'label']),
            'collection' => ['id' => 3, 'code' => 'main'],
            'item' => ['id' => 9],
            'title' => 'Edit item',
        ]];
        yield 'import page' => ['import_page', '@NowoBreadcrumbKitBundle/dashboard/import.html.twig', static fn (): array => [
            'form' => DashboardTemplateRenderer::form('import', ['file', 'mode']),
        ]];
        yield 'collection form partial' => ['collection_form_partial', '@NowoBreadcrumbKitBundle/dashboard/_collection_form_partial.html.twig', static fn (): array => [
            'form' => DashboardTemplateRenderer::form('breadcrumb_collection', ['code', 'contextKey', 'name', 'classList']),
            'is_edit' => true,
        ]];
        yield 'item form partial' => ['item_form_partial', '@NowoBreadcrumbKitBundle/dashboard/_item_form_partial.html.twig', static fn (): array => [
            'form' => DashboardTemplateRenderer::form('breadcrumb_item', ['routeName', 'label']),
        ]];
        yield 'import partial' => ['import_partial', '@NowoBreadcrumbKitBundle/dashboard/_import_partial.html.twig', static fn (): array => [
            'form' => DashboardTemplateRenderer::form('import', ['file', 'mode']),
        ]];
        yield 'modals (with delete)' => ['modals_with_delete', '@NowoBreadcrumbKitBundle/dashboard/_dashboard_modals.html.twig', static fn (): array => [
            'delete_form' => DashboardTemplateRenderer::form('', ['_token']),
            'modal_classes' => ['collection_form' => 'modal-lg', 'item_form' => 'modal-xl', 'import' => '', 'delete' => ''],
        ]];
        yield 'modals (no delete)' => ['modals_without_delete', '@NowoBreadcrumbKitBundle/dashboard/_dashboard_modals.html.twig', static fn (): array => []];
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param array<string, int>|null    $pagination
     *
     * @return array<string, mixed>
     */
    public static function collectionIndexContext(array $collections, ?array $pagination): array
    {
        $counts = [];
        foreach ($collections as $c) {
            $counts[$c['id']] = 4;
        }

        return [
            'collections' => $collections,
            'collection_item_counts' => $counts,
            'search_form' => DashboardTemplateRenderer::form('', ['q']),
            'search' => null === $pagination ? '' : 'ma',
            'pagination' => $pagination,
            'dashboard_routes' => self::collectionRoutes(),
            'delete_form' => DashboardTemplateRenderer::form('', ['_token']),
            'modal_classes' => ['collection_form' => 'modal-lg', 'item_form' => 'modal-lg', 'import' => '', 'delete' => ''],
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, int>|null    $pagination
     *
     * @return array<string, mixed>
     */
    public static function itemIndexContext(array $items, string $search, ?array $pagination): array
    {
        return [
            'collection' => ['id' => 3, 'code' => 'main', 'name' => 'Main trail'],
            'items' => $items,
            'search_form' => DashboardTemplateRenderer::form('', ['q']),
            'search_query' => $search,
            'pagination' => $pagination,
            'dashboard_routes' => [
                'collections_index' => 'nowo_breadcrumb_kit_dashboard_collections_index',
                'collections_edit' => 'nowo_breadcrumb_kit_dashboard_collections_edit',
                'export' => 'nowo_breadcrumb_kit_dashboard_collections_export',
            ],
            'delete_form' => DashboardTemplateRenderer::form('', ['_token']),
            'modal_classes' => ['collection_form' => 'modal-lg', 'item_form' => 'modal-lg', 'import' => '', 'delete' => ''],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function collectionRoutes(): array
    {
        return [
            'index' => 'nowo_breadcrumb_kit_dashboard_collections_index',
            'new' => 'nowo_breadcrumb_kit_dashboard_collections_new',
            'edit' => 'nowo_breadcrumb_kit_dashboard_collections_edit',
            'delete' => 'nowo_breadcrumb_kit_dashboard_collections_delete',
            'export' => 'nowo_breadcrumb_kit_dashboard_collections_export',
            'import' => 'nowo_breadcrumb_kit_dashboard_import',
            'export_all' => 'nowo_breadcrumb_kit_dashboard_export_all',
        ];
    }
}
