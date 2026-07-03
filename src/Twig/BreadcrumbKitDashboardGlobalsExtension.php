<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BreadcrumbKitDashboardGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly string $layoutTemplate,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'nowo_breadcrumb_kit_layout_template' => $this->layoutTemplate,
        ];
    }
}
