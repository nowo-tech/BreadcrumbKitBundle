<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\EventSubscriber\TablePrefixSubscriber;
use PHPUnit\Framework\TestCase;

final class TablePrefixSubscriberTest extends TestCase
{
    public function testAppliesPrefixToBreadcrumbEntities(): void
    {
        $metadata = new ClassMetadata(BreadcrumbCollection::class);
        $metadata->setPrimaryTable(['name' => 'dashboard_breadcrumb_collection']);

        $subscriber = new TablePrefixSubscriber('app_');
        $subscriber->loadClassMetadata($this->createEventArgs($metadata));

        self::assertSame('app_dashboard_breadcrumb_collection', $metadata->getTableName());
    }

    public function testAppliesPrefixToBreadcrumbItemEntity(): void
    {
        $metadata = new ClassMetadata(BreadcrumbItem::class);
        $metadata->setPrimaryTable(['name' => 'dashboard_breadcrumb_item']);

        $subscriber = new TablePrefixSubscriber('tenant_');
        $subscriber->loadClassMetadata($this->createEventArgs($metadata));

        self::assertSame('tenant_dashboard_breadcrumb_item', $metadata->getTableName());
    }

    public function testIgnoresOtherEntities(): void
    {
        $metadata = new ClassMetadata(\stdClass::class);
        $metadata->setPrimaryTable(['name' => 'other_table']);

        $subscriber = new TablePrefixSubscriber('app_');
        $subscriber->loadClassMetadata($this->createEventArgs($metadata));

        self::assertSame('other_table', $metadata->getTableName());
    }

    public function testSkipsWhenPrefixEmpty(): void
    {
        $metadata = new ClassMetadata(BreadcrumbCollection::class);
        $metadata->setPrimaryTable(['name' => 'dashboard_breadcrumb_collection']);

        $subscriber = new TablePrefixSubscriber('');
        $subscriber->loadClassMetadata($this->createEventArgs($metadata));

        self::assertSame('dashboard_breadcrumb_collection', $metadata->getTableName());
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function createEventArgs(ClassMetadata $metadata): LoadClassMetadataEventArgs
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        return new LoadClassMetadataEventArgs($metadata, $entityManager);
    }
}
