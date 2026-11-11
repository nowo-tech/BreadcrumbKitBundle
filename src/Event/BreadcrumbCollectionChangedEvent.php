<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per breadcrumb collection whose definition changed (the collection or one of its items was created,
 * updated or deleted) after the change was committed (2.16, architecture bar rules 4 and 7): dashboard CRUD, imports
 * and any custom code that flushes breadcrumb entities. Ids and scalars only — reload the definition, warm a cache or
 * write an outbox row from it.
 *
 * When the flush runs inside an outer transaction, the event waits for the end of that transaction (the importer
 * dispatches it right after its commit); a rolled-back transaction drops it at the end of the request.
 */
final class BreadcrumbCollectionChangedEvent extends Event
{
    public function __construct(
        public readonly int $collectionId,
        public readonly string $collectionCode,
        public readonly string $contextKey,
        /** True when the collection itself was deleted. */
        public readonly bool $deleted = false,
    ) {
    }
}
