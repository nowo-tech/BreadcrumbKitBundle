<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;

/**
 * @extends ServiceEntityRepository<BreadcrumbItem>
 */
class BreadcrumbItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BreadcrumbItem::class);
    }

    /**
     * @return list<BreadcrumbItem>
     */
    public function findAllForCollection(BreadcrumbCollection $collection): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.collection = :c')
            ->setParameter('c', $collection)
            ->orderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dashboard list (ordered by id). Optional search by route, label, or numeric id.
     *
     * @return list<BreadcrumbItem>
     */
    public function findForDashboardList(
        BreadcrumbCollection $collection,
        ?string $search,
        int $offset = 0,
        ?int $limit = null,
    ): array {
        $qb = $this->createDashboardListQueryBuilder($collection, $search)
            ->orderBy('i.id', 'ASC')
            ->setFirstResult(max(0, $offset));

        if (null !== $limit) {
            $qb->setMaxResults(max(1, $limit));
        }

        /* @var list<BreadcrumbItem> */
        return $qb->getQuery()->getResult();
    }

    public function countForDashboardList(BreadcrumbCollection $collection, ?string $search): int
    {
        return (int) $this->createDashboardListQueryBuilder($collection, $search)
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createDashboardListQueryBuilder(BreadcrumbCollection $collection, ?string $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.collection = :c')
            ->setParameter('c', $collection);

        $search = null !== $search ? trim($search) : '';
        if ('' !== $search) {
            $like = '%'.mb_strtolower($search).'%';
            $orX = $qb->expr()->orX(
                'LOWER(i.routeName) LIKE :like',
                'LOWER(COALESCE(i.label, :emptyStr)) LIKE :like',
            );
            $qb->setParameter('emptyStr', '');
            $qb->setParameter('like', $like);
            if (ctype_digit($search)) {
                $orX->add('i.id = :id');
                $qb->setParameter('id', (int) $search);
            }
            $qb->andWhere($orX);
        }

        return $qb;
    }

    /**
     * @param list<int> $collectionIds
     *
     * @return array<int, int>
     */
    public function countForCollections(array $collectionIds): array
    {
        if ([] === $collectionIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.collection) AS collection_id, COUNT(i.id) AS item_count')
            ->where('i.collection IN (:collectionIds)')
            ->setParameter('collectionIds', $collectionIds)
            ->groupBy('i.collection')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $collectionId = isset($row['collection_id']) ? (int) $row['collection_id'] : 0;
            if ($collectionId <= 0) {
                continue;
            }
            $out[$collectionId] = isset($row['item_count']) ? (int) $row['item_count'] : 0;
        }

        return $out;
    }
}
