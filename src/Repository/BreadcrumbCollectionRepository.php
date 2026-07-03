<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;

/**
 * @extends ServiceEntityRepository<BreadcrumbCollection>
 */
class BreadcrumbCollectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BreadcrumbCollection::class);
    }

    public function findOneByCodeAndContextKey(string $code, string $contextKey = ''): ?BreadcrumbCollection
    {
        return $this->findOneBy(['code' => $code, 'contextKey' => $contextKey]);
    }

    public function createSearchQueryBuilder(string $search = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.code', 'ASC')
            ->addOrderBy('c.contextKey', 'ASC');

        if ('' !== $search) {
            $term = '%'.addcslashes($search, '%_').'%';
            $qb->andWhere('c.code LIKE :term OR c.name LIKE :term OR c.contextKey LIKE :term')
                ->setParameter('term', $term);
        }

        return $qb;
    }

    /**
     * @return list<BreadcrumbCollection>
     */
    public function findForDashboard(string $search = '', int $offset = 0, ?int $limit = null): array
    {
        $qb = $this->createSearchQueryBuilder($search);
        if (null !== $limit && $limit > 0) {
            $qb->setFirstResult($offset)->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countForDashboard(string $search = ''): int
    {
        $qb = $this->createSearchQueryBuilder($search)
            ->select('COUNT(c.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
