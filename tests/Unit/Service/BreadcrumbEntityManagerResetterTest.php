<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BreadcrumbEntityManagerResetterTest extends TestCase
{
    public function testOpenManagerIsNotReset(): void
    {
        $registry = $this->registry($this->entityManager(true));
        $registry->expects(self::never())->method('resetManager');

        (new BreadcrumbEntityManagerResetter($registry))->resetIfClosed();
    }

    public function testMissingOrmManagerIsIgnored(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(BreadcrumbCollection::class)->willReturn(null);
        $registry->expects(self::never())->method('resetManager');

        (new BreadcrumbEntityManagerResetter($registry))->resetIfClosed();
    }

    public function testClosedManagerIsResetByItsRegistryName(): void
    {
        $closed = $this->entityManager(false);
        $registry = $this->registry($closed);
        $registry->method('getManagerNames')->willReturn(['logs' => 'doctrine.orm.logs_entity_manager', 'default' => 'doctrine.orm.default_entity_manager']);
        $registry->method('getManager')->willReturnMap([
            ['logs', $this->entityManager(true)],
            ['default', $closed],
        ]);
        $registry->expects(self::once())->method('resetManager')->with('default');

        (new BreadcrumbEntityManagerResetter($registry))->resetIfClosed();
    }

    public function testClosedManagerUnknownToRegistryIsLeftAlone(): void
    {
        $registry = $this->registry($this->entityManager(false));
        $registry->method('getManagerNames')->willReturn([]);
        $registry->expects(self::never())->method('resetManager');

        (new BreadcrumbEntityManagerResetter($registry))->resetIfClosed();
    }

    private function registry(EntityManagerInterface $entityManager): ManagerRegistry&MockObject
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(BreadcrumbCollection::class)->willReturn($entityManager);

        return $registry;
    }

    private function entityManager(bool $open): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('isOpen')->willReturn($open);

        return $entityManager;
    }
}
