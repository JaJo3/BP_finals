<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Find users that are either ROLE_ADMIN or ROLE_STAFF.
     * This method uses a LIKE match on the roles column to keep it DB-agnostic.
     *
     * @return User[]
     */
    public function findAdminsOrStaff(): array
    {
        $qb = $this->createQueryBuilder('u');

        // roles is stored as JSON/array; use LIKE to match role strings contained in the serialized value
        $qb->where($qb->expr()->orX(
            $qb->expr()->like('u.roles', ':admin'),
            $qb->expr()->like('u.roles', ':staff')
        ));

        $qb->setParameter('admin', '%ROLE_ADMIN%');
        $qb->setParameter('staff', '%ROLE_STAFF%');

        return $qb->orderBy('u.id', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find users that have ROLE_STAFF only (not admins).
     *
     * @return User[]
     */
    public function findStaff(): array
    {
        $qb = $this->createQueryBuilder('u');

        // Match serialized roles containing ROLE_STAFF
        $qb->where($qb->expr()->like('u.roles', ':staff'))
           ->andWhere($qb->expr()->notLike('u.roles', ':admin'));

        $qb->setParameter('staff', '%ROLE_STAFF%');
        $qb->setParameter('admin', '%ROLE_ADMIN%');

        return $qb->orderBy('u.id', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find users that can be selected as buyers for transactions.
     * Returns only ROLE_USER users (excludes ROLE_ADMIN and ROLE_STAFF).
     *
     * @return User[]
     */
    public function findBuyers(): array
    {
        $qb = $this->createQueryBuilder('u');

        // Return only users with ROLE_USER, exclude admins and staff
        $qb->where($qb->expr()->notLike('u.roles', ':admin'))
           ->andWhere($qb->expr()->notLike('u.roles', ':staff'))
           ->setParameter('admin', '%ROLE_ADMIN%')
           ->setParameter('staff', '%ROLE_STAFF%')
           ->orderBy('u.email', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
