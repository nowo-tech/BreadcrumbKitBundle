<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Transaction;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ToManyAssociationMapping;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel\Entity\HostNote;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbFlushObserver;
use PHPUnit\Framework\TestCase;
use stdClass;

final class BreadcrumbFlushObserverTest extends TestCase
{
    public function testBreadcrumbEntitiesAndTheirProxiesAreRecognised(): void
    {
        self::assertTrue(BreadcrumbFlushObserver::isBreadcrumbEntity(BreadcrumbItem::class));
        self::assertTrue(BreadcrumbFlushObserver::isBreadcrumbEntity('Proxies\\__CG__\\Nowo\\BreadcrumbKitBundle\\Entity\\BreadcrumbItem'));
        self::assertFalse(BreadcrumbFlushObserver::isBreadcrumbEntity(HostNote::class));
    }

    public function testAFlushOfBreadcrumbRowsOnlyIsNotAHostWrite(): void
    {
        $observer = new BreadcrumbFlushObserver();
        $observer->onFlush($this->args([new BreadcrumbCollection()], [new BreadcrumbItem()], [new BreadcrumbItem()]));

        self::assertFalse($observer->hostWritten);
    }

    public function testAnEntityOfTheApplicationIsAHostWrite(): void
    {
        $observer = new BreadcrumbFlushObserver();
        $observer->onFlush($this->args([new BreadcrumbCollection()], [], [new HostNote('x')]));

        self::assertTrue($observer->hostWritten);
    }

    public function testACollectionOfTheApplicationIsAHostWrite(): void
    {
        $host = $this->collection(new stdClass());
        $observer = new BreadcrumbFlushObserver();
        $observer->onFlush($this->args([], [], [], [$host]));

        self::assertTrue($observer->hostWritten);

        $unowned = $this->collection(null);
        $observer = new BreadcrumbFlushObserver();
        $observer->onFlush($this->args([], [], [], [], [$unowned]));

        self::assertTrue($observer->hostWritten, 'a collection without an owner cannot be shown to be the bundle\'s');
    }

    public function testACollectionOfABreadcrumbRowIsNotAHostWrite(): void
    {
        $own = $this->collection(new BreadcrumbCollection());
        $observer = new BreadcrumbFlushObserver();
        $observer->onFlush($this->args([], [], [], [$own], [$own]));

        self::assertFalse($observer->hostWritten);
    }

    /**
     * @return PersistentCollection<int, object>
     */
    private function collection(?object $owner): PersistentCollection
    {
        $manager = (new BreadcrumbStore())->entityManager;
        $collection = new PersistentCollection($manager, $manager->getClassMetadata(BreadcrumbItem::class), new ArrayCollection());
        if (null !== $owner) {
            $mapping = $manager->getClassMetadata(BreadcrumbCollection::class)->associationMappings['items'];
            \assert($mapping instanceof ToManyAssociationMapping);
            $collection->setOwner($owner, $mapping);
        }

        return $collection;
    }

    /**
     * @param list<object>                                  $insertions
     * @param list<object>                                  $updates
     * @param list<object>                                  $deletions
     * @param list<PersistentCollection<array-key, object>> $collectionUpdates
     * @param list<PersistentCollection<array-key, object>> $collectionDeletions
     */
    private function args(array $insertions, array $updates, array $deletions, array $collectionUpdates = [], array $collectionDeletions = []): OnFlushEventArgs
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn($insertions);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn($updates);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn($deletions);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn($collectionUpdates);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn($collectionDeletions);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);

        return new OnFlushEventArgs($manager);
    }
}
