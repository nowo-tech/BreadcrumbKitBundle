<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Profiler;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Appends breadcrumb resolution snapshots to the current request (for Web Profiler).
 */
final readonly class BreadcrumbProfilerRecorder
{
    public const REQUEST_ATTRIBUTE = '_nowo_breadcrumb_kit_profiler';

    public function __construct(
        private RequestStack $requestStack,
        private bool $debug = false,
    ) {
    }

    /**
     * @param 'collection_not_found'|'no_http_request'|'no_route'|'no_item_match'|'ok' $status
     */
    public function record(
        string $collectionCode,
        string $contextKey,
        BreadcrumbTrailView $view,
        string $status,
        ?string $requestRoute = null,
        ?string $matchedItemRoute = null,
    ): void {
        if (!$this->debug) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $nodes = [];
        foreach ($view->nodes as $node) {
            $nodes[] = $this->serializeNode($node);
        }

        $snapshot = [
            'collection' => $collectionCode,
            'contextKey' => $contextKey,
            'status' => $status,
            'requestRoute' => $requestRoute,
            'matchedItemRoute' => $matchedItemRoute,
            'nodeCount' => \count($nodes),
            'nodes' => $nodes,
            'classList' => $view->classList,
            'separatorIcon' => $view->separatorIcon,
        ];

        /** @var list<array<string, mixed>> $log */
        $log = $request->attributes->get(self::REQUEST_ATTRIBUTE, []);
        if (!\is_array($log)) {
            $log = [];
        }
        $log[] = $snapshot;
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $log);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNode(BreadcrumbNode $node): array
    {
        return [
            'label' => $node->label,
            'url' => $node->url,
            'current' => $node->current,
            'linkEnabled' => $node->linkEnabled,
            'icon' => $node->icon,
            'routeParamKeys' => array_keys($node->routeParams),
        ];
    }
}
