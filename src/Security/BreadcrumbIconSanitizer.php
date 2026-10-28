<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Security;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;

/**
 * Allowlist sanitizer for editor-supplied breadcrumb icons (item `icon`, collection `homeIcon`).
 *
 * The themes print icons with `|raw` on public pages, so the stored value must never carry script. Accepted input:
 * plain text / emoji / icon tokens (e.g. `⌂`, `tabler:home`), inline SVG, and simple inline HTML such as
 * `<i class="bi bi-house"></i>`. The markup is parsed as XML and re-serialized from the DOM: only allowlisted
 * elements and attributes survive, text is escaped, comments and processing instructions are dropped. Other
 * elements, event handler attributes, non-fragment `href` values and script-capable URL values are rejected.
 *
 * Same rules as CookieConsentBundle's PreferencesBubbleIconSanitizer.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BreadcrumbIconSanitizer
{
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    private const XHTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

    /** SVG elements (lowercase local names) allowed in an icon. */
    private const SVG_ELEMENTS = [
        'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon', 'title', 'desc',
        'defs', 'symbol', 'use', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'text', 'tspan',
    ];

    /** Inline HTML elements (lowercase) allowed in an icon (icon-font `<i>`, emoji `<span>`). */
    private const HTML_ELEMENTS = ['span', 'i', 'b', 'strong', 'em', 'small'];

    /** Attributes (lowercase) kept on allowed elements; anything else (except dangerous ones, which throw) is dropped. */
    private const ATTRIBUTES = [
        'class', 'id', 'role', 'aria-hidden', 'aria-label', 'focusable', 'viewbox', 'width', 'height', 'version',
        'preserveaspectratio', 'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity',
        'clip-rule', 'clip-path', 'mask', 'd', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'x', 'y', 'x1', 'y1',
        'x2', 'y2', 'dx', 'dy', 'points', 'transform', 'opacity', 'offset', 'stop-color', 'stop-opacity',
        'gradientunits', 'gradienttransform', 'spreadmethod', 'maskunits', 'clippathunits', 'vector-effect',
        'shape-rendering', 'text-anchor', 'font-size', 'font-family', 'font-weight', 'color', 'href',
    ];

    /**
     * @return string|null sanitized markup, or null when empty
     *
     * @throws InvalidArgumentException when the markup is malformed or contains disallowed content
     */
    public static function sanitize(?string $icon): ?string
    {
        if (null === $icon) {
            return null;
        }
        $icon = trim($icon);
        if ('' === $icon) {
            return null;
        }

        $document = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML('<root xmlns:xlink="'.self::XLINK_NAMESPACE.'">'.$icon.'</root>', \LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        if (!$loaded || !$document->documentElement instanceof DOMElement) {
            throw new InvalidArgumentException('Invalid breadcrumb icon markup.');
        }

        $sanitized = trim(self::serializeChildren($document->documentElement));

        return '' !== $sanitized ? $sanitized : null;
    }

    /**
     * Like {@see sanitize()} but returns null instead of throwing (render-time guard for stored values).
     */
    public static function sanitizeOrNull(?string $icon): ?string
    {
        try {
            return self::sanitize($icon);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * True when the value is empty or passes {@see sanitize()} (form / import validation).
     */
    public static function isAllowed(?string $icon): bool
    {
        try {
            self::sanitize($icon);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private static function serializeChildren(DOMNode $parent): string
    {
        $out = '';
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $out .= self::serializeElement($child);
            } elseif ($child instanceof DOMText) {
                // Text and CDATA sections are emitted as escaped text.
                $out .= self::escape($child->data);
            }
            // Comments and processing instructions are dropped.
        }

        return $out;
    }

    private static function serializeElement(DOMElement $element): string
    {
        $name = (string) $element->localName;
        $lowerName = strtolower($name);
        $namespace = $element->namespaceURI;

        $isSvg = \in_array($lowerName, self::SVG_ELEMENTS, true)
            && (null === $namespace || self::SVG_NAMESPACE === $namespace);
        $isHtml = \in_array($lowerName, self::HTML_ELEMENTS, true)
            && (null === $namespace || self::XHTML_NAMESPACE === $namespace);

        if (!$isSvg && !$isHtml) {
            throw new InvalidArgumentException('Invalid breadcrumb icon markup.');
        }

        $out = '<'.$name;
        if ('svg' === $lowerName && self::SVG_NAMESPACE === $namespace) {
            $out .= ' xmlns="'.self::SVG_NAMESPACE.'"';
        }

        foreach ($element->attributes as $attribute) {
            $attributeName = strtolower((string) $attribute->localName);
            $value = $attribute->value;

            if (str_starts_with($attributeName, 'on') || self::isDangerousValue($value)) {
                throw new InvalidArgumentException('Invalid breadcrumb icon markup.');
            }

            $isXlink = self::XLINK_NAMESPACE === $attribute->namespaceURI;
            if (null !== $attribute->namespaceURI && !$isXlink) {
                continue;
            }
            if (!\in_array($attributeName, self::ATTRIBUTES, true)) {
                continue;
            }
            if ('href' === $attributeName && 1 !== preg_match('/^#[A-Za-z_][\w.:-]*$/', $value)) {
                throw new InvalidArgumentException('Invalid breadcrumb icon markup.');
            }

            $out .= ' '.($isXlink ? 'xlink:' : '').$attribute->localName.'="'.self::escape($value).'"';
        }

        $children = self::serializeChildren($element);
        if ('' === $children && $isSvg) {
            return $out.'/>';
        }

        return $out.'>'.$children.'</'.$name.'>';
    }

    /**
     * Rejects script-capable URL schemes and url() references that are not local fragments.
     */
    private static function isDangerousValue(string $value): bool
    {
        $normalized = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $value));

        foreach (['javascript:', 'vbscript:', 'data:', 'expression('] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        if (str_contains($normalized, 'url(')) {
            $all = preg_match_all('/url\(/', $normalized);
            $local = preg_match_all('/url\([\'"]?#[\w.:-]+[\'"]?\)/', $normalized);

            return $all !== $local;
        }

        return false;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
