<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Testing;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JsonException;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads breadcrumb rows as a map "route name => parent route name (null for a root)" for
 * {@see BreadcrumbRouteCoverageTrait}, from the database or from a fixture file.
 *
 * Accepted data shapes (JSON or YAML): the dashboard / `nowo_breadcrumb_kit:export` shape (`{collection, items}` with
 * nested `children`, or `{collections: [...]}`, or a list of `{collection, items}` blocks), and flat item lists whose
 * rows name their parent route (`{items: [{route, parent}]}` or `[{routeName, parent}]`). An item names its route in
 * `routeName` or `route`. A route that appears in several rows (several path patterns) keeps its first parent.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BreadcrumbRows
{
    /**
     * Rows stored in the bundle tables, optionally limited to one collection code.
     *
     * @return array<string, string|null>
     */
    public static function fromEntityManager(EntityManagerInterface $entityManager, ?string $collectionCode = null): array
    {
        $qb = $entityManager->createQueryBuilder()
            ->select('i')
            ->from(BreadcrumbItem::class, 'i')
            ->orderBy('i.id', 'ASC');
        if (null !== $collectionCode) {
            $qb->innerJoin('i.collection', 'c')->andWhere('c.code = :code')->setParameter('code', $collectionCode);
        }

        $rows = [];
        /** @var list<BreadcrumbItem> $items */
        $items = $qb->getQuery()->getResult();
        foreach ($items as $item) {
            $route = $item->getRouteName();
            if ('' !== $route && !\array_key_exists($route, $rows)) {
                $parent = $item->getParent()?->getRouteName();
                $rows[$route] = '' !== $parent ? $parent : null;
            }
        }

        return $rows;
    }

    /**
     * Rows of a JSON (`.json`) or YAML (`.yaml`, `.yml`) fixture file.
     *
     * @return array<string, string|null>
     */
    public static function fromFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(\sprintf('Breadcrumb fixture "%s" is not a readable file.', $path));
        }
        $raw = (string) file_get_contents($path);
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        try {
            $data = \in_array($extension, ['yaml', 'yml'], true)
                ? Yaml::parse($raw)
                : json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException|ParseException $e) {
            throw new InvalidArgumentException(\sprintf('Breadcrumb fixture "%s" cannot be parsed: %s', $path, $e->getMessage()), 0, $e);
        }
        if (!\is_array($data)) {
            throw new InvalidArgumentException(\sprintf('Breadcrumb fixture "%s" must hold an object or a list.', $path));
        }

        return self::fromArray($data);
    }

    /**
     * Rows of decoded fixture data (see the class description for the shapes).
     *
     * @param array<mixed> $data
     *
     * @return array<string, string|null>
     */
    public static function fromArray(array $data): array
    {
        $rows = [];
        foreach (self::itemLists($data) as $items) {
            self::collect($items, null, $rows);
        }

        return $rows;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    private static function itemLists(array $data): array
    {
        if (isset($data['collections']) && \is_array($data['collections'])) {
            $data = $data['collections'];
        }
        if (isset($data['items']) && \is_array($data['items'])) {
            return [$data['items']];
        }

        $lists = [];
        foreach ($data as $block) {
            if (\is_array($block) && isset($block['items']) && \is_array($block['items'])) {
                $lists[] = $block['items'];
            }
        }

        // A plain list of items.
        return [] !== $lists ? $lists : [$data];
    }

    /**
     * @param array<mixed>               $items
     * @param array<string, string|null> $rows
     */
    private static function collect(array $items, ?string $nestedParent, array &$rows): void
    {
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $route = self::stringValue($item['routeName'] ?? $item['route'] ?? null);
            $parent = self::stringValue($item['parent'] ?? null) ?? $nestedParent;
            if (null !== $route && !\array_key_exists($route, $rows)) {
                $rows[$route] = $parent;
            }
            if (isset($item['children']) && \is_array($item['children'])) {
                self::collect($item['children'], $route ?? $nestedParent, $rows);
            }
        }
    }

    private static function stringValue(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
