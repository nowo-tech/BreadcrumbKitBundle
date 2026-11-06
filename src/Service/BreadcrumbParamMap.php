<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Per-item route parameters taken from differently named parameters of the current route.
 *
 * Stored as entries of the item's `dynamicParamKeys` (no schema change) and written `params` in the collection JSON:
 *
 *     "id=@projectId"                                   {"id": "@projectId"}
 *     "id=@projectId,@spaceSlug|before:--"              {"id": ["@projectId", "@spaceSlug|before:--"]}
 *
 * The first source holding a value wins (up to 16 sources); `|before:<separator>` keeps what precedes the separator. A
 * source reads the current `_route_params`, then a request attribute only if `param_map.attributes` declares its name. An
 * entry that does not have this exact shape is a plain parameter name, as it always was.
 */
final class BreadcrumbParamMap
{
    // Names start with a letter: `_route`, `_controller`, `_firewall_context`… and the generator's `_fragment`, `_scheme`… are out of reach.
    private const NAME = '[a-zA-Z][a-zA-Z0-9_]{0,63}';
    private const SOURCE = '@'.self::NAME.'(?:\|before:[^\s,|]{1,16})?';
    private const ENTRY = '/^('.self::NAME.')=('.self::SOURCE.'(?:,'.self::SOURCE.'){0,15})$/D';
    private const BEFORE = '|before:';

    /**
     * Entries that are not strings are skipped: the column is free JSON and may hold anything written around the form.
     *
     * @param array<mixed>|null $keys the item's dynamicParamKeys
     *
     * @return array{0: list<string>|null, 1: array<string, list<string>>} plain names (null stays null), target => sources
     */
    public static function split(?array $keys): array
    {
        if (null === $keys) {
            return [null, []];
        }
        $plain = [];
        $map = [];
        foreach ($keys as $key) {
            if (!\is_string($key)) {
                continue;
            }
            if (1 === preg_match(self::ENTRY, $key, $match)) {
                $map[$match[1]] ??= explode(',', $match[2]);
            } else {
                $plain[] = $key;
            }
        }

        return [$plain, $map];
    }

    /**
     * @param array<string, list<string>> $map        target => sources
     * @param list<string>                $attributes request attributes a source may read (`param_map.attributes`)
     *
     * @return array<string, int|string> target => value, for the targets with a value in this request
     */
    public static function resolve(array $map, Request $request, array $attributes = []): array
    {
        $params = [];
        foreach ($map as $target => $sources) {
            foreach ($sources as $source) {
                $value = self::read($source, $request, $attributes);
                if (null !== $value) {
                    $params[$target] = $value;

                    break;
                }
            }
        }

        return $params;
    }

    /**
     * Whether a name can be used as a target or a source.
     */
    public static function isName(mixed $name): bool
    {
        return \is_string($name) && 1 === preg_match('/^'.self::NAME.'$/D', $name);
    }

    /**
     * Stored entry for one `params` pair of the collection JSON.
     *
     * @return string|null null when the pair is not valid
     */
    public static function encode(int|string $target, mixed $sources): ?string
    {
        $sources = \is_string($sources) ? [$sources] : $sources;
        if (!\is_string($target) || !\is_array($sources) || [] === $sources) {
            return null;
        }
        foreach ($sources as $source) {
            if (!\is_string($source)) {
                return null;
            }
        }
        $entry = $target.'='.implode(',', $sources);

        return 1 === preg_match(self::ENTRY, $entry) ? $entry : null;
    }

    /**
     * `params` of the collection JSON (the reverse of {@see encode()}).
     *
     * @param array<string, list<string>> $map target => sources
     *
     * @return array<string, string|list<string>>
     */
    public static function export(array $map): array
    {
        return array_map(static fn (array $sources): array|string => 1 === \count($sources) ? $sources[0] : $sources, $map);
    }

    /**
     * @param list<string> $attributes
     */
    private static function read(string $source, Request $request, array $attributes): int|string|null
    {
        $parts = explode(self::BEFORE, substr($source, 1), 2);
        $name = $parts[0];
        $separator = $parts[1] ?? null;
        $routeParams = (array) $request->attributes->get('_route_params', []);
        $value = $routeParams[$name] ?? null;
        if (null === $value && \in_array($name, $attributes, true)) {
            $value = $request->attributes->get($name);
        }
        if (\is_int($value)) {
            return $value;
        }
        if (!\is_string($value)) {
            return null;
        }
        if (null !== $separator) {
            $cut = strstr($value, $separator, true);
            $value = false === $cut ? $value : $cut;
        }

        return '' === $value ? null : $value;
    }
}
