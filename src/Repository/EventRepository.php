<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Optimized query for the landing page.
     *
     * Returns rows shaped like: [0 => Event, 'minPrice' => ?string]
     *
     * @return array<int, array{0: Event, minPrice: mixed}>
     */
    public function findUpcomingForLanding(int $limit = 6): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('e')
            ->select('e, o, MIN(t.price) AS minPrice')
            ->leftJoin('e.organizer', 'o')
            ->leftJoin('e.tickets', 't')
            ->andWhere('e.date >= :now')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('now', $now)
            ->setParameter('statuses', [
                Event::STATUS_ACTIVE,
                Event::STATUS_UPCOMING,
                Event::STATUS_ONGOING,
            ])
            ->groupBy('e.id, o.id')
            ->orderBy('e.date', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Event[]
     */
    public function findWithFilters(
        ?string $name = null,
        ?string $organizer = null,
        ?\DateTimeInterface $date = null,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.organizer', 'o')
            ->addSelect('o')
            ->orderBy('e.date_created', 'DESC');

        if ($name) {
            $qb->andWhere('e.event_name LIKE :name')
               ->setParameter('name', '%' . $name . '%');
        }

        if ($organizer) {
            $qb->andWhere('o.org_name LIKE :org')
               ->setParameter('org', '%' . $organizer . '%');
        }

        if ($date) {
            $qb->andWhere('DATE(e.date) = :dateExact')
               ->setParameter('dateExact', $date->format('Y-m-d'));
        }

        if ($status) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}
