<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DataCollector;

use Nowo\BreadcrumbKitBundle\Profiler\BreadcrumbProfilerRecorder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\TemplateAwareDataCollectorInterface;

/**
 * Web Profiler panel: last breadcrumb resolutions ({@see BreadcrumbProfilerRecorder}).
 */
final class BreadcrumbDataCollector extends DataCollector implements TemplateAwareDataCollectorInterface
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        /** @var list<array<string, mixed>> $trails */
        $trails = $request->attributes->get(BreadcrumbProfilerRecorder::REQUEST_ATTRIBUTE, []);
        if (!\is_array($trails)) {
            $trails = [];
        }

        $this->data = [
            'trails' => $trails,
            'snapshot_count' => \count($trails),
            'last_node_count' => [] !== $trails ? (int) ($trails[array_key_last($trails)]['nodeCount'] ?? 0) : 0,
        ];
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getName(): string
    {
        return 'nowo_breadcrumb_kit';
    }

    public static function getTemplate(): string
    {
        return '@NowoBreadcrumbKitBundle/Collector/breadcrumb.html.twig';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTrails(): array
    {
        return $this->data['trails'] ?? [];
    }

    public function getSnapshotCount(): int
    {
        return $this->data['snapshot_count'] ?? 0;
    }

    public function getLastNodeCount(): int
    {
        return $this->data['last_node_count'] ?? 0;
    }
}
