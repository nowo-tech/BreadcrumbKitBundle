<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Security;

use InvalidArgumentException;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbIconSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BreadcrumbIconSanitizerTest extends TestCase
{
    public function testEmptyValuesBecomeNull(): void
    {
        self::assertNull(BreadcrumbIconSanitizer::sanitize(null));
        self::assertNull(BreadcrumbIconSanitizer::sanitize('   '));
        self::assertTrue(BreadcrumbIconSanitizer::isAllowed(null));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function allowedIcons(): iterable
    {
        yield 'emoji' => ['⌂', '⌂'];
        yield 'ux icon token' => ['tabler:home', 'tabler:home'];
        yield 'icon font' => ['<i class="bi bi-house"></i>', '<i class="bi bi-house"></i>'];
        yield 'emoji span' => ['<span aria-hidden="true">🏠</span>', '<span aria-hidden="true">🏠</span>'];
        yield 'inline svg' => [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M8 1l7 7H1z"/></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M8 1l7 7H1z"/></svg>',
        ];
        yield 'svg fragment use' => [
            '<svg><use href="#home"/></svg>',
            '<svg><use href="#home"/></svg>',
        ];
        yield 'unknown attribute dropped' => ['<i class="bi" style="color:red" data-x="1"></i>', '<i class="bi"></i>'];
        yield 'comment dropped' => ['<span><!-- x -->A</span>', '<span>A</span>'];
        yield 'local gradient reference' => ['<svg><path fill="url(#g)"/></svg>', '<svg><path fill="url(#g)"/></svg>'];
        yield 'foreign namespaced attribute dropped' => ['<svg xmlns:f="urn:f"><path f:d="1" d="M0 0"/></svg>', '<svg><path d="M0 0"/></svg>'];
    }

    #[DataProvider('allowedIcons')]
    public function testAllowedIconsAreKept(string $input, string $expected): void
    {
        self::assertSame($expected, BreadcrumbIconSanitizer::sanitize($input));
        self::assertTrue(BreadcrumbIconSanitizer::isAllowed($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeIcons(): iterable
    {
        yield 'img onerror' => ['<img src=x onerror=alert(1)>'];
        yield 'img onerror xml' => ['<img src="x" onerror="alert(1)"/>'];
        yield 'script' => ['<script>alert(1)</script>'];
        yield 'event handler on allowed element' => ['<span onmouseover="alert(1)">x</span>'];
        yield 'svg onload' => ['<svg onload="alert(1)"/>'];
        yield 'javascript href' => ['<svg><use href="javascript:alert(1)"/></svg>'];
        yield 'external href' => ['<svg><use href="https://evil.test/i.svg#a"/></svg>'];
        yield 'xlink javascript' => ['<svg><use xlink:href="java&#x09;script:alert(1)"/></svg>'];
        yield 'url() to remote' => ['<svg><path fill="url(https://evil.test/x)"/></svg>'];
        yield 'foreignObject' => ['<svg><foreignObject><div>x</div></foreignObject></svg>'];
        yield 'iframe' => ['<iframe src="//evil.test"></iframe>'];
        yield 'malformed' => ['<span>'];
    }

    #[DataProvider('unsafeIcons')]
    public function testUnsafeIconsAreRejected(string $input): void
    {
        self::assertFalse(BreadcrumbIconSanitizer::isAllowed($input));
        self::assertNull(BreadcrumbIconSanitizer::sanitizeOrNull($input));

        $this->expectException(InvalidArgumentException::class);
        BreadcrumbIconSanitizer::sanitize($input);
    }

    public function testTextIsEscaped(): void
    {
        self::assertSame('&lt;b&gt;', BreadcrumbIconSanitizer::sanitize('<![CDATA[<b>]]>'));
    }
}
