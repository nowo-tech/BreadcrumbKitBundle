<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;

/**
 * JSON export of breadcrumb collections and item trees (no entity ids in items).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class BreadcrumbExporter
{
    public function __construct(
        private BreadcrumbCollectionRepository $collectionRepository,
        private BreadcrumbItemRepository $itemRepository,
    ) {
    }

    /**
     * @return array{collection: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function exportCollection(BreadcrumbCollection $collection): array
    {
        $items = $this->itemRepository->findAllForCollection($collection);
        $tree = $this->buildItemTree($items);

        return [
            'items' => $tree,
            'collection' => $this->collectionToArray($collection),
        ];
    }

    /**
     * @return array{collections: list<array{collection: array<string, mixed>, items: list<array<string, mixed>>}>}
     */
    public function exportAll(): array
    {
        $collections = $this->collectionRepository->findBy([], ['code' => 'ASC', 'contextKey' => 'ASC']);
        $out = [];
        foreach ($collections as $col) {
            $out[] = $this->exportCollection($col);
        }

        return ['collections' => $out];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionToArray(BreadcrumbCollection $collection): array
    {
        return [
            'code' => $collection->getCode(),
            'contextKey' => $collection->getContextKey(),
            'name' => $collection->getName(),
            'homeIcon' => $collection->getHomeIcon(),
            'separatorIcon' => $collection->getSeparatorIcon(),
            'responsiveConfig' => $collection->getResponsiveConfig(),
            'classList' => $collection->getClassList(),
            'classItem' => $collection->getClassItem(),
            'classSeparator' => $collection->getClassSeparator(),
            'classCurrent' => $collection->getClassCurrent(),
            'inlineEditAccessKey' => $collection->getInlineEditAccessKey(),
        ];
    }

    /**
     * @param list<BreadcrumbItem> $flatItems
     *
     * @return list<array<string, mixed>>
     */
    private function buildItemTree(array $flatItems): array
    {
        /** @var array<string, list<BreadcrumbItem>> $byParent */
        $byParent = [];
        foreach ($flatItems as $item) {
            $pid = $item->getParent()?->getId();
            $key = null !== $pid ? (string) $pid : '__root';
            if (!isset($byParent[$key])) {
                $byParent[$key] = [];
            }
            $byParent[$key][] = $item;
        }

        $build = function (?int $parentId) use (&$build, $byParent): array {
            $key = null !== $parentId ? (string) $parentId : '__root';
            $rows = $byParent[$key] ?? [];
            $out = [];
            foreach ($rows as $item) {
                $node = $this->itemToArray($item);
                $children = $build($item->getId());
                if ([] !== $children) {
                    $node['children'] = $children;
                }
                $out[] = $node;
            }

            return $out;
        };

        return $build(null);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemToArray(BreadcrumbItem $item): array
    {
        $data = [
            'routeName' => $item->getRouteName(),
            'staticRouteParams' => $item->getStaticRouteParams() ?? [],
            'dynamicParamKeys' => $item->getDynamicParamKeys() ?? [],
            'linkEnabled' => $item->isLinkEnabled(),
            'label' => $item->getLabel(),
            'translations' => $item->getTranslations(),
            'icon' => $item->getIcon(),
        ];

        return $this->normalizeAssociativeForExport($data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeAssociativeForExport(array $data): array
    {
        foreach ($data as $k => $v) {
            if (null === $v) {
                unset($data[$k]);
            } elseif (\is_array($v) && [] === $v) {
                unset($data[$k]);
            } elseif (\is_bool($v) && false === $v && \in_array($k, ['linkEnabled'], true)) {
                $data[$k] = false;
            }
        }

        return $data;
    }
}
