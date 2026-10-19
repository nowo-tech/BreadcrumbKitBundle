<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Contract;

use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbCollectionType;
use Nowo\BreadcrumbKitBundle\Tests\Support\DashboardPageFixtures;
use Nowo\FormKitBundle\Twig\LocaleTabsTwigExtension;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\Test\RenderedPage;
use Nowo\UiKitBundle\View\ConfirmPage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\DataCollectorTranslator;
use Twig\Environment;

/**
 * REQ-UI-003: every dashboard page extends a UiKit scaffold, fills the canonical blocks, ships no chrome of its own
 * (no <html>, CDN, inline handlers, framework classes outside the theme slots) and renders translated labels only.
 */
final class PageContractTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        $collections = DashboardPageFixtures::collections();
        $default = $collections[0];
        $items = DashboardPageFixtures::items($default);

        yield 'collections index' => new PageFixture(DashboardPages::TEMPLATES['collections_index'], DashboardPageFixtures::collectionsIndex($collections));
        yield 'collections index (empty)' => new PageFixture(DashboardPages::TEMPLATES['collections_index'], DashboardPageFixtures::collectionsIndex([]));
        yield 'collections index (search, no pagination)' => new PageFixture(DashboardPages::TEMPLATES['collections_index'], DashboardPageFixtures::collectionsIndex([$collections[1]], 'shop', false));
        yield 'collection form (new, error)' => new PageFixture(DashboardPages::TEMPLATES['collection_form'], DashboardPageFixtures::collectionForm(null, true));
        yield 'collection form (edit)' => new PageFixture(DashboardPages::TEMPLATES['collection_form'], DashboardPageFixtures::collectionForm($default));
        yield 'collection delete (no-JS confirm)' => new PageFixture(DashboardPages::TEMPLATES['collection_delete'], DashboardPageFixtures::collectionDelete($collections[2]));
        yield 'items index' => new PageFixture(DashboardPages::TEMPLATES['items_index'], DashboardPageFixtures::itemsIndex($default));
        yield 'items index (empty)' => new PageFixture(DashboardPages::TEMPLATES['items_index'], DashboardPageFixtures::itemsIndex($collections[1], true));
        yield 'items index (empty search)' => new PageFixture(DashboardPages::TEMPLATES['items_index'], DashboardPageFixtures::itemsIndex($default, true, 'nothing'));
        yield 'item form (new)' => new PageFixture(DashboardPages::TEMPLATES['item_form'], DashboardPageFixtures::itemForm($default, null));
        yield 'item form (edit)' => new PageFixture(DashboardPages::TEMPLATES['item_form'], DashboardPageFixtures::itemForm($default, $items[1]));
        yield 'item delete (no-JS confirm)' => new PageFixture(DashboardPages::TEMPLATES['item_delete'], DashboardPageFixtures::itemDelete($default, $items[2]));
        yield 'import' => new PageFixture(DashboardPages::TEMPLATES['import'], DashboardPageFixtures::import());
    }

    /**
     * @return iterable<string, array{PageFixture}>
     */
    public static function translationPages(): iterable
    {
        foreach (self::pageFixtures() as $name => $fixture) {
            yield $name => [$fixture];
        }
    }

    /**
     * The form theme translates placeholders in the field domain: the search placeholder is a catalogue key translated
     * once (it was translated by the controller / page model and looked up again: a profiler missing message).
     */
    #[DataProvider('translationPages')]
    public function testPageLooksUpNoMissingMessage(PageFixture $fixture): void
    {
        $twig = $this->createTwig();
        $collector = $twig->getExtension(TranslationExtension::class)->getTranslator();
        \assert($collector instanceof DataCollectorTranslator);
        $html = $twig->render($fixture->template, $fixture->context);

        $missing = array_filter(
            $collector->getCollectedMessages(),
            static fn (array $message): bool => DataCollectorTranslator::MESSAGE_MISSING === $message['state'],
        );
        self::assertSame([], array_values(array_map(static fn (array $message): string => $message['domain'].': '.$message['id'], $missing)));
        if (str_contains($html, 'name="q"')) {
            self::assertStringContainsString('placeholder="Search by code, name or context…"', $html);
        }
    }

    protected static function inlineTemplates(): array
    {
        return [
            // 1.x host layout (dashboard.layout_template defining nowo_breadcrumb_kit_content): wrapped by the bridge.
            'host/legacy.html.twig' => '<legacy-layout><title>{% block title %}{% endblock %}</title><h1 class="host-title">{{ block(\'bk_page_title\') }}</h1>{% block stylesheets %}<host-css/>{% endblock %}<main>{% block nowo_breadcrumb_kit_content %}{% endblock %}</main>{% block javascripts %}{% endblock %}</legacy-layout>',
            // Thin app override through the bang namespace (REQ-UI-003 "For applications" 4).
            'host/items_override.html.twig' => "{% extends '@!NowoBreadcrumbKitBundle/dashboard/item/index.html.twig' %}{% block nowo_ui_list_after %}<p class=\"host-note\">host note</p>{% endblock %}",
        ];
    }

    public function testCollectionsIndexRendersCellsActionsDialogsAndPagination(): void
    {
        $html = $this->html(DashboardPages::TEMPLATES['collections_index'], DashboardPageFixtures::collectionsIndex(DashboardPageFixtures::collections()));

        self::assertStringContainsString('<a href="/nowo_breadcrumb_kit_dashboard_items_index?collectionId=1"><code>default</code></a>', $html);
        self::assertStringContainsString('Back office', $html);
        self::assertStringContainsString('tenant-42', $html);
        self::assertSame(3, substr_count($html, 'data-nowo-confirm-open'), 'One confirm toggle per collection delete.');
        self::assertStringContainsString('Delete collection “admin” and all its items?', $html);
        self::assertStringContainsString('value="token-delete_collection_3"', $html);
        self::assertStringContainsString('nowo-ui-action__fallback" href="/breadcrumb-kit-admin/collections/3/delete"', $html);
        // UiKit 2.2 remote dialog: the edit link (no-JS fallback) is fetched into the collection form dialog
        self::assertMatchesRegularExpression('#href="/breadcrumb-kit-admin/collections/2/edit"[^>]* data-nowo-ui-dialog-open="bk-collection-form-dialog" data-nowo-ui-dialog-src="/breadcrumb-kit-admin/collections/2/edit"#', $html);
        self::assertStringNotContainsString('data-bk-dialog-url', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-body data-bk-dialog-body', $html);
        self::assertMatchesRegularExpression('/<dialog id="bk-collection-form-dialog" class="[^"]*nowo-ui-dialog--xl"/', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-open="bk-import-dialog"', $html);
        self::assertMatchesRegularExpression('/<dialog id="bk-import-dialog"[^>]*data-testid="bk-import-dialog"/', $html);
        self::assertStringContainsString('action="/breadcrumb-kit-admin/import"', $html);
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('data-nowo-ui-region="pagination"', $html);
        self::assertStringContainsString('js/dashboard.js', $html);
        self::assertStringNotContainsString('modal-dialog', $html, 'No Bootstrap modal markup.');
    }

    public function testItemsIndexRendersParentsLinksAndEmptyStates(): void
    {
        $collection = DashboardPageFixtures::collections()[0];
        $html = $this->html(DashboardPages::TEMPLATES['items_index'], DashboardPageFixtures::itemsIndex($collection));

        self::assertStringContainsString('<code>app_product_show</code>', $html);
        self::assertStringContainsString('<code>app_shop</code> <span class="', $html);
        self::assertStringContainsString('#11</span>', $html);
        self::assertStringContainsString('Delete item #12 (app_product_show)?', $html);
        self::assertStringContainsString('href="/breadcrumb-kit-admin/collections/1/items?q=app_shop"', $html);
        self::assertStringContainsString('Items · default', $html);
        self::assertStringContainsString('data-testid="bk-item-new"', $html);
        self::assertMatchesRegularExpression('#data-testid="bk-item-new" data-nowo-ui-dialog-open="bk-item-form-dialog" data-nowo-ui-dialog-src="[^"]*/items/new"#', $html);
        self::assertStringContainsString('id="bk-item-form-dialog"', $html);

        $empty = $this->html(DashboardPages::TEMPLATES['items_index'], DashboardPageFixtures::itemsIndex($collection, true));
        self::assertStringContainsString('No items.', $empty);
        self::assertStringContainsString('Add one', $empty);

        $search = $this->html(DashboardPages::TEMPLATES['items_index'], DashboardPageFixtures::itemsIndex($collection, true, 'nothing'));
        self::assertStringContainsString('No items match.', $search);
        self::assertStringContainsString('View all', $search);
    }

    public function testFormPagesCarryErrorsBackLinkAndDangerZone(): void
    {
        $collections = DashboardPageFixtures::collections();
        $newContext = DashboardPageFixtures::collectionForm(null, true);
        $new = $this->html(DashboardPages::TEMPLATES['collection_form'], $newContext);
        self::assertStringContainsString('A collection with this code and context key already exists.', $new);
        // UiKit 2.2: the trail (kit, else the dashboard's own — breadcrumbsMode auto) is the way back — no "←" action
        self::assertSame('auto', $newContext['page']->header->breadcrumbsMode);
        self::assertSame([], $newContext['page']->header->actions);
        self::assertStringNotContainsString('← Collections', $new);
        self::assertStringNotContainsString('data-nowo-ui-region="danger-zone"', $new);

        $edit = $this->html(DashboardPages::TEMPLATES['collection_form'], DashboardPageFixtures::collectionForm($collections[0]));
        self::assertStringContainsString('data-nowo-ui-region="danger-zone"', $edit);
        self::assertStringContainsString('Edit · default', $edit);
        self::assertStringContainsString('href="/breadcrumb-kit-admin/collections/1/export"', $edit);

        $item = $this->html(DashboardPages::TEMPLATES['item_form'], DashboardPageFixtures::itemForm($collections[0], DashboardPageFixtures::items($collections[0])[1]));
        self::assertStringContainsString('Edit item #11', $item);
        self::assertStringNotContainsString('← Items', $item);
        self::assertStringContainsString('class="nowo-ui-input nowo-ui-input--full nowo-ui-code"', $item);
        self::assertStringContainsString('data-nowo-ui-region="danger-zone"', $item);

        $newItem = $this->html(DashboardPages::TEMPLATES['item_form'], DashboardPageFixtures::itemForm($collections[0], null));
        self::assertStringContainsString('New breadcrumb item', $newItem);
        self::assertStringNotContainsString('data-nowo-ui-region="danger-zone"', $newItem);
    }

    public function testDeleteConfirmPagesPostWithCsrf(): void
    {
        $collection = DashboardPageFixtures::collections()[2];
        $html = $this->html(DashboardPages::TEMPLATES['collection_delete'], DashboardPageFixtures::collectionDelete($collection));

        self::assertStringContainsString('data-nowo-ui-page="confirm"', $html);
        self::assertStringContainsString('action="/breadcrumb-kit-admin/collections/3/delete"', $html);
        self::assertStringContainsString('value="token-delete_collection_3"', $html);
        self::assertStringContainsString('href="/breadcrumb-kit-admin/collections"', $html);

        $default = DashboardPageFixtures::collections()[0];
        $item = $this->html(DashboardPages::TEMPLATES['item_delete'], DashboardPageFixtures::itemDelete($default, DashboardPageFixtures::items($default)[2]));
        self::assertStringContainsString('action="/breadcrumb-kit-admin/collections/1/items/12/delete"', $item);
        self::assertStringContainsString('value="token-delete_item_12"', $item);
        self::assertStringContainsString('Delete item #12 (app_product_show)?', $item);
        self::assertStringContainsString('href="/breadcrumb-kit-admin/collections/1/items"', $item);
    }

    public function testImportPageShowsTheFormatHint(): void
    {
        $html = $this->html(DashboardPages::TEMPLATES['import'], DashboardPageFixtures::import());

        self::assertStringContainsString('<code>breadcrumbKitExport</code>', $html);
        self::assertStringContainsString('Import collections', $html);
    }

    public function testDialogPartialsUseThemeSlotsOnly(): void
    {
        $twig = $this->createTwig('bootstrap5');
        $collection = DashboardPageFixtures::collections()[0];
        $form = DashboardPageFixtures::factory()->create(BreadcrumbCollectionType::class, $collection)->createView();

        foreach ([
            ['@NowoBreadcrumbKitBundle/dashboard/_collection_form_partial.html.twig', ['form' => $form, 'title' => 'Edit · default', 'is_edit' => true]],
            ['@NowoBreadcrumbKitBundle/dashboard/_item_form_partial.html.twig', ['form' => DashboardPageFixtures::itemFormView(null), 'title' => 'New item']],
            ['@NowoBreadcrumbKitBundle/dashboard/_import_partial.html.twig', ['form' => DashboardPageFixtures::importForm(), 'dialog' => true]],
        ] as [$template, $context]) {
            $html = $twig->render($template, $context);
            $page = new RenderedPage($template, $html, 'bootstrap5');
            $this->assertNoForbiddenMarkup($page);
            $this->assertNoFrameworkUtilityClasses($page);
            self::assertStringContainsString('data-nowo-ui-dialog-close', $html);
            self::assertStringContainsString('pc-form-actions', $html, 'The partials read the theme slots.');
            self::assertStringContainsString('data-bk-dialog-title="', $html);
        }
    }

    public function testLegacyLayoutIsBridged(): void
    {
        $context = DashboardPageFixtures::collectionDelete(DashboardPageFixtures::collections()[2]);
        $page = $context['page'];
        self::assertInstanceOf(ConfirmPage::class, $page);
        $context['page'] = new ConfirmPage($page->header, $page->confirm, $page->action, $page->cancelHref, DashboardPages::BUNDLE, DashboardPages::LEGACY_LAYOUT_BRIDGE);
        $context['nowo_breadcrumb_kit_legacy_layout'] = 'host/legacy.html.twig';

        $html = $this->createTwig()->render(DashboardPages::TEMPLATES['collection_delete'], $context);

        self::assertStringStartsWith('<legacy-layout><title>Confirm deletion</title><h1 class="host-title">Confirm deletion</h1>', $html);
        self::assertStringContainsString('<host-css/>', $html);
        self::assertStringContainsString('css/nowo-ui.css', $html);
        self::assertStringContainsString('js/nowo-ui-confirm.js', $html);
        self::assertStringContainsString('js/nowo-ui-dialog.js', $html);
        self::assertStringContainsString('data-nowo-ui-bundle="NowoBreadcrumbKitBundle"', $html);
        self::assertStringContainsString('data-nowo-ui-page="confirm"', $html);
    }

    public function testBangNamespaceOverrideKeepsTheBundlePage(): void
    {
        $html = $this->html('host/items_override.html.twig', DashboardPageFixtures::itemsIndex(DashboardPageFixtures::collections()[0]));

        self::assertStringContainsString('<p class="host-note">host note</p>', $html);
        self::assertStringContainsString('data-nowo-ui-page="list"', $html);
    }

    protected static function templatePaths(): array
    {
        $views = \dirname(__DIR__, 3).'/src/Resources/views';

        return [
            'NowoBreadcrumbKitBundle' => $views,
            '!NowoBreadcrumbKitBundle' => $views,
            'NowoFormKitBundle' => \dirname(__DIR__, 3).'/vendor/nowo-tech/form-kit-bundle/src/Resources/views',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function formThemes(): array
    {
        return ['form_div_layout.html.twig', '@NowoFormKitBundle/form/locale_tabs_theme.html.twig'];
    }

    protected function configureTwig(Environment $twig): void
    {
        $twig->addExtension(new LocaleTabsTwigExtension());
    }

    protected static function translationDirs(): array
    {
        return [\dirname(__DIR__, 3).'/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return \dirname(__DIR__, 3).'/docs/TEMPLATES.md';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function html(string $template, array $context): string
    {
        return $this->renderPage(new PageFixture($template, $context))->html;
    }
}
