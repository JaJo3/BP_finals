<?php

namespace App\Repository;

use App\Entity\Organizer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organizer>
 */
class OrganizerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organizer::class);
    }

    /**
     * @return array{items: Organizer[], total: int}
     */
    public function findPaginated(
        ?string $query = null,
        int $page = 1,
        int $limit = 10
    ): array {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.id', 'ASC');

        if ($query) {
            $qb->andWhere('o.org_name LIKE :q OR o.email LIKE :q OR o.contact LIKE :q')
               ->setParameter('q', '%' . $query . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
