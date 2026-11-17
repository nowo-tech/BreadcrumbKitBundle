<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Dto;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbCollapse;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

final class BreadcrumbTrailViewWithNodesTest extends TestCase
{
    private function view(): BreadcrumbTrailView
    {
        return new BreadcrumbTrailView(
            [
                new BreadcrumbNode('Home', '/', true, false, 'home', ['a' => 1]),
                new BreadcrumbNode('Project', '/projects/1', false, true),
            ],
            'house',
            'chevron',
            'list',
            'item',
            'sep',
            'cur',
            ['k' => ['v']],
            false,
            new BreadcrumbCollapse(true, 4, '48rem'),
        );
    }

    private function assertPresentationKept(BreadcrumbTrailView $a, BreadcrumbTrailView $b): void
    {
        self::assertSame($a->homeIcon, $b->homeIcon);
        self::assertSame($a->separatorIcon, $b->separatorIcon);
        self::assertSame($a->classList, $b->classList);
        self::assertSame($a->classItem, $b->classItem);
        self::assertSame($a->classSeparator, $b->classSeparator);
        self::assertSame($a->classCurrent, $b->classCurrent);
        self::assertSame($a->responsiveConfig, $b->responsiveConfig);
        self::assertSame($a->homeIconReplacesLabel, $b->homeIconReplacesLabel);
        self::assertSame($a->collapse, $b->collapse);
    }

    public function testWithNodesReplacesOnlyNodesAndKeepsEverythingElse(): void
    {
        $view = $this->view();
        $original = $view->nodes;
        $new = [new BreadcrumbNode('Only', null, false, true)];

        $copy = $view->withNodes($new);

        self::assertNotSame($view, $copy);
        self::assertSame($new, $copy->nodes);
        self::assertSame($original, $view->nodes);
        $this->assertPresentationKept($view, $copy);
    }

    public function testWithNodeReplacesOneNodeWithoutTouchingTheOriginal(): void
    {
        $view = $this->view();
        $node = $view->nodes[1]->withLabel('Acme');

        $copy = $view->withNode(1, $node);

        self::assertSame($node, $copy->nodes[1]);
        self::assertSame($view->nodes[0], $copy->nodes[0]);
        self::assertSame('Project', $view->nodes[1]->label);
        $this->assertPresentationKept($view, $copy);
    }

    public function testWithNodeRejectsUnknownIndex(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->view()->withNode(5, new BreadcrumbNode('x', null, false, false));
    }

    public function testMapNodesReturnsSameInstanceWhenNothingChanged(): void
    {
        $view = $this->view();

        self::assertSame($view, $view->mapNodes(static fn (BreadcrumbNode $n): BreadcrumbNode => $n));
    }

    public function testMapNodesRebuildsWhenANodeChanged(): void
    {
        $view = $this->view();
        $seen = [];

        $copy = $view->mapNodes(static function (BreadcrumbNode $n, int $i) use (&$seen): BreadcrumbNode {
            $seen[] = $i;

            return $n->current ? $n->withLabel('Acme') : $n;
        });

        self::assertSame([0, 1], $seen);
        self::assertNotSame($view, $copy);
        self::assertSame(['Home', 'Acme'], array_map(static fn (BreadcrumbNode $n): string => $n->label, $copy->nodes));
        self::assertSame('Project', $view->nodes[1]->label);
        self::assertSame($view->nodes[0], $copy->nodes[0]);
        $this->assertPresentationKept($view, $copy);
    }

    public function testNodeWithLabelKeepsEveryOtherField(): void
    {
        $node = new BreadcrumbNode('Home', '/', true, true, 'home', ['a' => 1]);

        $copy = $node->withLabel('Start');

        self::assertSame('Start', $copy->label);
        self::assertSame('Home', $node->label);
        self::assertSame('/', $copy->url);
        self::assertTrue($copy->linkEnabled);
        self::assertTrue($copy->current);
        self::assertSame('home', $copy->icon);
        self::assertSame(['a' => 1], $copy->routeParams);
    }
}
