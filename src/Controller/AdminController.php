<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\EventRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EventRepository $eventRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly ActivityLogRepository $activityLogRepository,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        // Only admins allowed by attribute #[IsGranted('ROLE_ADMIN')]

        $totalUsers = $this->userRepository->count([]);
        $totalStaff = (int) $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :staffRole')
            ->setParameter('staffRole', '%"ROLE_STAFF"%')
            ->getQuery()
            ->getSingleScalarResult();

        $totalEvents = $this->eventRepository->count([]);
        $totalTickets = $this->ticketRepository->count([]);

        // Recent activities (limit 10)
        $recentActivities = $this->activityLogRepository->findBy([], ['createdAt' => 'DESC'], 10);

        $dashboardData = [
            'stats' => [
                'totalUsers' => $totalUsers,
                'totalStaff' => $totalStaff,
                'totalEvents' => $totalEvents,
                'totalTickets' => $totalTickets,
            ],
            'recentActivities' => $recentActivities,
        ];

        return $this->render('admin/index.html.twig', [
            'data' => $dashboardData,
        ]);
    }
}
