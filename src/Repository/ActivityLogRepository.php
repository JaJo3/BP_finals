<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTime;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find logs with optional filters
     */
    public function findForAdminList(array $filters = [], int $page = 1, int $limit = 25): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.createdAt', 'DESC');

        // Filter by user
        if (!empty($filters['user'])) {
            $qb->andWhere('u.username LIKE :user OR u.email LIKE :user')
                ->setParameter('user', '%' . $filters['user'] . '%');
        }

        // Filter by action
        if (!empty($filters['action'])) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $filters['action']);
        }

        // Filter by entity type
        if (!empty($filters['entityType'])) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $filters['entityType']);
        }

        // Filter by date range
        if (!empty($filters['dateFrom'])) {
            $fromDate = new DateTime($filters['dateFrom']);
            $qb->andWhere('a.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $fromDate);
        }

        if (!empty($filters['dateTo'])) {
            $toDate = new DateTime($filters['dateTo']);
            $toDate->modify('+1 day'); // Include entire day
            $qb->andWhere('a.createdAt < :dateTo')
                ->setParameter('dateTo', $toDate);
        }

        // Calculate offset and get total count
        $total = count($qb->getQuery()->getResult());
        $offset = ($page - 1) * $limit;

        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        return [
            'items' => $qb->getQuery()->getResult(),
            'total' => $total,
        ];
    }

    /**
     * Get available actions for filtering
     */
    public function getAvailableActions(): array
    {
        return [
            ActivityLog::ACTION_CREATE,
            ActivityLog::ACTION_UPDATE,
            ActivityLog::ACTION_DELETE,
            ActivityLog::ACTION_LOGIN,
            ActivityLog::ACTION_LOGOUT,
        ];
    }

    /**
     * Get available entity types for filtering
     */
    public function getAvailableEntityTypes(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT a.entityType')
            ->where('a.entityType IS NOT NULL')
            ->orderBy('a.entityType', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'entityType');
    }

    /**
     * Clear old logs (older than specified days)
     */
    public function clearOldLogs(int $days = 90): int
    {
        $cutoffDate = new DateTime("-{$days} days");
        
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
