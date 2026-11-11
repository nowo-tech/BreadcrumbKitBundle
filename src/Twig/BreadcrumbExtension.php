<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Twig;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabelInterface;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Stringable;
use Symfony\Contracts\Translation\TranslatableInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BreadcrumbExtension extends AbstractExtension
{
    public function __construct(
        private readonly BreadcrumbLoader $loader,
        private readonly BreadcrumbInlineEditResolver $inlineEditResolver,
        private readonly string $defaultCollection,
        private readonly ?BreadcrumbCurrentLabelInterface $currentLabel = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('breadcrumb_trail', $this->trail(...)),
            new TwigFunction('breadcrumb_render', $this->render(...), ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('breadcrumb_current_label', $this->currentLabel(...)),
        ];
    }

    /**
     * 2.17: names the current crumb for this request, e.g. `{% do breadcrumb_current_label(page.title) %}` at the top
     * of a child template (runs before its layout renders any block). Outputs nothing; null or blank restores the
     * item's label. With `domain`, the string is a message key translated in that domain; `collection` / `contextKey`
     * restrict it to the trails of that collection / context key.
     *
     * @param array<string, mixed> $parameters
     */
    public function currentLabel(
        string|Stringable|TranslatableInterface|null $label,
        ?string $domain = null,
        array $parameters = [],
        ?string $collection = null,
        ?string $contextKey = null,
    ): string {
        $this->currentLabel?->setCurrentLabel($label, $domain, $parameters, $collection, $contextKey);

        return '';
    }

    /**
     * @return list<BreadcrumbNode>
     */
    public function trail(?string $collectionCode = null, string $contextKey = ''): array
    {
        $code = $collectionCode ?? $this->defaultCollection;
        $view = $this->loader->loadTrailView($code, $contextKey);

        return $view->nodes;
    }

    public function render(Environment $twig, ?string $collectionCode = null, string $template = '@NowoBreadcrumbKitBundle/breadcrumb.html.twig', string $contextKey = ''): string
    {
        $code = $collectionCode ?? $this->defaultCollection;
        $view = $this->loader->loadTrailView($code, $contextKey);
        $inline = $this->inlineEditResolver->resolve($code, $contextKey);

        return $twig->render($template, [
            'trail' => $view,
            'breadcrumb_inline_edit' => [
                'show' => $inline->show,
                'iframe_url' => $inline->iframeUrl,
                'modal_title' => $inline->modalTitle,
            ],
        ]);
    }
}
