<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Import JSON produced by {@see BreadcrumbExporter}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class BreadcrumbImporter
{
    public const STRATEGY_SKIP_EXISTING = 'skip_existing';
    public const STRATEGY_REPLACE = 'replace';

    public function __construct(
        private BreadcrumbCollectionRepository $collectionRepository,
        private BreadcrumbItemRepository $itemRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<int|string, mixed> $data
     * @param self::STRATEGY_*         $strategy
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(array $data, string $strategy = self::STRATEGY_SKIP_EXISTING): array
    {
        $data = self::normalizeImportPayload($data);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        if (isset($data['collections']) && \is_array($data['collections'])) {
            /** @var array<string, true> $seen */
            $seen = [];
            foreach ($data['collections'] as $i => $block) {
                if (!\is_array($block) || !isset($block['collection']) || !isset($block['items'])) {
                    $result['errors'][] = $this->t('import.error.entry_missing_keys', ['%index%' => (string) $i]);

                    continue;
                }
                $colData = $block['collection'];
                $itemsData = \is_array($block['items']) ? array_values($block['items']) : [];
                if (!\is_array($colData)) {
                    $result['errors'][] = $this->t('import.error.collection_not_object', ['%index%' => (string) $i]);

                    continue;
                }
                $code = isset($colData['code']) && \is_string($colData['code']) ? trim($colData['code']) : '';
                if ('' === $code) {
                    $result['errors'][] = $this->t('import.error.entry_code_required', ['%index%' => (string) $i]);

                    continue;
                }
                $ctxKey = $this->contextKeyFromData($colData);
                $sig = $code."\0".$ctxKey;
                if (isset($seen[$sig])) {
                    continue;
                }
                $seen[$sig] = true;
                $this->importOne($colData, $itemsData, $strategy, $result);
            }
        } elseif (isset($data['collection']) && \is_array($data['collection']) && isset($data['items']) && \is_array($data['items'])) {
            $this->importOne($data['collection'], array_values($data['items']), $strategy, $result);
        } else {
            $result['errors'][] = $this->t('import.error.invalid_shape');
        }

        return $result;
    }

    /**
     * @param array<string, string> $params
     */
    private function t(string $id, array $params = []): string
    {
        return $this->translator->trans($id, $params, NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN);
    }

    /**
     * @param array<int|string, mixed> $data
     *
     * @return array<int|string, mixed>
     */
    public static function normalizeImportPayload(array $data): array
    {
        if ([] === $data || !array_is_list($data)) {
            return $data;
        }
        foreach ($data as $block) {
            if (!\is_array($block) || !isset($block['collection'], $block['items'])) {
                return $data;
            }
        }

        return ['collections' => array_values($data)];
    }

    /**
     * @param array<string, mixed>                                                  $colData
     * @param list<array<string, mixed>>                                            $itemsData
     * @param array{created: int, updated: int, skipped: int, errors: list<string>} $result
     */
    private function importOne(array $colData, array $itemsData, string $strategy, array &$result): void
    {
        $code = isset($colData['code']) && \is_string($colData['code']) ? trim($colData['code']) : '';
        if ('' === $code) {
            $result['errors'][] = $this->t('import.error.collection_code_required');

            return;
        }
        $ctxKey = $this->contextKeyFromData($colData);
        $existing = $this->collectionRepository->findOneByCodeAndContextKey($code, $ctxKey);

        if ($existing instanceof BreadcrumbCollection) {
            if (self::STRATEGY_SKIP_EXISTING === $strategy) {
                ++$result['skipped'];

                return;
            }
            foreach ($this->itemRepository->findAllForCollection($existing) as $item) {
                $this->entityManager->remove($item);
            }
            $this->entityManager->flush();
            $this->applyCollectionData($existing, $colData);
            ++$result['updated'];
            $collection = $existing;
        } else {
            $collection = new BreadcrumbCollection();
            $this->applyCollectionData($collection, $colData);
            $this->entityManager->persist($collection);
            $this->entityManager->flush();
            ++$result['created'];
        }

        $this->persistItemTree($itemsData, $collection, null);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $colData
     */
    private function contextKeyFromData(array $colData): string
    {
        if (!isset($colData['contextKey'])) {
            return '';
        }
        $v = $colData['contextKey'];

        return \is_string($v) ? $v : '';
    }

    /**
     * @param array<string, mixed> $colData
     */
    private function applyCollectionData(BreadcrumbCollection $collection, array $colData): void
    {
        $collection->setCode(isset($colData['code']) && \is_string($colData['code']) ? trim($colData['code']) : '');
        $collection->setContextKey($this->contextKeyFromData($colData));
        if (isset($colData['name'])) {
            $collection->setName(\is_string($colData['name']) && '' !== trim($colData['name']) ? trim($colData['name']) : null);
        }
        $collection->setHomeIcon($this->stringOrNull($colData['homeIcon'] ?? null));
        $collection->setSeparatorIcon($this->stringOrNull($colData['separatorIcon'] ?? null));
        if (\array_key_exists('responsiveConfig', $colData)) {
            $collection->setResponsiveConfig(\is_array($colData['responsiveConfig']) ? $colData['responsiveConfig'] : null);
        }
        $collection->setClassList($this->stringOrNull($colData['classList'] ?? null));
        $collection->setClassItem($this->stringOrNull($colData['classItem'] ?? null));
        $collection->setClassSeparator($this->stringOrNull($colData['classSeparator'] ?? null));
        $collection->setClassCurrent($this->stringOrNull($colData['classCurrent'] ?? null));
        $collection->setInlineEditAccessKey($this->stringOrNull($colData['inlineEditAccessKey'] ?? null));
    }

    /**
     * @param list<array<string, mixed>> $itemsData
     */
    private function persistItemTree(array $itemsData, BreadcrumbCollection $collection, ?BreadcrumbItem $parent): void
    {
        foreach ($itemsData as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $routeName = isset($row['routeName']) && \is_string($row['routeName']) ? trim($row['routeName']) : '';
            if ('' === $routeName) {
                continue;
            }
            $item = new BreadcrumbItem();
            $item->setCollection($collection);
            $item->setParent($parent);
            $item->setRouteName($routeName);
            $item->setStaticRouteParams($this->staticParamsFromRow($row));
            $item->setDynamicParamKeys($this->dynamicKeysFromRow($row));
            $item->setLinkEnabled(!isset($row['linkEnabled']) ? true : (bool) $row['linkEnabled']);
            $item->setLabel($this->stringOrNull($row['label'] ?? null));
            $item->setTranslations($this->translationsFromRow($row));
            $item->setIcon($this->stringOrNull($row['icon'] ?? null));
            $this->entityManager->persist($item);

            $children = isset($row['children']) && \is_array($row['children']) ? array_values($row['children']) : [];
            if ([] !== $children) {
                $this->persistItemTree($children, $collection, $item);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, scalar|null>
     */
    private function staticParamsFromRow(array $row): array
    {
        if (!isset($row['staticRouteParams']) || !\is_array($row['staticRouteParams'])) {
            return [];
        }
        $out = [];
        foreach ($row['staticRouteParams'] as $k => $v) {
            if (!\is_string($k)) {
                continue;
            }
            if (null === $v || \is_scalar($v)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>|null
     */
    private function dynamicKeysFromRow(array $row): ?array
    {
        if (!isset($row['dynamicParamKeys']) || !\is_array($row['dynamicParamKeys'])) {
            return null;
        }
        $out = [];
        foreach ($row['dynamicParamKeys'] as $v) {
            if (\is_string($v) && '' !== $v) {
                $out[] = $v;
            }
        }

        return [] === $out ? null : $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>|null
     */
    private function translationsFromRow(array $row): ?array
    {
        if (!isset($row['translations']) || !\is_array($row['translations'])) {
            return null;
        }
        $out = [];
        foreach ($row['translations'] as $k => $v) {
            if (\is_string($k) && \is_string($v)) {
                $out[$k] = $v;
            }
        }

        return [] === $out ? null : $out;
    }

    private function stringOrNull(mixed $v): ?string
    {
        if (!\is_string($v)) {
            return null;
        }
        $t = trim($v);

        return '' === $t ? null : $t;
    }
}
