<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Twig;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BreadcrumbExtension extends AbstractExtension
{
    public function __construct(
        private readonly BreadcrumbLoader $loader,
        private readonly BreadcrumbInlineEditResolver $inlineEditResolver,
        private readonly string $defaultCollection,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('breadcrumb_trail', $this->trail(...)),
            new TwigFunction('breadcrumb_render', $this->render(...), ['is_safe' => ['html'], 'needs_environment' => true]),
        ];
    }

    /**
     * @return list<\Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode>
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
