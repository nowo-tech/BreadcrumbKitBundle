<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Templates;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use Nowo\BreadcrumbKitBundle\Twig\BreadcrumbExtension;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * REQ-UI-003: the front trail (host chrome) reads the UiKit theme slots breadcrumbs / breadcrumb_link /
 * breadcrumb_separator, keeps the 2.5 / 2.6 markup when no host classes / attributes are set, and UiKit page headers
 * render it through nowo_ui_kit.breadcrumbs.breadcrumb_kit (breadcrumb_render()).
 */
final class BreadcrumbThemeSlotsTest extends TestCase
{
    private const TEMPLATE = '@NowoBreadcrumbKitBundle/breadcrumb.html.twig';

    private const LEGACY = 'legacy_v2_5_0.html.twig';

    public function testOutputIsUnchangedWithAnEmptyUiKitTheme(): void
    {
        $context = $this->context(new BreadcrumbTrailView(self::nodes(), separatorIcon: '›', classList: 'trail'));

        self::assertSame(
            $this->twig()->render(self::LEGACY, $context),
            $this->twig(new Theme('tailwind'))->render(self::TEMPLATE, $context),
        );
    }

    public function testHostSlotClassesAndAttributesAreAppended(): void
    {
        $theme = new Theme(
            'bootstrap5',
            ['breadcrumbs' => 'app-crumbs', 'breadcrumb_link' => 'app-crumbs__link', 'breadcrumb_separator' => 'app-crumbs__sep'],
            ['breadcrumbs' => ['data-controller' => 'crumbs'], 'breadcrumb_link' => ['data-turbo' => 'false']],
        );
        $html = $this->twig($theme)->render(self::TEMPLATE, $this->context(new BreadcrumbTrailView(self::nodes())));

        self::assertStringContainsString('<nav class="breadcrumb app-crumbs" aria-label="Breadcrumb" data-controller="crumbs">', $html);
        self::assertSame(2, substr_count($html, '<a href="/" class="app-crumbs__link" data-turbo="false">') + substr_count($html, '<a href="/catalogue?a=1&amp;b=2" class="app-crumbs__link" data-turbo="false">'));
        self::assertSame(3, substr_count($html, '<li class="breadcrumb-separator app-crumbs__sep" aria-hidden="true">'));
        self::assertStringNotContainsString('nowo-ui-breadcrumbs', $html, 'Only the host classes: no UiKit semantic / framework classes on the trail.');
        self::assertStringContainsString('<span aria-current="page">', $html, 'The current crumb is not a link.');
    }

    public function testUiKitPageHeadersRenderTheBreadcrumbKitTrail(): void
    {
        $twig = $this->twig(new Theme());
        $twig->addExtension($this->breadcrumbExtension());
        $uiKit = $twig->getExtension(ThemeExtension::class);

        self::assertTrue($uiKit->breadcrumbKitAvailable($twig));
        $html = $uiKit->breadcrumbKit($twig);
        self::assertStringContainsString('<div class="breadcrumb-kit">', $html);
        self::assertStringContainsString('<span aria-current="page"> Shop</span>', $html);
        self::assertStringContainsString('<a href="/"> Home</a>', $html);

        $disabled = new ThemeExtension(new Theme(), breadcrumbs: ['breadcrumb_kit' => false, 'collection' => null]);
        self::assertFalse($disabled->breadcrumbKitAvailable($twig));
        self::assertSame('', $disabled->breadcrumbKit($twig));
    }

    /**
     * @return list<BreadcrumbNode>
     */
    private static function nodes(): array
    {
        return [
            new BreadcrumbNode('Home', '/', true, false, '⌂'),
            new BreadcrumbNode('Shop & <Co>', null, false, false),
            new BreadcrumbNode('Catalogue', '/catalogue?a=1&b=2', true, false),
            new BreadcrumbNode('Product "X"', null, true, true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(BreadcrumbTrailView $trail): array
    {
        return ['trail' => $trail, 'breadcrumb_inline_edit' => ['show' => false, 'iframe_url' => null, 'modal_title' => null]];
    }

    private function breadcrumbExtension(): BreadcrumbExtension
    {
        $collection = (new BreadcrumbCollection())->setCode('default')->setContextKey('');
        (new \ReflectionProperty(BreadcrumbCollection::class, 'id'))->setValue($collection, 1);
        $home = new BreadcrumbItem();
        $home->setCollection($collection);
        $home->setRouteName('app_home');
        $home->setLabel('Home');
        $home->setLinkEnabled(true);
        (new \ReflectionProperty(BreadcrumbItem::class, 'id'))->setValue($home, 1);
        $shop = new BreadcrumbItem();
        $shop->setCollection($collection);
        $shop->setParent($home);
        $shop->setRouteName('app_shop');
        $shop->setLabel('Shop');
        (new \ReflectionProperty(BreadcrumbItem::class, 'id'))->setValue($shop, 2);

        $collections = $this->createMock(BreadcrumbCollectionRepository::class);
        $collections->method('findOneByCodeAndContextKey')->willReturn($collection);
        $items = $this->createMock(BreadcrumbItemRepository::class);
        $items->method('findAllForCollection')->willReturn([$home, $shop]);
        $urls = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $urls->method('resolve')->willReturn(['/', []]);

        $request = Request::create('/shop');
        $request->attributes->set('_route', 'app_shop');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);
        $loader = new BreadcrumbLoader($collections, $items, $urls, $stack, 'en');

        return new BreadcrumbExtension($loader, new BreadcrumbInlineEditResolver(
            new RequestStack(),
            $collections,
            $loader,
            $this->createMock(ContainerInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(TranslatorInterface::class),
            null,
            null,
            false,
        ), 'default');
    }

    private function twig(?Theme $theme = null): Environment
    {
        $root = \dirname(__DIR__, 3);
        $filesystem = new FilesystemLoader([], $root);
        $filesystem->addPath($root.'/src/Resources/views', 'NowoBreadcrumbKitBundle');
        $legacy = new ArrayLoader([self::LEGACY => (string) file_get_contents($root.'/tests/fixtures/templates/breadcrumb_v2_5_0.html.twig')]);
        $twig = new Environment(new ChainLoader([$legacy, $filesystem]), ['strict_variables' => false, 'autoescape' => 'html', 'cache' => false]);

        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', $root.'/src/Resources/translations/NowoBreadcrumbKitBundle.en.yaml', 'en', 'NowoBreadcrumbKitBundle');
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/assets/'.($package ?? 'app').'/'.$path));
        if (null !== $theme) {
            $twig->addExtension(new ThemeExtension($theme));
        }

        return $twig;
    }
}
