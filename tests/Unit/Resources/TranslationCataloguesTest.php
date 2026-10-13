<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Every shipped locale carries the same keys as English, and every literal key a dashboard template
 * translates exists in the catalogue (no hard-coded or missing strings in the admin chrome).
 */
final class TranslationCataloguesTest extends TestCase
{
    public function testAllLocalesShareTheEnglishKeySet(): void
    {
        $english = $this->keys('en');
        foreach (glob($this->dir().'/NowoBreadcrumbKitBundle.*.yaml') ?: [] as $file) {
            $locale = explode('.', basename($file))[1];
            $keys = $this->keys($locale);
            self::assertSame([], array_values(array_diff($english, $keys)), \sprintf('Missing in %s.', $locale));
            self::assertSame([], array_values(array_diff($keys, $english)), \sprintf('Extra in %s.', $locale));
        }
    }

    public function testDashboardTemplatesOnlyUseExistingKeys(): void
    {
        $english = array_flip($this->keys('en'));
        $missing = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 3).'/src/Resources/views/dashboard'));
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            preg_match_all("#'(dashboard\.[a-z0-9_.]+)'\s*\|\s*trans#", (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $key) {
                if (!isset($english[$key])) {
                    $missing[] = $file->getFilename().': '.$key;
                }
            }
        }

        self::assertSame([], $missing);
    }

    /**
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $data = Yaml::parseFile($this->dir().'/NowoBreadcrumbKitBundle.'.$locale.'.yaml');
        self::assertIsArray($data);

        $flat = [];
        $walk = static function (array $node, string $prefix) use (&$walk, &$flat): void {
            foreach ($node as $k => $v) {
                $key = '' === $prefix ? (string) $k : $prefix.'.'.$k;
                if (\is_array($v)) {
                    $walk($v, $key);
                } else {
                    $flat[] = $key;
                }
            }
        };
        $walk($data, '');
        sort($flat);

        return $flat;
    }

    private function dir(): string
    {
        return \dirname(__DIR__, 3).'/src/Resources/translations';
    }
}
