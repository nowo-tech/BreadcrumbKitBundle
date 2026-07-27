<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig globals for dashboard layout / CSS framework (REQ-UI-001).
 */
final class BreadcrumbKitDashboardGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly string $layoutTemplate,
        private readonly string $cssFramework = 'bootstrap5',
        private readonly string $iconSet = 'bootstrap-icons',
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'nowo_breadcrumb_kit_layout_template' => $this->layoutTemplate,
            'nowo_breadcrumb_kit_css_framework' => $this->cssFramework,
            'nowo_breadcrumb_kit_icon_set' => $this->iconSet,
        ];
    }
}
