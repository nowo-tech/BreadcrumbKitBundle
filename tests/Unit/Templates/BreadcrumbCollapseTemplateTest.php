<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Templates;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbCollapse;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Spec 004: "collapse on narrow screens" markup of breadcrumb.html.twig, and byte-identical output
 * to v2.5.0 whenever the option does not apply.
 */
final class BreadcrumbCollapseTemplateTest extends TestCase
{
    private const TEMPLATE = '@NowoBreadcrumbKitBundle/breadcrumb.html.twig';

    private const LEGACY = 'legacy_v2_5_0.html.twig';

    /**
     * @return iterable<string, array{BreadcrumbTrailView, bool}>
     */
    public static function unchangedTrails(): iterable
    {
        $styled = static fn (?BreadcrumbCollapse $collapse = null, array $responsive = []): BreadcrumbTrailView => new BreadcrumbTrailView(
            self::nodes(),
            homeIcon: '<svg class="home"></svg>',
            separatorIcon: '›',
            classList: 'trail',
            classItem: 'trail__item',
            classSeparator: 'trail__sep',
            classCurrent: 'is-here',
            responsiveConfig: $responsive,
            collapse: $collapse ?? new BreadcrumbCollapse(),
        );

        yield 'disabled' => [$styled(), false];
        yield 'disabled, inline edit' => [$styled(), true];
        yield 'disabled, legacy responsive breakpoint' => [$styled(null, ['breakpoint' => 768]), false];
        yield 'enabled below min_items' => [$styled(new BreadcrumbCollapse(true, 5)), false];
        yield 'enabled below min_items, inline edit' => [$styled(new BreadcrumbCollapse(true, 5)), true];
        yield 'default classes' => [new BreadcrumbTrailView(self::nodes()), false];
        yield 'single crumb' => [new BreadcrumbTrailView([new BreadcrumbNode('Home', null, true, true)], collapse: new BreadcrumbCollapse(true, 2)), false];
        yield 'empty trail with inline edit' => [new BreadcrumbTrailView([], collapse: new BreadcrumbCollapse(true, 2)), true];
    }

    #[DataProvider('unchangedTrails')]
    public function testOutputIsIdenticalToV250WhenCollapseDoesNotApply(BreadcrumbTrailView $trail, bool $inlineEdit): void
    {
        $context = $this->context($trail, $inlineEdit);

        self::assertSame($this->twig()->render(self::LEGACY, $context), $this->twig()->render(self::TEMPLATE, $context));
    }

    public function testCollapseItemSitsBeforeTheLastSeparator(): void
    {
        $xpath = $this->xpath($this->render($this->collapsibleTrail()));

        self::assertSame('40rem', $this->first($xpath, '//div[@class="breadcrumb-kit"]')->getAttribute('data-breadcrumb-collapse'));

        $classes = array_map(static fn (\DOMElement $li): string => $li->getAttribute('class'), $this->elements($xpath, '//ol[@class="breadcrumb-list"]/li'));

        self::assertSame([
            'trail__item',
            'trail__sep',
            'trail__item',
            'trail__sep',
            'trail__item',
            'trail__item breadcrumb-collapse',
            'trail__sep',
            'trail__item is-here',
        ], $classes, 'every normal crumb stays in the DOM; the collapse item precedes the last separator');
    }

    public function testMenuListsEveryPreviousCrumbInOrder(): void
    {
        $xpath = $this->xpath($this->render($this->collapsibleTrail()));

        $summary = $this->first($xpath, '//li[contains(@class,"breadcrumb-collapse")]/details[@data-bk-collapse]/summary[@class="breadcrumb-collapse__toggle"]');
        self::assertSame('Show previous pages', $summary->getAttribute('aria-label'));
        self::assertSame('…', trim($summary->textContent));

        $menu = $this->first($xpath, '//ol[@class="breadcrumb-collapse__menu"]');
        self::assertSame('list', $menu->getAttribute('role'));
        self::assertSame('Previous pages', $menu->getAttribute('aria-label'));

        self::assertCount(3, $this->elements($xpath, './/li[@class="breadcrumb-collapse__entry"]', $menu));

        $labels = array_map(static fn (\DOMElement $label): string => $label->textContent, $this->elements($xpath, './/span[@class="breadcrumb-collapse__label"]', $menu));
        self::assertSame(['Home', 'Shop & <Co>', 'Catalogue'], $labels, 'labels in trail order, current crumb excluded');

        $hrefs = array_map(static fn (\DOMElement $a): string => $a->getAttribute('href'), $this->elements($xpath, './/a[@class="breadcrumb-collapse__link"]', $menu));
        self::assertSame(['/', '/catalogue?a=1&b=2'], $hrefs);

        $text = $this->elements($xpath, './/span[@class="breadcrumb-collapse__text"]', $menu);
        self::assertCount(1, $text, 'non-link crumb rendered as text');
        self::assertStringContainsString('Shop & <Co>', $text[0]->textContent);
    }

    public function testMenuUsesHomeIconAndNodeIconsRawAndEscapesLabels(): void
    {
        $html = $this->render($this->collapsibleTrail());
        $menu = substr($html, (int) strpos($html, 'breadcrumb-collapse__menu'));

        self::assertStringContainsString('<span class="breadcrumb-icon" aria-hidden="true"><svg class="home"></svg></span><span class="breadcrumb-collapse__label">Home</span>', $menu, 'home icon + visible label');
        self::assertStringContainsString('<span class="breadcrumb-icon" aria-hidden="true"><i class="bi-box"></i></span><span class="breadcrumb-collapse__label">Catalogue</span>', $menu);
        self::assertStringContainsString('Shop &amp; &lt;Co&gt;', $menu);
        self::assertStringNotContainsString('<Co>', $html);
    }

    public function testAssetsCarryNonceBreakpointAndScript(): void
    {
        $html = $this->render($this->collapsibleTrail(new BreadcrumbCollapse(true, 3, '36.5em')), 'n0nce');

        self::assertStringContainsString('<style nonce="n0nce">', $html);
        self::assertStringContainsString('@container breadcrumb-kit (width < 36.5em)', $html);
        self::assertStringContainsString('@media not all and (min-width: 36.5em)', $html);
        self::assertStringContainsString('.breadcrumb-kit[data-breadcrumb-collapse="36.5em"] .breadcrumb-list > li:not(.breadcrumb-collapse):not(.breadcrumb-collapse ~ li) { display: none; }', $html);
        self::assertStringContainsString('var(--breadcrumb-kit-collapse-bg, Canvas)', $html);
        self::assertStringContainsString('prefers-reduced-motion', $html);
        self::assertStringContainsString('<script src="/assets/nowo_breadcrumb_kit/js/breadcrumb-collapse.js" defer></script>', $html);
        self::assertSame(1, substr_count($html, 'breadcrumb-collapse.js'));
    }

    public function testStyleWithoutNonceWhenRequestHasNone(): void
    {
        $html = $this->render($this->collapsibleTrail());

        self::assertStringContainsString('<style>', $html);
        self::assertStringNotContainsString('nonce=', $html);
    }

    public function testCollapseCoexistsWithInlineEdit(): void
    {
        $html = $this->twig()->render(self::TEMPLATE, $this->context($this->collapsibleTrail(), true));

        self::assertStringContainsString('<div class="breadcrumb-kit" data-breadcrumb-kit-inline-wrap data-breadcrumb-collapse="40rem">', $html);
        self::assertStringContainsString('breadcrumb-inline.js', $html);
        self::assertStringContainsString('breadcrumb-collapse.js', $html);
    }

    public function testToggleLabelIsTranslated(): void
    {
        $xpath = $this->xpath($this->render($this->collapsibleTrail(), null, 'es'));

        $summary = $this->first($xpath, '//summary');
        self::assertSame('Mostrar páginas anteriores', $summary->getAttribute('aria-label'));
        self::assertSame('Mostrar páginas anteriores', $summary->getAttribute('title'));
    }

    public function testEveryCatalogueTranslatesTheCollapseKeys(): void
    {
        foreach (['en', 'es', 'de', 'fr', 'it', 'nl', 'pt'] as $locale) {
            $translator = $this->translator($locale);
            foreach (['breadcrumb.collapse.toggle', 'breadcrumb.collapse.menu'] as $key) {
                self::assertNotSame($key, $translator->trans($key, [], 'NowoBreadcrumbKitBundle', $locale), $locale.': '.$key);
            }
        }
    }

    public function testBehaviourScriptUsesTheDisclosureHook(): void
    {
        $js = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/public/js/breadcrumb-collapse.js');

        self::assertStringContainsString("'details[data-bk-collapse]'", $js);
        self::assertStringContainsString("'Escape'", $js);
        self::assertStringContainsString('toggle.focus()', $js);
        self::assertStringContainsString("closest('a[href]')", $js);
        self::assertStringContainsString('window.__nowoBreadcrumbKitCollapseBound', $js);
    }

    /**
     * @return list<BreadcrumbNode>
     */
    private static function nodes(): array
    {
        return [
            new BreadcrumbNode('Home', '/', true, false, '⌂'),
            new BreadcrumbNode('Shop & <Co>', null, false, false),
            new BreadcrumbNode('Catalogue', '/catalogue?a=1&b=2', true, false, '<i class="bi-box"></i>'),
            new BreadcrumbNode('Product "X"', null, true, true),
        ];
    }

    private function collapsibleTrail(?BreadcrumbCollapse $collapse = null): BreadcrumbTrailView
    {
        return new BreadcrumbTrailView(
            self::nodes(),
            homeIcon: '<svg class="home"></svg>',
            separatorIcon: '›',
            classList: 'trail',
            classItem: 'trail__item',
            classSeparator: 'trail__sep',
            classCurrent: 'is-here',
            collapse: $collapse ?? new BreadcrumbCollapse(true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function context(BreadcrumbTrailView $trail, bool $inlineEdit): array
    {
        return [
            'trail' => $trail,
            'breadcrumb_inline_edit' => ['show' => $inlineEdit, 'iframe_url' => '/edit?x=1', 'modal_title' => 'Edit'],
        ];
    }

    private function render(BreadcrumbTrailView $trail, ?string $nonce = null, string $locale = 'en'): string
    {
        return $this->twig($nonce, $locale)->render(self::TEMPLATE, $this->context($trail, false));
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }

    /**
     * @return list<\DOMElement>
     */
    private function elements(\DOMXPath $xpath, string $expression, ?\DOMNode $context = null): array
    {
        $nodes = $xpath->query($expression, $context);
        self::assertInstanceOf(\DOMNodeList::class, $nodes);

        $elements = [];
        foreach ($nodes as $node) {
            self::assertInstanceOf(\DOMElement::class, $node);
            $elements[] = $node;
        }

        return $elements;
    }

    private function first(\DOMXPath $xpath, string $expression): \DOMElement
    {
        $elements = $this->elements($xpath, $expression);
        self::assertNotSame([], $elements, $expression);

        return $elements[0];
    }

    private function twig(?string $nonce = null, string $locale = 'en'): Environment
    {
        $root = \dirname(__DIR__, 3);
        $filesystem = new FilesystemLoader([], $root);
        $filesystem->addPath($root.'/src/Resources/views', 'NowoBreadcrumbKitBundle');
        $legacy = new ArrayLoader([self::LEGACY => (string) file_get_contents($root.'/tests/fixtures/templates/breadcrumb_v2_5_0.html.twig')]);

        $twig = new Environment(new ChainLoader([$legacy, $filesystem]), ['strict_variables' => false, 'autoescape' => 'html', 'cache' => false]);
        $twig->addExtension(new TranslationExtension($this->translator($locale)));

        $request = Request::create('/');
        $request->setLocale($locale);
        if (null !== $nonce) {
            $request->attributes->set('csp_nonce', $nonce);
        }
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $app = new AppVariable();
        $app->setRequestStack($requestStack);
        $twig->addGlobal('app', $app);
        $twig->addFunction(new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/assets/'.($package ?? 'app').'/'.$path));

        return $twig;
    }

    private function translator(string $locale): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (glob(\dirname(__DIR__, 3).'/src/Resources/translations/NowoBreadcrumbKitBundle.*.yaml') ?: [] as $file) {
            $translator->addResource('yaml', $file, explode('.', basename($file))[1], 'NowoBreadcrumbKitBundle');
        }

        return $translator;
    }
}
