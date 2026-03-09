<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Build a QueryBuilder for admin/staff listing with optional filters.
     * Filters: status, event, user (buyer), date range (from, to), createdBy (owner)
     */
    public function findForAdminList(array $filters = [], int $page = 1, int $limit = 25): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->leftJoin('t.event', 'e')
            ->addSelect('e')
            ->leftJoin('t.createdBy', 'cb')
            ->addSelect('cb')
            ->orderBy('t.createdAt', 'DESC');

        if (!empty($filters['status'])) {
            $qb->andWhere('t.paymentStatus = :status')->setParameter('status', $filters['status']);
        }

        if (!empty($filters['event'])) {
            $qb->andWhere('e.id = :event')->setParameter('event', $filters['event']);
        }

        if (!empty($filters['user'])) {
            $qb->andWhere('u.id = :user')->setParameter('user', $filters['user']);
        }

        if (!empty($filters['from'])) {
            $qb->andWhere('t.createdAt >= :from')->setParameter('from', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $qb->andWhere('t.createdAt <= :to')->setParameter('to', $filters['to']);
        }

        if (!empty($filters['createdBy'])) {
            $qb->andWhere('cb.id = :createdBy')->setParameter('createdBy', $filters['createdBy']);
        }

        $firstResult = max(0, ($page - 1) * $limit);
        $qb->setFirstResult($firstResult)->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => count($paginator),
        ];
    }
}
