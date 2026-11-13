<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler\BreadcrumbEntityManagerPass;
use Nowo\BreadcrumbKitBundle\Doctrine\ExcludedEntityNamespaceDriver;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use PHPUnit\Framework\TestCase;

final class ExcludedEntityNamespaceDriverTest extends TestCase
{
    public function testMapsNothing(): void
    {
        $driver = new ExcludedEntityNamespaceDriver();

        self::assertTrue($driver->isTransient(BreadcrumbCollection::class));
        self::assertSame([], $driver->getAllClassNames());

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(BreadcrumbCollection::class);
        $driver->loadMetadataForClass(BreadcrumbCollection::class, new ClassMetadata(BreadcrumbCollection::class));
    }

    /**
     * A chain asks its namespace drivers before its default driver: a default driver that claims every entity class
     * no longer claims the breadcrumb entities.
     */
    public function testWinsOverTheDefaultDriverOfAChain(): void
    {
        $catchAll = $this->createStub(MappingDriver::class);
        $catchAll->method('isTransient')->willReturn(false);
        $chain = new MappingDriverChain();
        $chain->setDefaultDriver($catchAll);
        self::assertFalse($chain->isTransient(BreadcrumbCollection::class));

        $chain->addDriver(new ExcludedEntityNamespaceDriver(), BreadcrumbEntityManagerPass::ENTITY_NAMESPACE);

        self::assertTrue($chain->isTransient(BreadcrumbCollection::class));
        self::assertTrue($chain->isTransient(BreadcrumbItem::class));
        self::assertFalse($chain->isTransient(self::class), 'other classes still go to the default driver');
    }
}
