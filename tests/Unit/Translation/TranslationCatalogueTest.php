<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards against untranslated strings: in every non-en catalogue (all domains) a key must exist, and a value identical to
 * the English one (any length, ignoring values without letters once placeholders are removed) must be listed in
 * ALLOWED_IDENTICAL for that locale. An allow-list entry that is no longer identical to English is reported as stale.
 */
final class TranslationCatalogueTest extends TestCase
{
    private const DIR = __DIR__.'/../../../src/Resources/translations';

    /**
     * Keys that are correctly identical to English in a locale (brands, product and technical names, codes, loanwords
     * and cognates), per locale.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_IDENTICAL = [
        'de' => [
            'dashboard.brand',
            'dashboard.link_export_json',
            'dashboard.table_code',
            'dashboard.table_id',
            'dashboard.table_link',
            'dashboard.table_name',
            'dashboard.table_route',
            'dashboard.title',
            'demo.base.brand_sub',
            'demo.base.footer_crud',
            'demo.base.github',
            'demo.base.packagist',
            'demo.shop.heading',
            'demo.sidebar.admin',
            'demo.sidebar.admin_block',
            'demo.sidebar.navigation',
            'demo.sidebar.section_static',
            'demo.sidebar.section_twig',
            'demo.sidebar.shop',
            'demo.sidebar.support',
            'form.breadcrumb_collection.class_list.placeholder',
            'form.breadcrumb_collection.code.label',
            'form.breadcrumb_item.path_pattern.placeholder',
            'form.breadcrumb_item.route_name.placeholder',
        ],
        'es' => [
            'dashboard.brand',
            'dashboard.link_export_json',
            'dashboard.link_no',
            'dashboard.table_id',
            'dashboard.title',
            'demo.base.brand_sub',
            'demo.base.footer_crud',
            'demo.base.github',
            'demo.base.packagist',
            'demo.sidebar.admin',
            'demo.sidebar.admin_block',
            'demo.sidebar.section_static',
            'demo.sidebar.section_twig',
            'form.breadcrumb_collection.class_list.placeholder',
            'form.breadcrumb_item.path_pattern.placeholder',
            'form.breadcrumb_item.route_name.placeholder',
        ],
        'fr' => [
            'dashboard.back_collections',
            'dashboard.brand',
            'dashboard.collection_modal_title',
            'dashboard.collection_styles_modal_title',
            'dashboard.collections_heading',
            'dashboard.link_export_json',
            'dashboard.nav_collections',
            'dashboard.table_actions',
            'dashboard.table_code',
            'dashboard.table_id',
            'dashboard.table_parent',
            'dashboard.table_route',
            'dashboard.title',
            'demo.base.brand_sub',
            'demo.base.footer_crud',
            'demo.base.github',
            'demo.base.packagist',
            'demo.sidebar.admin',
            'demo.sidebar.admin_block',
            'demo.sidebar.navigation',
            'demo.sidebar.section_static',
            'demo.sidebar.section_twig',
            'demo.sidebar.support',
            'form.breadcrumb_collection.class_list.placeholder',
            'form.breadcrumb_collection.code.label',
            'form.breadcrumb_item.parent.label',
            'form.breadcrumb_item.path_pattern.placeholder',
            'form.breadcrumb_item.route_name.placeholder',
        ],
        'it' => [
            'dashboard.brand',
            'dashboard.link_export_json',
            'dashboard.link_no',
            'dashboard.table_id',
            'dashboard.table_link',
            'dashboard.title',
            'demo.base.brand_sub',
            'demo.base.footer_crud',
            'demo.base.github',
            'demo.base.packagist',
            'demo.sidebar.admin',
            'demo.sidebar.admin_block',
            'demo.sidebar.section_static',
            'demo.sidebar.section_twig',
            'form.breadcrumb_collection.class_list.placeholder',
            'form.breadcrumb_item.path_pattern.placeholder',
            'form.breadcrumb_item.route_name.placeholder',
        ],
        'nl' => [
            'dashboard.back_items',
            'dashboard.brand',
            'dashboard.item_modal_title',
            'dashboard.link_export_json',
            'dashboard.table_code',
            'dashboard.table_context',
            'dashboard.table_id',
            'dashboard.table_label',
            'dashboard.table_link',
            'dashboard.table_route',
            'dashboard.title',
            'demo.base.brand_sub',
            'demo.base.footer_crud',
            'demo.base.github',
            'demo.base.packagist',
            'demo.product.heading',
            'demo.sidebar.admin',
            'demo.sidebar.admin_block',
            'demo.sidebar.section_static',
            'demo.sidebar.section_twig',
            'demo.sidebar.support',
            'form.breadcrumb_collection.class_list.placeholder',
            'form.breadcrumb_collection.code.label',
            'form.breadcrumb_item.path_pattern.placeholder',
            'form.breadcrumb_item.route_name.placeholder',
        ],
        'pt' => [
            'dashboard.brand',
            'dashboard.item_modal_title',
            'dashboard.link_export_json',
            'dashboard.table_id',
            'dashboard.title',
            'demo.admin_dashboard.heading',
            'demo.base.brand_sub',
            'demo.base.footer_crud',
            'demo.base.github',
            'demo.base.packagist',
            'demo.sidebar.admin',
            'demo.sidebar.admin_block',
            'demo.sidebar.section_static',
            'demo.sidebar.section_twig',
            'form.breadcrumb_collection.class_list.placeholder',
            'form.breadcrumb_item.path_pattern.placeholder',
            'form.breadcrumb_item.route_name.placeholder',
        ],
    ];

    /** @return iterable<string, array{string, string}> */
    public static function catalogues(): iterable
    {
        $files = glob(self::DIR.'/*.yaml') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (1 === preg_match('/^(.+)\.([a-z]{2})\.yaml$/', basename($file), $m) && 'en' !== $m[2]) {
                yield basename($file) => [$m[1], $m[2]];
            }
        }
    }

    #[DataProvider('catalogues')]
    public function testLocaleHasEveryEnglishKey(string $domain, string $locale): void
    {
        $missing = array_values(array_diff(array_keys($this->load($domain, 'en')), array_keys($this->load($domain, $locale))));

        self::assertSame([], $missing, \sprintf('Keys of %s.en.yaml missing in %s.%s.yaml', $domain, $domain, $locale));
    }

    #[DataProvider('catalogues')]
    public function testNoUntranslatedEnglishValues(string $domain, string $locale): void
    {
        $english = $this->load($domain, 'en');
        $target = $this->load($domain, $locale);
        $allowed = self::ALLOWED_IDENTICAL[$locale] ?? [];
        $gaps = [];

        foreach ($english as $key => $value) {
            if (!isset($target[$key]) || $target[$key] !== $value || \in_array($key, $allowed, true)) {
                continue;
            }

            if (1 === preg_match('/[^\W\d_]/u', (string) preg_replace('/%[^%\s]+%|\{[^}]+\}/', '', $value))) {
                $gaps[] = $key;
            }
        }

        self::assertSame([], $gaps, \sprintf('Values identical to English in %s.%s.yaml (translate or allow-list)', $domain, $locale));
    }

    public function testAllowListHasNoStaleEntries(): void
    {
        $english = [];
        $locales = [];

        foreach (self::catalogues() as [$domain, $locale]) {
            $english += $this->load($domain, 'en');
            $locales[$locale] = ($locales[$locale] ?? []) + $this->load($domain, $locale);
        }

        $stale = [];

        foreach (self::ALLOWED_IDENTICAL as $locale => $keys) {
            foreach ($keys as $key) {
                if (!isset($english[$key]) || ($locales[$locale][$key] ?? null) !== $english[$key]) {
                    $stale[] = $locale.':'.$key;
                }
            }
        }

        self::assertSame([], $stale, 'Allow-list entries that no longer exist or are no longer identical to English');
    }

    /** @return array<string, string> */
    private function load(string $domain, string $locale): array
    {
        $data = Yaml::parseFile(\sprintf('%s/%s.%s.yaml', self::DIR, $domain, $locale));

        return $this->flatten(\is_array($data) ? $data : []);
    }

    /**
     * @param array<mixed> $tree
     *
     * @return array<string, string>
     */
    private function flatten(array $tree, string $prefix = ''): array
    {
        $out = [];

        foreach ($tree as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix.'.'.$key;

            if (\is_array($value)) {
                $out += $this->flatten($value, $path);
            } else {
                $out[$path] = (string) $value;
            }
        }

        return $out;
    }
}
