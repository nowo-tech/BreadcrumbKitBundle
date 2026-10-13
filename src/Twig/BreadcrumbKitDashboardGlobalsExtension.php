<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig globals for dashboard layout / CSS framework (REQ-UI-001) and the admin chrome hooks (spec 003).
 */
final class BreadcrumbKitDashboardGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param array<string, string> $classes  dashboard.classes (empty values removed)
     * @param array<string, string> $partials dashboard.partials (empty values removed)
     */
    public function __construct(
        private readonly string $layoutTemplate,
        private readonly string $cssFramework = 'bootstrap5',
        private readonly string $iconSet = 'bootstrap-icons',
        private readonly array $classes = [],
        private readonly array $partials = [],
        private readonly bool $uiKitStylesheet = true,
        private readonly string $collectionsView = 'list',
        private readonly string $rowActionsPosition = 'start',
        private readonly bool $cancelFirst = false,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'nowo_breadcrumb_kit_layout_template' => $this->layoutTemplate,
            'nowo_breadcrumb_kit_css_framework' => $this->cssFramework,
            'nowo_breadcrumb_kit_icon_set' => $this->iconSet,
            'nowo_breadcrumb_kit_classes' => $this->classes,
            'nowo_breadcrumb_kit_partials' => $this->partials,
            'nowo_breadcrumb_kit_ui_kit_stylesheet' => $this->uiKitStylesheet,
            'nowo_breadcrumb_kit_collections_view' => $this->collectionsView,
            'nowo_breadcrumb_kit_row_actions_position' => $this->rowActionsPosition,
            'nowo_breadcrumb_kit_cancel_first' => $this->cancelFirst,
        ];
    }
}
