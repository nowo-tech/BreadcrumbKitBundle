<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use SortDirection;

/**
 * @extends ServiceEntityRepository<BreadcrumbCollection>
 */
class BreadcrumbCollectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BreadcrumbCollection::class);
    }

    /**
     * Refreshes an already managed collection so edits made by another worker or process are
     * visible when the identity map survives between requests (worker mode without kernel reset).
     */
    public function findOneByCodeAndContextKey(string $code, string $contextKey = ''): ?BreadcrumbCollection
    {
        // No LIMIT before the exact match (2.20): a case-folding or PAD SPACE collation can match a look-alike key, and
        // the row it returned first would hide the exact one. The keys are compared with `===`.
        /** @var list<BreadcrumbCollection> $collections */
        $collections = $this->createQueryBuilder('c')
            ->andWhere('c.code = :code')
            ->andWhere('c.contextKey = :contextKey')
            ->setParameter('code', $code)
            ->setParameter('contextKey', $contextKey)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        foreach ($collections as $collection) {
            if ($collection->getCode() === $code && $collection->getContextKey() === $contextKey) {
                return $collection;
            }
        }

        return null;
    }

    public function createSearchQueryBuilder(string $search = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.code', SortDirection::Ascending)
            ->addOrderBy('c.contextKey', SortDirection::Ascending);

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
