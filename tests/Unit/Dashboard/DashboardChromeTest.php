<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Dashboard;

use Nowo\BreadcrumbKitBundle\Dashboard\DashboardChrome;
use PHPUnit\Framework\TestCase;

final class DashboardChromeTest extends TestCase
{
    public function testUnknownKeysListsOnlyKeysOutsideTheAllowList(): void
    {
        self::assertSame([], DashboardChrome::unknownKeys(['btn_primary' => 'a', 'table' => 'b'], DashboardChrome::CLASS_KEYS));
        self::assertSame(['nope', '0'], DashboardChrome::unknownKeys(['btn_primary' => 'a', 'nope' => 'b', 0 => 'c'], DashboardChrome::CLASS_KEYS));
    }

    public function testCleanTrimsAndDropsEmptyOrNonStringValues(): void
    {
        self::assertSame(
            ['btn_primary' => 'btn-primary'],
            DashboardChrome::clean(['btn_primary' => '  btn-primary ', 'table' => '   ', 'badge' => null, 0 => 'x', 'muted' => 3]),
        );
    }

    public function testPartialKeysAreTheDocumentedOnes(): void
    {
        self::assertContains('modal_actions', DashboardChrome::CLASS_KEYS);
        self::assertSame(DashboardChrome::CLASS_KEYS, array_values(array_unique(DashboardChrome::CLASS_KEYS)));
    }
}
