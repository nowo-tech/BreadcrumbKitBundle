<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Resources;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards the CSP-safe contract: JSON config islands and no inline executable scripts in bundle templates.
 */
final class CspSafeTemplatesTest extends TestCase
{
    private const VIEWS = __DIR__.'/../../../src/Resources/views';

    public function testBundleTemplatesHaveNoInlineExecutableScripts(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::VIEWS, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            // Allowed: external scripts (src=) and non-executable JSON islands.
            $inline = preg_match_all('/<script(?![^>]*\bsrc=)(?![^>]*type="application\/json")[^>]*>/i', $content);

            self::assertSame(0, $inline, \sprintf('Inline executable <script> found in %s', $file->getFilename()));
        }
    }

    public function testDashboardJsLoadsFormsIntoUiKitDialogsWithoutInlineHtml(): void
    {
        $js = (string) file_get_contents(self::VIEWS.'/../public/js/dashboard.js');

        self::assertStringContainsString('nowo-ui:dialog-loaded', $js, 'UiKit 2.2 remote dialogs load the forms');
        self::assertStringContainsString('data-nowo-ui-dialog-src', $js);
        self::assertStringContainsString('data-bk-dialog-url', $js, 'deprecated openers still load');
        self::assertStringContainsString('nowoUiOpenDialog', $js);
        self::assertStringNotContainsString('bootstrap', strtolower($js));
        self::assertStringNotContainsString('eval(', $js);
    }
}
