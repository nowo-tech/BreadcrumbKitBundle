<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Worker;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoaderInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Renders the `main` trail of the current request as JSON (labels, links, list class) for the worker tests.
 */
final readonly class WorkerTrailController
{
    public function __construct(private BreadcrumbLoaderInterface $loader)
    {
    }

    public function trail(): JsonResponse
    {
        $view = $this->loader->loadTrailView('main');
        $nodes = [];
        foreach ($view->nodes as $node) {
            $nodes[] = [$node->label, $node->url];
        }

        return new JsonResponse(['classList' => $view->classList, 'nodes' => $nodes]);
    }

    public function boom(): never
    {
        throw new RuntimeException('boom');
    }
}
