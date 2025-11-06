<?php

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * @return Ticket[] Returns an array of Ticket objects with filtering and sorting
     */
    public function findWithFiltersAndSorting(
        string $sortBy = 'createdAt',
        string $sortOrder = 'DESC',
        ?string $eventFilter = null,
        ?string $typeFilter = null,
        ?float $priceMin = null,
        ?float $priceMax = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.event', 'e')
            ->addSelect('e');

        // Apply filters
        if ($eventFilter) {
            $qb->andWhere('e.event_name LIKE :eventFilter')
               ->setParameter('eventFilter', '%' . $eventFilter . '%');
        }

        if ($typeFilter) {
            $qb->andWhere('t.ticketType = :typeFilter')
               ->setParameter('typeFilter', $typeFilter);
        }

        if ($priceMin !== null) {
            $qb->andWhere('t.price >= :priceMin')
               ->setParameter('priceMin', $priceMin);
        }

        if ($priceMax !== null) {
            $qb->andWhere('t.price <= :priceMax')
               ->setParameter('priceMax', $priceMax);
        }

        // Apply sorting
        $allowedSortFields = ['createdAt', 'price', 'quantity', 'ticketType'];
        $allowedSortOrders = ['ASC', 'DESC'];

        if (in_array($sortBy, $allowedSortFields) && in_array($sortOrder, $allowedSortOrders)) {
            if ($sortBy === 'createdAt') {
                $qb->orderBy('t.createdAt', $sortOrder);
            } elseif ($sortBy === 'eventName') {
                $qb->orderBy('e.event_name', $sortOrder);
            } else {
                $qb->orderBy('t.' . $sortBy, $sortOrder);
            }
        } else {
            $qb->orderBy('t.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Ticket[] Returns an array of available tickets
     */
    public function findAvailableTickets(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.event', 'e')
            ->addSelect('e')
            ->andWhere('t.quantity > 0')
            ->orderBy('e.event_name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEventAndType(Event $event, string $ticketType): ?Ticket
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.event = :event')
            ->andWhere('t.ticketType = :type')
            ->setParameter('event', $event)
            ->setParameter('type', $ticketType)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Ticket[] Returns an array of Ticket objects with related entities
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.event', 'e')
            ->leftJoin('e.organizer', 'o')
            ->addSelect('e', 'o')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllTicketTiersByOrganizer($organizerId)
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.event', 'e')
            ->innerJoin('e.organizer', 'o')
            ->where('o.id = :organizerId')
            ->setParameter('organizerId', $organizerId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTicketsWithDetails()
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'e', 'o')
            ->innerJoin('t.event', 'e')
            ->innerJoin('e.organizer', 'o')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
