<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Testing;

use Closure;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Route;

/**
 * Decides which routes are HTML pages that need a breadcrumb row (used by {@see BreadcrumbRouteCoverageTrait}).
 *
 * A route is a non-page, and skipped, when any of these holds:
 *
 * - it cannot answer GET (methods set without GET);
 * - its name starts with `_` or its path with `/_` (profiler, web debug toolbar, error previews);
 * - it carries the route option `breadcrumb: false` (declarative opt-out on the route itself);
 * - it has a `_format` requirement or a non-HTML `_format` default, or a `.{…}` placeholder in its path;
 * - its path ends with a file extension of {@see self::DEFAULT_EXTENSIONS} (downloads, feeds, manifests);
 * - its controller is Symfony's `RedirectController` (YAML `redirect` / `urlRedirect` routes);
 * - its controller method declares a JSON, redirect, file or streamed response return type (reflection);
 * - its name matches `$ignoredRoutes`, its path matches `$ignoredPaths` (`fnmatch()` patterns: `*`, `?`, `[…]`);
 * - the `$isNonPage` predicate returns true for it.
 *
 * The rules only read the route definition: nothing is requested, so redirect-only or JSON routes whose controller
 * declares a plain `Response` must be listed in `$ignoredRoutes`, opted out with `breadcrumb: false` or caught by
 * `$isNonPage`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class NonPageRouteFilter
{
    /** Path extensions of non-HTML answers. */
    public const DEFAULT_EXTENSIONS = [
        'atom', 'css', 'csv', 'gif', 'ico', 'ics', 'jpeg', 'jpg', 'js', 'json', 'map', 'md', 'ods', 'pdf', 'png',
        'rss', 'svg', 'txt', 'webmanifest', 'webp', 'xls', 'xlsx', 'xml', 'yaml', 'yml', 'zip',
    ];

    /** Controller return types that are never an HTML page. */
    public const NON_PAGE_RESPONSE_CLASSES = [
        JsonResponse::class,
        RedirectResponse::class,
        BinaryFileResponse::class,
        StreamedResponse::class,
    ];

    /** Route option that opts a route out of the coverage check when set to false. */
    public const ROUTE_OPTION = 'breadcrumb';

    /** @var (Closure(string, Route): bool)|null */
    private ?Closure $isNonPage;

    /**
     * @param list<string>                         $ignoredRoutes                route names or `fnmatch()` patterns that are not pages
     * @param list<string>                         $ignoredPaths                 route paths or `fnmatch()` patterns that are not pages (e.g. `/api/*`)
     * @param list<string>                         $nonPageExtensions            path extensions that are downloads or machine answers
     * @param bool                                 $inspectControllerReturnTypes skip routes whose controller declares a non-page response
     * @param (callable(string, Route): bool)|null $isNonPage                    extra predicate: return true to skip the route
     */
    public function __construct(
        private array $ignoredRoutes = [],
        private array $ignoredPaths = [],
        private array $nonPageExtensions = self::DEFAULT_EXTENSIONS,
        private bool $inspectControllerReturnTypes = true,
        ?callable $isNonPage = null,
    ) {
        $this->isNonPage = null !== $isNonPage ? $isNonPage(...) : null;
    }

    /**
     * True when the route is an HTML page that needs a breadcrumb row.
     */
    public function isPage(string $name, Route $route): bool
    {
        return null === $this->nonPageReason($name, $route);
    }

    /**
     * Why the route is skipped, or null when it is a page (for debugging a filter).
     */
    public function nonPageReason(string $name, Route $route): ?string
    {
        $methods = $route->getMethods();
        $path = $route->getPath();

        return match (true) {
            [] !== $methods && !\in_array('GET', $methods, true) => 'not a GET route',
            str_starts_with($name, '_') || str_starts_with($path, '/_') => 'internal route',
            false === $route->getOption(self::ROUTE_OPTION) => 'route option "breadcrumb" is false',
            self::hasNonHtmlFormat($route) => 'format requirement',
            $this->hasNonPageExtension($path) => 'file extension',
            self::isRedirectController($route->getDefault('_controller')) => 'redirect controller',
            $this->inspectControllerReturnTypes && self::returnsNonPageResponse($route->getDefault('_controller')) => 'non-page response type',
            self::matchesAny($name, $this->ignoredRoutes) => 'ignored route name',
            self::matchesAny($path, $this->ignoredPaths) => 'ignored route path',
            null !== $this->isNonPage && ($this->isNonPage)($name, $route) => 'custom predicate',
            default => null,
        };
    }

    /**
     * A copy with more ignored route names or patterns.
     *
     * @param list<string> $routes
     */
    public function withIgnoredRoutes(array $routes): self
    {
        return new self([...$this->ignoredRoutes, ...$routes], $this->ignoredPaths, $this->nonPageExtensions, $this->inspectControllerReturnTypes, $this->isNonPage);
    }

    /**
     * A copy with more ignored route paths or patterns.
     *
     * @param list<string> $paths
     */
    public function withIgnoredPaths(array $paths): self
    {
        return new self($this->ignoredRoutes, [...$this->ignoredPaths, ...$paths], $this->nonPageExtensions, $this->inspectControllerReturnTypes, $this->isNonPage);
    }

    private static function hasNonHtmlFormat(Route $route): bool
    {
        if ($route->hasRequirement('_format') || str_contains($route->getPath(), '.{')) {
            return true;
        }
        $format = $route->getDefault('_format');

        return \is_string($format) && '' !== $format && 'html' !== $format;
    }

    private function hasNonPageExtension(string $path): bool
    {
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        return '' !== $extension && !str_contains($extension, '}') && \in_array($extension, $this->nonPageExtensions, true);
    }

    private static function isRedirectController(mixed $controller): bool
    {
        return \is_string($controller) && str_starts_with($controller, RedirectController::class);
    }

    private static function returnsNonPageResponse(mixed $controller): bool
    {
        if (!\is_string($controller) || '' === $controller) {
            return false;
        }
        [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
        if (!class_exists($class)) {
            return false;
        }
        try {
            $type = (new ReflectionMethod($class, $method))->getReturnType();
        } catch (ReflectionException) {
            return false;
        }
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }
        $returned = $type->getName();

        foreach (self::NON_PAGE_RESPONSE_CLASSES as $nonPage) {
            if (is_a($returned, $nonPage, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $patterns
     */
    private static function matchesAny(string $subject, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $subject || fnmatch($pattern, $subject)) {
                return true;
            }
        }

        return false;
    }
}
