<?php

namespace App\Controller\Api;

use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
final class AdminDashboardController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/dashboard', name: 'api_admin_dashboard', methods: ['GET'])]
    public function dashboard(TransactionRepository $txRepo, UserRepository $userRepo, ActivityLogRepository $activityRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $conn = $this->em->getConnection();

        // Total users
        $totalUsers = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Total transactions
        $totalTx = (int) $txRepo->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Revenue this year (sum of paid transactions' totalAmount)
        $yearStart = (new \DateTimeImmutable('first day of January this year'))->setTime(0,0,0);
        $yearEnd = (new \DateTimeImmutable('last day of December this year'))->setTime(23,59,59);

        $qb = $txRepo->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.totalAmount), 0)')
            ->where('t.paymentStatus = :paid')
            ->andWhere('t.createdAt BETWEEN :start AND :end')
            ->setParameter('paid', 'paid')
            ->setParameter('start', $yearStart)
            ->setParameter('end', $yearEnd);

        $revenueYear = (float) $qb->getQuery()->getSingleScalarResult();

        // Sales: last 6 months monthly revenue
        $labels = [];
        $values = [];
        $now = new \DateTimeImmutable();
        for ($i = 5; $i >= 0; $i--) {
            $dt = $now->modify("-{$i} months");
            $monthStart = new \DateTimeImmutable($dt->format('Y-m-01 00:00:00'));
            $monthEnd = $monthStart->modify('last day of this month')->setTime(23,59,59);

            $qb = $txRepo->createQueryBuilder('t')
                ->select('COALESCE(SUM(t.totalAmount), 0)')
                ->where('t.paymentStatus = :paid')
                ->andWhere('t.createdAt BETWEEN :start AND :end')
                ->setParameter('paid', 'paid')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd);
            $sum = (float) $qb->getQuery()->getSingleScalarResult();

            $labels[] = $monthStart->format('M Y');
            $values[] = round($sum, 2);
        }

        // Recent activities (last 5)
        $recent = $activityRepo->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $recentOut = [];
        foreach ($recent as $r) {
            $recentOut[] = [
                'id' => $r->getId(),
                'action' => $r->getAction(),
                'entityType' => $r->getEntityType(),
                'createdAt' => $r->getCreatedAt()?->format(DATE_ATOM),
                'user' => $r->getUser() ? ['id' => $r->getUser()->getId(), 'username' => $r->getUser()->getUsername()] : null,
            ];
        }

        $payload = [
            'totals' => [
                'revenueYear' => $revenueYear,
                'totalUsers' => $totalUsers,
                'totalTransactions' => $totalTx,
            ],
            'sales' => [
                'labels' => $labels,
                'values' => $values,
            ],
            'recentActivities' => $recentOut,
        ];

        return new JsonResponse($payload, JsonResponse::HTTP_OK);
    }
}
