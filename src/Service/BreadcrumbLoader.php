<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbTrailBuiltEvent;
use Nowo\BreadcrumbKitBundle\Profiler\BreadcrumbProfilerRecorder;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function loadTrailView(string $collectionCode, string $contextKey = ''): BreadcrumbTrailView
    {
        $request = $this->requestStack->getCurrentRequest();

        $collection = $this->collectionRepository->findOneByCodeAndContextKey($collectionCode, $contextKey);
        if (!$collection instanceof BreadcrumbCollection) {
            return $this->finishTrail(
                new BreadcrumbTrailView([]),
                $collectionCode,
                $contextKey,
                'collection_not_found',
                $request,
                null,
            );
        }

        if (!$request instanceof Request) {
            return $this->finishTrail(
                $this->emptyView($collection),
                $collectionCode,
                $contextKey,
                'no_http_request',
                null,
                null,
            );
        }

        $locale = $request->getLocale();
        $rows = $this->loadItemRows($collection);

        $currentRoute = $request->attributes->get('_route');
        $routeName = \is_scalar($currentRoute) && '' !== (string) $currentRoute ? (string) $currentRoute : '';
        /** @var array<string, scalar|null> $routeParams */
        $routeParams = (array) $request->attributes->get('_route_params', []);

        $best = $this->pickBestMatch($rows, $routeName, $routeParams, $request);
        if (null === $best) {
            return $this->finishTrail(
                $this->emptyView($collection),
                $collectionCode,
                $contextKey,
                '' === $routeName ? 'no_route' : 'no_item_match',
                $request,
                null,
            );
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
                $rowRoute = (string) ($row['route_name'] ?? '');
                if ('*' !== $rowRoute && '' !== $rowRoute) {
                    [$url, $merged] = $this->urlResolver->resolve(
                        $rowRoute,
                        \is_array($row['static_params']) ? $row['static_params'] : [],
                        $this->castDynamicKeys($row['dynamic_keys'] ?? null),
                    );
                }
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

        return $this->finishTrail(
            $view,
            $collectionCode,
            $contextKey,
            'ok',
            $request,
            isset($best['route_name']) ? (string) $best['route_name'] : null,
        );
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
        $routeName = \is_scalar($currentRoute) && '' !== (string) $currentRoute ? (string) $currentRoute : '';
        /** @var array<string, scalar|null> $routeParams */
        $routeParams = (array) $request->attributes->get('_route_params', []);

        $best = $this->pickBestMatch($rows, $routeName, $routeParams, $request);
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
    private function finishTrail(
        BreadcrumbTrailView $view,
        string $collectionCode,
        string $contextKey,
        string $status,
        ?Request $request,
        ?string $matchedItemRoute,
    ): BreadcrumbTrailView {
        $this->profile($collectionCode, $contextKey, $view, $status, $request, $matchedItemRoute);

        if (null === $this->eventDispatcher) {
            return $view;
        }

        $event = new BreadcrumbTrailBuiltEvent($view, $collectionCode, $contextKey, $status, $request, $matchedItemRoute);
        $this->eventDispatcher->dispatch($event);

        return $event->getView();
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
            'path_pattern' => $item->getPathPattern(),
            'match_attributes' => $item->getMatchAttributes() ?? [],
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
    private function pickBestMatch(array $rows, string $routeName, array $routeParams, Request $request): ?array
    {
        $candidates = [];
        foreach ($rows as $row) {
            if (!$this->rowMatchesRequest($row, $routeName, $routeParams, $request)) {
                continue;
            }
            $static = \is_array($row['static_params'] ?? null) ? $row['static_params'] : [];
            $matchAttrs = \is_array($row['match_attributes'] ?? null) ? $row['match_attributes'] : [];
            $pathPattern = isset($row['path_pattern']) && \is_string($row['path_pattern']) ? $row['path_pattern'] : '';
            $score = \count($static) + \count($matchAttrs) + ('' !== $pathPattern ? 10 : 0);
            $candidates[] = ['row' => $row, 'score' => $score];
        }

        if ([] === $candidates) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $candidates[0]['row'];
    }

    /**
     * @param array<string, mixed>       $row
     * @param array<string, scalar|null> $routeParams
     */
    private function rowMatchesRequest(array $row, string $routeName, array $routeParams, Request $request): bool
    {
        $rowRoute = (string) ($row['route_name'] ?? '');
        $pathPatternRaw = $row['path_pattern'] ?? null;
        $pathPattern = \is_string($pathPatternRaw) ? trim($pathPatternRaw) : '';
        $matchAttrs = \is_array($row['match_attributes'] ?? null) ? $row['match_attributes'] : [];

        $wildcard = '*' === $rowRoute;
        if ($wildcard && '' === $pathPattern && [] === $matchAttrs) {
            return false;
        }

        if (!$wildcard) {
            if ('' === $rowRoute || $rowRoute !== $routeName) {
                return false;
            }
        }

        if ('' !== $pathPattern && !$this->pathPatternMatches($pathPattern, $request->getPathInfo())) {
            return false;
        }

        if (!$this->staticParamsMatch(\is_array($row['static_params'] ?? null) ? $row['static_params'] : [], $routeParams)) {
            return false;
        }

        return $this->requestAttributesMatch($matchAttrs, $request);
    }

    private function pathPatternMatches(string $pattern, string $pathInfo): bool
    {
        $delimited = '#'.$pattern.'#u';
        $result = @preg_match($delimited, $pathInfo);
        if (false === $result) {
            return false;
        }

        return 1 === $result;
    }

    /**
     * @param array<string, scalar|null> $expected
     */
    private function requestAttributesMatch(array $expected, Request $request): bool
    {
        foreach ($expected as $key => $value) {
            if ('' === $key) {
                return false;
            }
            if (!$request->attributes->has($key)) {
                return false;
            }
            $actual = $request->attributes->get($key);
            if (!\is_scalar($actual) && null !== $actual) {
                return false;
            }
            if ((string) $actual !== (string) $value) {
                return false;
            }
        }

        return true;
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
