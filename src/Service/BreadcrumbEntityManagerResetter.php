<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;

/**
 * Replaces the EntityManager that maps the bundle entities when a failed flush closed it.
 *
 * Without a kernel reset between requests (worker mode), a closed manager would otherwise break
 * every later dashboard write on the same worker. The identity map is never cleared.
 */
final readonly class BreadcrumbEntityManagerResetter
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    public function resetIfClosed(): void
    {
        $entityManager = $this->registry->getManagerForClass(BreadcrumbCollection::class);
        if (!$entityManager instanceof EntityManagerInterface || $entityManager->isOpen()) {
            return;
        }

        foreach (array_keys($this->registry->getManagerNames()) as $name) {
            if ($this->registry->getManager($name) === $entityManager) {
                $this->registry->resetManager($name);

                return;
            }
        }
    }
}
