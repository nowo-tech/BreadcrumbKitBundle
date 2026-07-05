<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;

/**
 * Applies configurable table prefix to breadcrumb entities at runtime.
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final readonly class TablePrefixSubscriber
{
    public function __construct(
        private string $tablePrefix,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        $class = $metadata->getName();

        if ('' === $this->tablePrefix || (BreadcrumbCollection::class !== $class && BreadcrumbItem::class !== $class)) {
            return;
        }

        $metadata->setPrimaryTable([
            'name' => $this->tablePrefix.$metadata->getTableName(),
        ]);
    }
}
