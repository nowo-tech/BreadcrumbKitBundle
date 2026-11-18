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
use Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbRowLock;
use Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbTransactionRunner;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
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
    private const OUTCOME_CREATED = 'created';
    private const OUTCOME_UPDATED = 'updated';
    private const OUTCOME_SKIPPED = 'skipped';
    private const OUTCOME_KEY_CONFLICT = 'key_conflict';

    public const STRATEGY_SKIP_EXISTING = 'skip_existing';
    public const STRATEGY_REPLACE = 'replace';

    private BreadcrumbTransactionRunner $transactionRunner;

    public function __construct(// @phpstan-ignore constructor.unusedParameter
        private BreadcrumbCollectionRepository $collectionRepository,
        // Kept for the positional signature (2.20 reads the items under a lock through BreadcrumbRowLock).
        BreadcrumbItemRepository $itemRepository,
        EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private LoggerInterface $logger = new NullLogger(),
        private ?BreadcrumbCacheInvalidator $changeNotifier = null,
        ?BreadcrumbTransactionRunner $transactionRunner = null,
    ) {
        // 2.20: every write runs on the bundle's runner (the container wires its own); a hand-built importer gets one on
        // the manager it was given.
        $this->transactionRunner = $transactionRunner ?? new BreadcrumbTransactionRunner($entityManager, null, null, 1, BreadcrumbTransactionRunner::BACKOFF_BASE_MILLISECONDS, $changeNotifier, $logger);
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
            try {
                $this->importCollections($data['collections'], $strategy, $result);
            } catch (TransientStorageFailure $failure) {
                throw $this->afterCommit($failure, $result);
            }
        } elseif (isset($data['collection']) && \is_array($data['collection']) && isset($data['items']) && \is_array($data['items'])) {
            $this->importOne($data['collection'], array_values($data['items']), $strategy, $result);
        } else {
            $result['errors'][] = $this->t('import.error.invalid_shape');
        }

        return $result;
    }

    /**
     * The collections of a multi-collection file, one unit of work each.
     *
     * @param array<int|string, mixed>                                              $collections
     * @param self::STRATEGY_*                                                      $strategy
     * @param array{created: int, updated: int, skipped: int, errors: list<string>} $result
     */
    private function importCollections(array $collections, string $strategy, array &$result): void
    {
        /** @var array<string, true> $seen */
        $seen = [];
        foreach ($collections as $i => $block) {
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
    }

    /**
     * A conflict after one collection of the file committed (each collection is its own unit): part of the file is
     * stored, so the answer is CONFLICT_002 (read again, do not repeat blindly). Inside a transaction of the caller
     * nothing is committed yet.
     *
     * @param array{created: int, updated: int, skipped: int, errors: list<string>} $result
     */
    private function afterCommit(TransientStorageFailure $failure, array $result): TransientStorageFailure
    {
        if ($failure instanceof ConcurrencyConflict && $failure->committed) {
            return $failure;
        }
        if (0 === $result['created'] + $result['updated'] || $this->transactionRunner->manager()->getConnection()->isTransactionActive()) {
            return $failure;
        }
        $cause = $failure->getPrevious() ?? $failure;
        $kind = (new TransientFailureClassifier())->classify($cause) ?? TransientFailureKind::Retryable;

        return new ConcurrencyConflict(1, $kind, null, $failure, true);
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
        // Read outside the unit, as a hint only: the first statement of the unit is a locking read or the claiming
        // insert, so what the unit reads afterwards is the state committed by every writer that held the row before it.
        $known = $this->collectionRepository->findOneByCodeAndContextKey($code, $ctxKey);
        if ($known instanceof BreadcrumbCollection && self::STRATEGY_SKIP_EXISTING === $strategy) {
            ++$result['skipped'];

            return;
        }
        $knownId = $known?->getId();

        // One unit of work per collection: a failure while re-creating the tree must not leave a "replace" import with
        // its old items deleted and the new ones missing. The collection row is locked first (it is the lock of its
        // tree), so two imports of one collection queue instead of both deleting the old items and both inserting
        // theirs. The unit is replayed after a deadlock or lock wait (outermost transaction only), so it starts from
        // the committed state every time and keeps no result outside the closure.
        $rejected = ['icon' => 0, 'pathPattern' => 0, 'params' => 0];
        // @igor-ignore - the closure runs inside the unit and is dropped when it returns; the runner is stateless.
        $outcome = $this->transactionRunner->runRetrying(function () use ($code, $ctxKey, $knownId, $colData, $itemsData, $strategy, &$rejected): string {
            $rejected = ['icon' => 0, 'pathPattern' => 0, 'params' => 0];
            $locked = $this->lockOrCreate($code, $ctxKey, $knownId);
            if (null === $locked) {
                return self::OUTCOME_KEY_CONFLICT;
            }
            [$collection, $created] = $locked;
            if (!$created && self::STRATEGY_SKIP_EXISTING === $strategy) {
                return self::OUTCOME_SKIPPED;
            }
            $this->writeCollection($created ? null : $collection, $collection, $colData, $itemsData, $rejected);

            return $created ? self::OUTCOME_CREATED : self::OUTCOME_UPDATED;
        });

        if (self::OUTCOME_KEY_CONFLICT === $outcome) {
            $result['errors'][] = $this->t('import.error.collection_key_conflict', ['%code%' => $code]);

            return;
        }
        if (self::OUTCOME_SKIPPED === $outcome) {
            ++$result['skipped'];

            return;
        }

        if (self::OUTCOME_UPDATED === $outcome) {
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
        if ($rejected['params'] > 0) {
            $result['errors'][] = $this->t('import.error.params_rejected', ['%code%' => $code, '%count%' => (string) $rejected['params']]);
        }
    }

    /**
     * The collection of a key, locked: the existing one, or the one this call just claimed. Never flushes (the runner
     * does, once, at the end of the unit).
     *
     * @return array{0: BreadcrumbCollection, 1: bool}|null the locked collection and whether this call created it; null when
     *                                                      only a look-alike key exists (another case, trailing spaces)
     *
     * @throws TransientStorageFailure when the collection changed while it was being claimed (the unit is replayed)
     */
    private function lockOrCreate(string $code, string $contextKey, ?int $knownId): ?array
    {
        $manager = $this->transactionRunner->manager();
        if (null !== $knownId) {
            $locked = BreadcrumbRowLock::lockCollection($manager, $knownId);
            // Audit QR-L2: the hint was read before the lock; the row may have been renamed meanwhile. Only the row that
            // still has the import's key is the target; otherwise the claim below finds (or creates) the right one.
            if ($locked instanceof BreadcrumbCollection && $locked->getCode() === $code && $locked->getContextKey() === $contextKey) {
                return [$locked, false];
            }
        }
        $created = BreadcrumbRowLock::claimCollection($manager, $code, $contextKey);
        // The pair is ours (just inserted) or exists now (a concurrent import committed it): read it under its lock.
        [$collection, $lookAlike] = BreadcrumbRowLock::lockCollectionByKey($manager, $code, $contextKey);
        if ($collection instanceof BreadcrumbCollection) {
            if ($created) {
                $this->changeNotifier?->announceCreated($collection);
            }

            return [$collection, $created];
        }
        if ($lookAlike) {
            return null;
        }

        // Deleted again between the claim and the read: the replay starts over.
        throw new TransientStorageFailure(1, 'The collection changed while it was being imported.');
    }

    /**
     * @param array<string, mixed>                            $colData
     * @param list<array<string, mixed>>                      $itemsData
     * @param array{icon: int, pathPattern: int, params: int} $rejected  values dropped as unsafe (icons) or invalid (path patterns)
     */
    private function writeCollection(?BreadcrumbCollection $existing, BreadcrumbCollection $collection, array $colData, array $itemsData, array &$rejected): void
    {
        $manager = $this->transactionRunner->manager();
        if ($existing instanceof BreadcrumbCollection) {
            // A locking read (audit QR-M1): inside a transaction of the application (REPEATABLE READ) a plain read would
            // show the snapshot of its earlier reads, not the tree the import we queued behind has just committed.
            foreach (BreadcrumbRowLock::lockItemsOf($manager, $existing) as $item) {
                // @igor-ignore - a Doctrine entity of this unit of work, not state of the shared runner.
                $manager->remove($item);
            }
        }
        $this->applyCollectionData($collection, $colData, $rejected);
        $this->persistItemTree($itemsData, $collection, null, $rejected, $manager);
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
     * @param array<string, mixed>                            $colData
     * @param array{icon: int, pathPattern: int, params: int} $rejected
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
     * @param list<array<string, mixed>>                      $itemsData
     * @param array{icon: int, pathPattern: int, params: int} $rejected
     */
    private function persistItemTree(array $itemsData, BreadcrumbCollection $collection, ?BreadcrumbItem $parent, array &$rejected, EntityManagerInterface $manager): void
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
            $item->setDynamicParamKeys($this->dynamicKeysFromRow($row, $rejected));
            $item->setLinkEnabled(!isset($row['linkEnabled']) ? true : (bool) $row['linkEnabled']);
            $item->setLabel($this->stringOrNull($row['label'] ?? null));
            $item->setTranslations($this->translationsFromRow($row));
            $item->setIcon($this->iconOrNull($row['icon'] ?? null, $rejected));
            $manager->persist($item);

            $children = isset($row['children']) && \is_array($row['children']) ? array_values($row['children']) : [];
            if ([] !== $children) {
                $this->persistItemTree($children, $collection, $item, $rejected, $manager);
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
     * `dynamicParamKeys` plus the `params` map, stored together (see {@see BreadcrumbParamMap}). A pair that cannot be
     * read as a mapping is dropped and counted with the rejected values.
     *
     * @param array<string, mixed>                            $row
     * @param array{icon: int, pathPattern: int, params: int} $rejected
     *
     * @return list<string>|null
     */
    private function dynamicKeysFromRow(array $row, array &$rejected): ?array
    {
        $out = [];
        foreach (\is_array($row['dynamicParamKeys'] ?? null) ? $row['dynamicParamKeys'] : [] as $v) {
            if (\is_string($v) && '' !== $v) {
                $out[] = $v;
            }
        }
        $params = $row['params'] ?? [];
        if (!\is_array($params)) {
            ++$rejected['params'];
            $params = [];
        }
        foreach ($params as $target => $sources) {
            $entry = BreadcrumbParamMap::encode($target, $sources);
            if (null === $entry) {
                ++$rejected['params'];
            } else {
                $out[] = $entry;
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
     * @param array{icon: int, pathPattern: int, params: int} $rejected
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
     * @param array{icon: int, pathPattern: int, params: int} $rejected
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
