<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Profiler\BreadcrumbProfilerRecorder;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Loads item definitions (optional cache), picks the best route match, walks parents, resolves labels/URLs.
 */
final readonly class BreadcrumbLoader
{
    private const CACHE_PREFIX = 'nowo_breadcrumb_kit.items.';

    public function __construct(
        private BreadcrumbCollectionRepository $collectionRepository,
        private BreadcrumbItemRepository $itemRepository,
        private BreadcrumbUrlResolverInterface $urlResolver,
        private RequestStack $requestStack,
        private ?string $defaultLocale,
        private ?CacheItemPoolInterface $cachePool = null,
        private int $cacheTtl = 60,
        private ?BreadcrumbProfilerRecorder $profilerRecorder = null,
        private bool $hideWhenSingleRoot = false,
        private bool $homeIconReplacesLabel = true,
        private ?string $defaultHomeIcon = null,
    ) {
    }

    public function loadTrailView(string $collectionCode, string $contextKey = ''): BreadcrumbTrailView
    {
        $request = $this->requestStack->getCurrentRequest();

        $collection = $this->collectionRepository->findOneByCodeAndContextKey($collectionCode, $contextKey);
        if (!$collection instanceof BreadcrumbCollection) {
            $empty = new BreadcrumbTrailView([]);
            $this->profile($collectionCode, $contextKey, $empty, 'collection_not_found', $request, null);

            return $empty;
        }

        if (!$request instanceof Request) {
            $view = $this->emptyView($collection);
            $this->profile($collectionCode, $contextKey, $view, 'no_http_request', null, null);

            return $view;
        }

        $locale = $request->getLocale();
        $rows = $this->loadItemRows($collection);

        $currentRoute = $request->attributes->get('_route');
        if (!\is_scalar($currentRoute) || '' === (string) $currentRoute) {
            $view = $this->emptyView($collection);
            $this->profile($collectionCode, $contextKey, $view, 'no_route', $request, null);

            return $view;
        }

        $routeName = (string) $currentRoute;
        /** @var array<string, scalar|null> $routeParams */
        $routeParams = (array) $request->attributes->get('_route_params', []);

        $best = $this->pickBestMatch($rows, $routeName, $routeParams);
        if (null === $best) {
            $view = $this->emptyView($collection);
            $this->profile($collectionCode, $contextKey, $view, 'no_item_match', $request, null);

            return $view;
        }

        $chain = $this->walkParentChain($rows, $best);
        $nodes = [];
        $n = \count($chain);
        foreach ($chain as $i => $row) {
            $label = $this->resolveLabelFromRow($row, $locale);
            $isLast = $i === $n - 1;
            $merged = [];
            $url = null;
            if ($row['link_enabled']) {
                [$url, $merged] = $this->urlResolver->resolve(
                    (string) $row['route_name'],
                    \is_array($row['static_params']) ? $row['static_params'] : [],
                    $this->castDynamicKeys($row['dynamic_keys'] ?? null),
                );
                if ($isLast) {
                    $url = null;
                }
            }

            $nodes[] = new BreadcrumbNode(
                label: $label,
                url: $url,
                linkEnabled: (bool) $row['link_enabled'],
                current: $isLast,
                icon: isset($row['icon']) ? (string) $row['icon'] : null,
                routeParams: $merged,
            );
        }

        $nodes = $this->finalizeNodes($nodes, $collection);

        $responsive = $collection->getResponsiveConfig();

        $view = new BreadcrumbTrailView(
            nodes: $nodes,
            homeIcon: $collection->getHomeIcon() ?? $this->defaultHomeIcon,
            separatorIcon: $collection->getSeparatorIcon(),
            classList: $collection->getClassList(),
            classItem: $collection->getClassItem(),
            classSeparator: $collection->getClassSeparator(),
            classCurrent: $collection->getClassCurrent(),
            responsiveConfig: \is_array($responsive) ? $responsive : [],
            homeIconReplacesLabel: $this->homeIconReplacesLabel,
        );
        $this->profile(
            $collectionCode,
            $contextKey,
            $view,
            'ok',
            $request,
            isset($best['route_name']) ? (string) $best['route_name'] : null,
        );

        return $view;
    }

    /**
     * Returns the breadcrumb item entity that matches the current request for the given collection, if any.
     */
    public function findMatchingItemForCurrentRequest(string $collectionCode, string $contextKey = ''): ?BreadcrumbItem
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        $collection = $this->collectionRepository->findOneByCodeAndContextKey($collectionCode, $contextKey);
        if (!$collection instanceof BreadcrumbCollection) {
            return null;
        }

        $cid = $collection->getId();
        if (null === $cid) {
            return null;
        }

        $rows = $this->loadItemRows($collection);
        $currentRoute = $request->attributes->get('_route');
        if (!\is_scalar($currentRoute) || '' === (string) $currentRoute) {
            return null;
        }

        $routeName = (string) $currentRoute;
        /** @var array<string, scalar|null> $routeParams */
        $routeParams = (array) $request->attributes->get('_route_params', []);

        $best = $this->pickBestMatch($rows, $routeName, $routeParams);
        if (null === $best || !isset($best['id'])) {
            return null;
        }

        $id = $best['id'];
        if (!\is_int($id) && !\is_string($id)) {
            return null;
        }

        $intId = (int) $id;
        if ($intId <= 0) {
            return null;
        }

        return $this->itemRepository->find($intId);
    }

    private function emptyView(BreadcrumbCollection $collection): BreadcrumbTrailView
    {
        $responsive = $collection->getResponsiveConfig();

        return new BreadcrumbTrailView(
            [],
            $collection->getHomeIcon() ?? $this->defaultHomeIcon,
            $collection->getSeparatorIcon(),
            $collection->getClassList(),
            $collection->getClassItem(),
            $collection->getClassSeparator(),
            $collection->getClassCurrent(),
            \is_array($responsive) ? $responsive : [],
            $this->homeIconReplacesLabel,
        );
    }

    /**
     * @param list<BreadcrumbNode> $nodes
     *
     * @return list<BreadcrumbNode>
     */
    private function finalizeNodes(array $nodes, BreadcrumbCollection $collection): array
    {
        if (!$this->shouldHideSingleRoot($nodes, $collection)) {
            return $nodes;
        }

        return [];
    }

    /**
     * @param list<BreadcrumbNode> $nodes
     */
    private function shouldHideSingleRoot(array $nodes, BreadcrumbCollection $collection): bool
    {
        if (1 !== \count($nodes)) {
            return false;
        }

        if (!$nodes[0]->current) {
            return false;
        }

        $responsive = $collection->getResponsiveConfig();
        if (\is_array($responsive) && \array_key_exists('hide_when_single_root', $responsive)) {
            return (bool) $responsive['hide_when_single_root'];
        }

        return $this->hideWhenSingleRoot;
    }

    /**
     * @param 'collection_not_found'|'no_http_request'|'no_route'|'no_item_match'|'ok' $status
     */
    private function profile(
        string $collectionCode,
        string $contextKey,
        BreadcrumbTrailView $view,
        string $status,
        ?Request $request,
        ?string $matchedItemRoute,
    ): void {
        if (null === $this->profilerRecorder) {
            return;
        }

        $route = null;
        if ($request instanceof Request) {
            $r = $request->attributes->get('_route');
            $route = \is_scalar($r) ? (string) $r : null;
        }

        $this->profilerRecorder->record($collectionCode, $contextKey, $view, $status, $route, $matchedItemRoute);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadItemRows(BreadcrumbCollection $collection): array
    {
        $cid = $collection->getId();
        if (null === $cid) {
            return [];
        }

        $cacheKey = self::CACHE_PREFIX.$cid;
        if ($this->cachePool instanceof CacheItemPoolInterface) {
            $item = $this->cachePool->getItem(md5($cacheKey));
            if ($item->isHit()) {
                $raw = $item->get();
                if ($this->isItemRowList($raw)) {
                    /* @var list<array<string, mixed>> $raw */

                    return $raw;
                }
            }
        }

        $entities = $this->itemRepository->findAllForCollection($collection);
        $rows = [];
        foreach ($entities as $entity) {
            $rows[] = $this->entityToRow($entity);
        }

        if ($this->cachePool instanceof CacheItemPoolInterface) {
            $cacheItem = $this->cachePool->getItem(md5($cacheKey));
            $cacheItem->set($rows);
            $cacheItem->expiresAfter($this->cacheTtl);
            $this->cachePool->save($cacheItem);
        }

        /* @var list<array<string, mixed>> $rows */

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function entityToRow(BreadcrumbItem $item): array
    {
        return [
            'id' => $item->getId(),
            'parent_id' => $item->getParent()?->getId(),
            'route_name' => $item->getRouteName(),
            'static_params' => $item->getStaticRouteParams() ?? [],
            'dynamic_keys' => $item->getDynamicParamKeys(),
            'link_enabled' => $item->isLinkEnabled(),
            'label' => $item->getLabel(),
            'translations' => $item->getTranslations() ?? [],
            'icon' => $item->getIcon(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, scalar|null> $routeParams
     *
     * @return array<string, mixed>|null
     */
    private function pickBestMatch(array $rows, string $routeName, array $routeParams): ?array
    {
        $candidates = [];
        foreach ($rows as $row) {
            if (($row['route_name'] ?? '') !== $routeName) {
                continue;
            }
            if (!$this->staticParamsMatch($row['static_params'] ?? [], $routeParams)) {
                continue;
            }
            $static = \is_array($row['static_params'] ?? null) ? $row['static_params'] : [];
            $score = \count($static);
            $candidates[] = ['row' => $row, 'score' => $score];
        }

        if ([] === $candidates) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $candidates[0]['row'];
    }

    /**
     * @param array<string, scalar|null> $static
     * @param array<string, scalar|null> $routeParams
     */
    private function staticParamsMatch(array $static, array $routeParams): bool
    {
        foreach ($static as $key => $value) {
            if (!\array_key_exists($key, $routeParams)) {
                return false;
            }
            if ((string) $routeParams[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed>       $leaf
     *
     * @return list<array<string, mixed>> Root → current
     */
    private function walkParentChain(array $rows, array $leaf): array
    {
        $byId = [];
        foreach ($rows as $r) {
            if (isset($r['id'])) {
                $byId[(int) $r['id']] = $r;
            }
        }

        $chain = [];
        $cur = $leaf;
        $guard = 0;
        while (null !== $cur && $guard++ < 256) {
            $chain[] = $cur;
            $pid = $cur['parent_id'] ?? null;
            if (null === $pid) {
                break;
            }
            $cur = $byId[(int) $pid] ?? null;
        }

        return array_reverse($chain);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveLabelFromRow(array $row, string $locale): string
    {
        $translations = \is_array($row['translations'] ?? null) ? $row['translations'] : [];
        if (isset($translations[$locale]) && \is_scalar($translations[$locale]) && '' !== (string) $translations[$locale]) {
            return (string) $translations[$locale];
        }
        $def = $this->defaultLocale;
        if (null !== $def && isset($translations[$def]) && \is_scalar($translations[$def]) && '' !== (string) $translations[$def]) {
            return (string) $translations[$def];
        }
        $label = $row['label'] ?? null;

        return \is_scalar($label) ? (string) $label : '';
    }

    /**
     * @return list<string>|null
     */
    private function castDynamicKeys(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }
        $out = [];
        foreach ($value as $item) {
            if (\is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function isItemRowList(mixed $raw): bool
    {
        if (!\is_array($raw)) {
            return false;
        }
        $i = 0;
        foreach ($raw as $k => $row) {
            if ($k !== $i || !\is_array($row)) {
                return false;
            }
            ++$i;
        }

        return true;
    }
}
