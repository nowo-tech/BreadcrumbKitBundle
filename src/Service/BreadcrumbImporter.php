<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbIconSanitizer;
use Nowo\BreadcrumbKitBundle\Security\BreadcrumbPathPatternValidator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

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
        private LoggerInterface $logger = new NullLogger(),
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
        $context = ['bundle' => 'NowoBreadcrumbKitBundle', 'action' => 'import', 'strategy' => $strategy];
        $this->logger->info('Breadcrumb import started.', $context);
        try {
            $result = $this->doImport($data, $strategy);
        } catch (Throwable $e) {
            $this->logger->error('Breadcrumb import failed.', $context + ['exception' => $e::class]);

            throw $e;
        }

        $context += [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'errors' => \count($result['errors']),
        ];
        if ([] === $result['errors']) {
            $this->logger->info('Breadcrumb import finished.', $context);
        } else {
            $this->logger->warning('Breadcrumb import finished with errors.', $context);
        }

        return $result;
    }

    /**
     * Import body without logging (see import()).
     *
     * @param array<int|string, mixed> $data
     * @param self::STRATEGY_*         $strategy
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    private function doImport(array $data, string $strategy): array
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

        return ['collections' => $data];
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

        if ($existing instanceof BreadcrumbCollection && self::STRATEGY_SKIP_EXISTING === $strategy) {
            ++$result['skipped'];

            return;
        }

        // One transaction per collection: a failure while re-creating the tree must not leave a "replace"
        // import with its old items deleted and the new ones missing.
        $this->entityManager->beginTransaction();
        $rejected = ['icon' => 0, 'pathPattern' => 0];
        try {
            $this->writeCollection($existing, $colData, $itemsData, $rejected);
            $this->entityManager->commit();
        } catch (Throwable $e) {
            $this->entityManager->rollback();
            // Same as EntityManager::wrapInTransaction(): the unit of work is no longer consistent.
            $this->entityManager->close();

            throw $e;
        }

        if ($existing instanceof BreadcrumbCollection) {
            ++$result['updated'];
        } else {
            ++$result['created'];
        }
        if ($rejected['icon'] > 0) {
            $result['errors'][] = $this->t('import.error.icon_rejected', ['%code%' => $code, '%count%' => (string) $rejected['icon']]);
        }
        if ($rejected['pathPattern'] > 0) {
            $result['errors'][] = $this->t('import.error.path_pattern_rejected', ['%code%' => $code, '%count%' => (string) $rejected['pathPattern']]);
        }
    }

    /**
     * @param array<string, mixed>               $colData
     * @param list<array<string, mixed>>         $itemsData
     * @param array{icon: int, pathPattern: int} $rejected  values dropped as unsafe (icons) or invalid (path patterns)
     */
    private function writeCollection(?BreadcrumbCollection $existing, array $colData, array $itemsData, array &$rejected): void
    {
        if ($existing instanceof BreadcrumbCollection) {
            foreach ($this->itemRepository->findAllForCollection($existing) as $item) {
                $this->entityManager->remove($item);
            }
            $this->entityManager->flush();
            $this->applyCollectionData($existing, $colData, $rejected);
            $collection = $existing;
        } else {
            $collection = new BreadcrumbCollection();
            $this->applyCollectionData($collection, $colData, $rejected);
            $this->entityManager->persist($collection);
            $this->entityManager->flush();
        }

        $this->persistItemTree($itemsData, $collection, null, $rejected);
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
     * @param array<string, mixed>               $colData
     * @param array{icon: int, pathPattern: int} $rejected
     */
    private function applyCollectionData(BreadcrumbCollection $collection, array $colData, array &$rejected): void
    {
        $collection->setCode(isset($colData['code']) && \is_string($colData['code']) ? trim($colData['code']) : '');
        $collection->setContextKey($this->contextKeyFromData($colData));
        if (isset($colData['name'])) {
            $collection->setName(\is_string($colData['name']) && '' !== trim($colData['name']) ? trim($colData['name']) : null);
        }
        $collection->setHomeIcon($this->iconOrNull($colData['homeIcon'] ?? null, $rejected));
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
     * @param list<array<string, mixed>>         $itemsData
     * @param array{icon: int, pathPattern: int} $rejected
     */
    private function persistItemTree(array $itemsData, BreadcrumbCollection $collection, ?BreadcrumbItem $parent, array &$rejected): void
    {
        foreach ($itemsData as $row) {
            $routeName = isset($row['routeName']) && \is_string($row['routeName']) ? trim($row['routeName']) : '';
            if ('' === $routeName) {
                continue;
            }
            $item = new BreadcrumbItem();
            $item->setCollection($collection);
            $item->setParent($parent);
            $item->setRouteName($routeName);
            $item->setStaticRouteParams($this->staticParamsFromRow($row));
            $item->setPathPattern($this->pathPatternOrNull($row['pathPattern'] ?? null, $rejected));
            $item->setMatchAttributes($this->matchAttributesFromRow($row));
            $item->setDynamicParamKeys($this->dynamicKeysFromRow($row));
            $item->setLinkEnabled(!isset($row['linkEnabled']) ? true : (bool) $row['linkEnabled']);
            $item->setLabel($this->stringOrNull($row['label'] ?? null));
            $item->setTranslations($this->translationsFromRow($row));
            $item->setIcon($this->iconOrNull($row['icon'] ?? null, $rejected));
            $this->entityManager->persist($item);

            $children = isset($row['children']) && \is_array($row['children']) ? array_values($row['children']) : [];
            if ([] !== $children) {
                $this->persistItemTree($children, $collection, $item, $rejected);
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
     * @return array<string, scalar|null>|null
     */
    private function matchAttributesFromRow(array $row): ?array
    {
        if (!isset($row['matchAttributes']) || !\is_array($row['matchAttributes'])) {
            return null;
        }
        $out = [];
        foreach ($row['matchAttributes'] as $k => $v) {
            if (!\is_string($k)) {
                continue;
            }
            if (null === $v || \is_scalar($v)) {
                $out[$k] = $v;
            }
        }

        return [] === $out ? null : $out;
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

    /**
     * Icons are printed |raw on public pages: imported markup goes through the same allowlist as the form; an
     * unsafe value is dropped (stored as null) and counted so the import result reports it.
     *
     * @param array{icon: int, pathPattern: int} $rejected
     */
    private function iconOrNull(mixed $v, array &$rejected): ?string
    {
        $icon = $this->stringOrNull($v);
        $sanitized = BreadcrumbIconSanitizer::sanitizeOrNull($icon);
        if (null !== $icon && null === $sanitized) {
            ++$rejected['icon'];
        }

        return $sanitized;
    }

    /**
     * Same check as the dashboard form: a pattern that does not compile or has nested quantifiers is dropped (the
     * item then matches by route only) and counted with the rejected values.
     *
     * @param array{icon: int, pathPattern: int} $rejected
     */
    private function pathPatternOrNull(mixed $v, array &$rejected): ?string
    {
        $pattern = $this->stringOrNull($v);
        if (null !== $pattern && !BreadcrumbPathPatternValidator::isAllowed($pattern)) {
            ++$rejected['pathPattern'];

            return null;
        }

        return $pattern;
    }
}
