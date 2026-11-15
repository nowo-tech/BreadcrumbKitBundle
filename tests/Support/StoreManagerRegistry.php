<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Support;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\ObjectManager;

/**
 * A one-manager registry for {@see BreadcrumbStore} (the repositories ask their registry for the manager per call).
 */
final class StoreManagerRegistry extends AbstractManagerRegistry
{
    public function __construct(private readonly EntityManager $entityManager)
    {
        parent::__construct('orm', ['default' => 'connection'], ['default' => 'manager'], 'default', 'default', EntityManager::class);
    }

    protected function getService(string $name): object
    {
        return 'manager' === $name ? $this->entityManager : $this->entityManager->getConnection();
    }

    protected function resetService(string $name): void
    {
    }

    public function getAliasNamespace(string $alias): string
    {
        return $alias;
    }

    public function getManagerForClass(string $class): ObjectManager
    {
        return $this->entityManager;
    }
}
