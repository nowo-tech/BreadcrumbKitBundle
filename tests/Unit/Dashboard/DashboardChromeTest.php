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

    public function testThemeBridgeMapsClassKeysToUiKitSlots(): void
    {
        self::assertSame([], DashboardChrome::themeBridge([]));
        self::assertSame([], DashboardChrome::themeBridge(['results' => 'host-results', 'search_input' => 'input', 'btn_primary' => '  ']));

        self::assertSame([
            'classes' => [
                'btn_primary' => ['value' => 'btn-moss', 'framework_defaults' => false],
                'action_neutral' => 'btn-ghost btn-icon',
                'action_danger' => 'btn-danger',
                'breadcrumbs' => ['value' => 'host-crumbs', 'framework_defaults' => false],
                'badge_neutral' => ['value' => 'badge badge--muted', 'framework_defaults' => false],
                'table_head_cell' => ['value' => 'px-2', 'framework_defaults' => false],
                'confirm_message' => ['value' => 'host-message', 'framework_defaults' => false],
                'dialog_body' => ['value' => 'modal-content', 'framework_defaults' => false],
            ],
        ], DashboardChrome::themeBridge([
            'btn_primary' => 'btn-moss',
            'action_view' => 'btn-view',
            'action_edit' => 'btn-ghost btn-icon',
            'action_delete' => 'btn-danger',
            'breadcrumb_nav' => 'host-crumbs',
            'badge' => 'badge',
            'badge_no' => 'badge badge--muted',
            'th' => 'px-2',
            'modal_message' => 'host-message',
            'modal_form_body' => 'modal-content',
            'list' => 'host-list',
        ]));
    }
}
