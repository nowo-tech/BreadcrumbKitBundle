<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Enum;

use Nowo\BreadcrumbKitBundle\Enum\CssFramework;
use Nowo\BreadcrumbKitBundle\Enum\IconSet;
use Nowo\BreadcrumbKitBundle\Enum\ModalSize;
use PHPUnit\Framework\TestCase;

final class DashboardUiEnumsTest extends TestCase
{
    public function testCssFrameworkValuesAndNormalization(): void
    {
        self::assertContains('bootstrap5', CssFramework::values());
        self::assertContains('bootstrap', CssFramework::values());
        self::assertSame(CssFramework::Bootstrap5, CssFramework::Bootstrap->normalized());
        self::assertSame(CssFramework::Tailwind, CssFramework::Tailwind->normalized());
    }

    public function testIconSetAndModalSizeValues(): void
    {
        self::assertSame(
            ['bootstrap-icons', 'tabler-icons', 'ux_icon', 'svg_inline', 'none'],
            IconSet::values(),
        );
        self::assertSame(['normal', 'lg', 'xl'], ModalSize::values());
    }
}
