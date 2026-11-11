<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Twig;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabel;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use Nowo\BreadcrumbKitBundle\Twig\BreadcrumbExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * 2.17: `breadcrumb_current_label()` against the two layout orders (trail before / after the content block).
 */
final class BreadcrumbCurrentLabelTwigTest extends TestCase
{
    private const TRAIL = 'trail.html.twig';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function layouts(): iterable
    {
        $top = "{% extends 'layout.html.twig' %}\n{% do breadcrumb_current_label(page.title) %}\n{% block content %}<h1>{{ page.title }}</h1>{% endblock %}";
        $inBlock = "{% extends 'layout.html.twig' %}\n{% block content %}{% do breadcrumb_current_label(page.title) %}<h1>{{ page.title }}</h1>{% endblock %}";
        $before = '<nav>{{ breadcrumb_render(null, "trail.html.twig") }}</nav><main>{% block content %}{% endblock %}</main>';
        $after = '<main>{% block content %}{% endblock %}</main><nav>{{ breadcrumb_render(null, "trail.html.twig") }}</nav>';

        yield 'top of the child, trail before the content' => [$before, $top, 'Getting started'];
        yield 'top of the child, trail after the content' => [$after, $top, 'Getting started'];
        yield 'in the content block, trail after the content' => [$after, $inBlock, 'Getting started'];
        // Documented constraint: a block rendered after the trail is too late.
        yield 'in the content block, trail before the content' => [$before, $inBlock, 'Page'];
    }

    #[DataProvider('layouts')]
    public function testCurrentLabelReachesTheTrail(string $layout, string $page, string $expected): void
    {
        $twig = $this->twig($layout, $page, new BreadcrumbCurrentLabel($this->stack()));

        $html = $twig->render('page.html.twig', ['page' => ['title' => 'Getting started']]);

        self::assertStringContainsString('<nav>Home &gt; '.$expected.'</nav>', $html);
    }

    public function testLabelIsEscapedByTheTemplate(): void
    {
        $twig = $this->twig(
            '<nav>{{ breadcrumb_render(null, "trail.html.twig") }}</nav>',
            '{% do breadcrumb_current_label(title) %}{% include "layout.html.twig" %}',
            new BreadcrumbCurrentLabel($this->stack()),
        );

        $html = $twig->render('page.html.twig', ['title' => '<img src=x onerror=alert(1)>']);

        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<img', $html);
    }

    public function testNullTitleKeepsTheItemLabel(): void
    {
        $twig = $this->twig('<nav>{{ breadcrumb_render(null, "trail.html.twig") }}</nav>', '{% do breadcrumb_current_label(null) %}{% include "layout.html.twig" %}', new BreadcrumbCurrentLabel($this->stack()));

        self::assertStringContainsString('<nav>Home &gt; Page</nav>', $twig->render('page.html.twig'));
    }

    public function testFunctionOutputsNothingAndIsANoOpWithoutTheService(): void
    {
        $extension = new BreadcrumbExtension($this->loader(null), $this->inlineResolver(), 'default');

        self::assertSame('', $extension->currentLabel('Ignored'));
        self::assertSame('Page', $extension->trail()[1]->label);
    }

    public function testTranslationDomainIsPassedThrough(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->with('wiki.title', ['%t%' => 'Intro'], 'wiki', 'en')->willReturn('Wiki: Intro');
        $twig = $this->twig(
            '<nav>{{ breadcrumb_render(null, "trail.html.twig") }}</nav>',
            "{% do breadcrumb_current_label('wiki.title', 'wiki', {'%t%': 'Intro'}) %}{% include 'layout.html.twig' %}",
            new BreadcrumbCurrentLabel($this->stack(), $translator),
        );

        self::assertStringContainsString('<nav>Home &gt; Wiki: Intro</nav>', $twig->render('page.html.twig'));
    }

    private function twig(string $layout, string $page, BreadcrumbCurrentLabel $currentLabel): Environment
    {
        $twig = new Environment(new ArrayLoader([
            'layout.html.twig' => $layout,
            'page.html.twig' => $page,
            self::TRAIL => '{% for node in trail.nodes %}{{ node.label }}{% if not loop.last %} &gt; {% endif %}{% endfor %}',
        ]), ['autoescape' => 'html', 'strict_variables' => true]);
        $twig->addExtension(new BreadcrumbExtension($this->loader($currentLabel), $this->inlineResolver(), 'default', $currentLabel));

        return $twig;
    }

    private ?RequestStack $stack = null;

    private function stack(): RequestStack
    {
        if (null === $this->stack) {
            $request = Request::create('/wiki/intro');
            $request->attributes->set('_route', 'wiki_page');
            $request->attributes->set('_route_params', []);
            $this->stack = new RequestStack();
            $this->stack->push($request);
        }

        return $this->stack;
    }

    private function loader(?BreadcrumbCurrentLabel $currentLabel): BreadcrumbLoader
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $collection->setContextKey('');
        (new ReflectionProperty(BreadcrumbCollection::class, 'id'))->setValue($collection, 1);

        $home = $this->item(1, $collection, null, 'home', 'Home');
        $page = $this->item(2, $collection, $home, 'wiki_page', 'Page');

        $collections = $this->createMock(BreadcrumbCollectionRepository::class);
        $collections->method('findOneByCodeAndContextKey')->willReturn($collection);
        $items = $this->createMock(BreadcrumbItemRepository::class);
        $items->method('findAllForCollection')->willReturn([$home, $page]);

        return new BreadcrumbLoader(
            $collections,
            $items,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $this->stack(),
            'en',
            currentLabel: $currentLabel,
        );
    }

    private function item(int $id, BreadcrumbCollection $collection, ?BreadcrumbItem $parent, string $route, string $label): BreadcrumbItem
    {
        $item = new BreadcrumbItem();
        $item->setCollection($collection);
        $item->setParent($parent);
        $item->setRouteName($route);
        $item->setLabel($label);
        $item->setLinkEnabled(false);
        (new ReflectionProperty(BreadcrumbItem::class, 'id'))->setValue($item, $id);

        return $item;
    }

    private function inlineResolver(): BreadcrumbInlineEditResolver
    {
        return new BreadcrumbInlineEditResolver(
            new RequestStack(),
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->loader(null),
            $this->createMock(ContainerInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(TranslatorInterface::class),
            null,
            null,
            false,
        );
    }
}
