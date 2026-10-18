<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Dashboard;

use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\Tests\Support\DashboardPageFixtures;
use Nowo\UiKitBundle\View\ListPage;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class DashboardPagesTest extends TestCase
{
    public function testTemplatesCanBeOverriddenPerPage(): void
    {
        $pages = new DashboardPages(DashboardPageFixtures::urls(), new Environment(new ArrayLoader()), null, ['import' => 'admin/import.html.twig']);

        self::assertSame('admin/import.html.twig', $pages->template('import'));
        self::assertSame(DashboardPages::TEMPLATES['items_index'], $pages->template('items_index'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown BreadcrumbKit dashboard page "edit"');
        $pages->template('edit');
    }

    public function testLayoutIsNullTheCanonicalLayoutOrTheLegacyBridge(): void
    {
        $twig = new Environment(new ArrayLoader([
            'canonical.html.twig' => '{% block nowo_ui_page_header %}{% endblock %}{% block nowo_ui_content %}{% endblock %}',
            'legacy.html.twig' => '{% block nowo_breadcrumb_kit_content %}{% endblock %}',
        ]));

        self::assertNull((new DashboardPages(DashboardPageFixtures::urls(), $twig))->layout());
        self::assertSame('canonical.html.twig', (new DashboardPages(DashboardPageFixtures::urls(), $twig, 'canonical.html.twig'))->layout());
        self::assertSame(DashboardPages::LEGACY_LAYOUT_BRIDGE, (new DashboardPages(DashboardPageFixtures::urls(), $twig, 'legacy.html.twig'))->layout());
    }

    public function testRenderPassesTheSharedContext(): void
    {
        $twig = new Environment(new ArrayLoader(['page.html.twig' => '{{ nowo_breadcrumb_kit_legacy_layout is null ? "canonical" : "legacy" }}|{{ nowo_breadcrumb_kit_dialog_sizes|join(",") }}|{{ page }}']));
        $pages = new DashboardPages(DashboardPageFixtures::urls(), $twig, null, ['import' => 'page.html.twig'], ['collection_form' => 'xl', 'item_form' => 'huge']);

        $response = $pages->render('import', ['page' => 'x'], 422);

        self::assertSame('canonical|xl,lg,md|x', $response->getContent());
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('xl', $pages->dialogSize('collection_form'));
        self::assertSame('lg', $pages->dialogSize('item_form'), 'Invalid sizes fall back to the default.');
        self::assertSame('md', $pages->dialogSize('unknown'));
    }

    #[IgnoreDeprecations]
    public function testRenderWrapsALegacyLayoutWithADeprecation(): void
    {
        $twig = new Environment(new ArrayLoader([
            'legacy.html.twig' => '{% block nowo_breadcrumb_kit_content %}{% endblock %}',
            'page.html.twig' => '{{ nowo_breadcrumb_kit_legacy_layout }}',
        ]));
        $pages = new DashboardPages(DashboardPageFixtures::urls(), $twig, 'legacy.html.twig', ['import' => 'page.html.twig']);

        $this->expectUserDeprecationMessageMatches('/"legacy\.html\.twig" \(nowo_breadcrumb_kit\.web_ui\.layout_template\) does not render the UiKit canonical blocks/');
        self::assertSame('legacy.html.twig', $pages->render('import', [])->getContent());
    }

    public function testPaginationIsNullWhenDisabled(): void
    {
        self::assertNull(DashboardPages::pagination(false, 1, 20, 3, DashboardPages::ROUTE_COLLECTIONS));
        $pagination = DashboardPages::pagination(true, 2, 20, 45, DashboardPages::ROUTE_ITEMS, ['q' => 'shop'], ['collectionId' => 4]);
        self::assertNotNull($pagination);
        self::assertSame(3, $pagination->totalPages);
        self::assertSame(['collectionId' => 4], $pagination->routeParams);
    }

    public function testDialogOpenerKeepsAnExistingQuery(): void
    {
        self::assertSame([
            'data-nowo-ui-dialog-open' => DashboardPages::DIALOG_ITEM_FORM,
            'data-bk-dialog-url' => '/items/new?x=1&_partial=1',
            'aria-haspopup' => 'dialog',
        ], DashboardPages::dialogOpener(DashboardPages::DIALOG_ITEM_FORM, '/items/new?x=1'));
    }

    public function testListPagesBuildRowActionsPerRow(): void
    {
        $collections = DashboardPageFixtures::collections();
        $page = DashboardPageFixtures::collectionsIndex($collections)['page'];
        self::assertInstanceOf(ListPage::class, $page);
        $rows = iterator_to_array($page->rows);
        $actions = $page->rowActions($rows[1], 1);

        self::assertSame(['view', 'export', 'edit', 'delete'], array_map(static fn ($a): string => $a->kind, $actions));
        self::assertSame('/breadcrumb-kit-admin/collections/2/delete', $actions[3]->fallbackHref);
        self::assertSame(12, $rows[1]['items']);
        self::assertSame(0, $rows[2]['items']);

        $items = DashboardPageFixtures::itemsIndex($collections[0], false, 'app')['page'];
        self::assertInstanceOf(ListPage::class, $items);
        self::assertNotNull($items->caption);
        self::assertSame('/breadcrumb-kit-admin/collections/1/items', $items->filtersClearHref);
    }
}
