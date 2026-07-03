<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Twig;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Safe URL to the dashboard collections index (null if disabled or route not registered).
 */
final class BreadcrumbKitDashboardLinkExtension extends AbstractExtension
{
    public function __construct(
        private readonly bool $dashboardEnabled,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('breadcrumb_kit_dashboard_collections_url', $this->collectionsUrl(...)),
        ];
    }

    public function collectionsUrl(): ?string
    {
        if (!$this->dashboardEnabled) {
            return null;
        }

        try {
            return $this->urlGenerator->generate('nowo_breadcrumb_kit_dashboard_collections_index');
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
