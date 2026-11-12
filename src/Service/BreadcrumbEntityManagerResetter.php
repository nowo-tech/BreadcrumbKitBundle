<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Worker\BreadcrumbOwnEntityManagerSubscriber;

/**
 * The EntityManager of the breadcrumb entities — `doctrine.entity_manager` when set (2.16), else the one that maps
 * them — and its replacement when a failed flush closed it. Never another manager.
 *
 * Without a kernel reset between requests (worker mode), a closed manager would otherwise break
 * every later dashboard write on the same worker. This class never clears the identity map; since 2.19.0
 * {@see BreadcrumbOwnEntityManagerSubscriber} clears the bundle's OWN manager (not
 * the host default) at the outermost unit-of-work boundaries.
 */
final readonly class BreadcrumbEntityManagerResetter
{
    public function __construct(
        private ManagerRegistry $registry,
        private ?string $managerName = null,
    ) {
    }

    /**
     * The bundle's manager (2.16). Lookup per call: a manager the registry reset is picked up.
     */
    public function entityManager(): EntityManagerInterface
    {
        $entityManager = null !== $this->managerName
            ? $this->registry->getManager($this->managerName)
            : $this->registry->getManagerForClass(BreadcrumbCollection::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('No Doctrine ORM entity manager maps '.BreadcrumbCollection::class.' (nowo_breadcrumb_kit.doctrine.entity_manager: '.($this->managerName ?? 'null').').');
        }

        return $entityManager;
    }

    public function resetIfClosed(): void
    {
        $entityManager = null !== $this->managerName
            ? $this->registry->getManager($this->managerName)
            : $this->registry->getManagerForClass(BreadcrumbCollection::class);
        if (!$entityManager instanceof EntityManagerInterface || $entityManager->isOpen()) {
            return;
        }

        if (null !== $this->managerName) {
            $this->registry->resetManager($this->managerName);

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
