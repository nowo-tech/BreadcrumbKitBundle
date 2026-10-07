<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;

/**
 * Guards the CSP-safe contract: JSON config islands and no inline executable scripts in bundle templates.
 */
final class CspSafeTemplatesTest extends TestCase
{
    private const VIEWS = __DIR__.'/../../../src/Resources/views';

    public function testDashboardLayoutEmitsJsonIslandWithoutInlineAssignment(): void
    {
        $twig = (string) file_get_contents(self::VIEWS.'/dashboard/layout.html.twig');

        self::assertStringContainsString('<script type="application/json" id="nowo-breadcrumb-kit-dashboard">', $twig);
        self::assertStringNotContainsString('window.__breadcrumbKitDashboard', $twig);
        self::assertStringNotContainsString('window.breadcrumbKitI18n', $twig);
    }

    public function testBundleTemplatesHaveNoInlineExecutableScripts(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::VIEWS, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            // Allowed: external scripts (src=) and non-executable JSON islands.
            $inline = preg_match_all('/<script(?![^>]*\bsrc=)(?![^>]*type="application\/json")[^>]*>/i', $content);

            self::assertSame(0, $inline, \sprintf('Inline executable <script> found in %s', $file->getFilename()));
        }
    }

    public function testPageTemplatesUsePageIsland(): void
    {
        foreach (['collection', 'item'] as $dir) {
            $twig = (string) file_get_contents(self::VIEWS.'/dashboard/'.$dir.'/index.html.twig');
            self::assertStringContainsString('id="nowo-breadcrumb-kit-dashboard-page"', $twig);
        }
    }

    public function testDashboardJsReadsIslandWithLegacyFallback(): void
    {
        $js = (string) file_get_contents(self::VIEWS.'/../public/js/dashboard.js');

        self::assertStringContainsString('nowo-breadcrumb-kit-dashboard', $js);
        self::assertStringContainsString('window.__breadcrumbKitDashboard', $js);
    }
}
