<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
#[Route('/admin/logs')]
final class ActivityLogController extends AbstractController
{
    private function ensureAdminOrStaff(): void
    {
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser && method_exists($currentUser, 'getIsActive') && !$currentUser->getIsActive()) {
            throw $this->createAccessDeniedException('Your account has been disabled.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Admin area is restricted to staff and admins only.');
        }
    }
    #[Route('/', name: 'app_activity_log_index', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $repo): Response
    {
        $this->ensureAdminOrStaff();
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(100, (int)$request->query->get('limit', 25));

        $filters = [
            'user' => $request->query->get('user'),
            'action' => $request->query->get('action'),
            'entityType' => $request->query->get('entityType'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo' => $request->query->get('dateTo'),
        ];

        $result = $repo->findForAdminList($filters, $page, $limit);
        $availableActions = $repo->getAvailableActions();
        $availableEntityTypes = $repo->getAvailableEntityTypes();

        return $this->render('activity_log/index.html.twig', [
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
            'availableActions' => $availableActions,
            'availableEntityTypes' => $availableEntityTypes,
        ]);
    }

    #[Route('/{id}', name: 'app_activity_log_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ActivityLog $log): Response
    {
        $this->ensureAdminOrStaff();
        // Decode stored JSON strings to arrays for pretty printing in the template
        $affectedData = null;
        $changes = null;

        try {
            if ($log->getAffectedData()) {
                $decoded = json_decode($log->getAffectedData(), true, 512, JSON_THROW_ON_ERROR);
                $affectedData = $decoded;
            }
        } catch (\JsonException $e) {
            // fallback: show raw string
            $affectedData = $log->getAffectedData();
        }

        try {
            if ($log->getChanges()) {
                $decoded = json_decode($log->getChanges(), true, 512, JSON_THROW_ON_ERROR);
                $changes = $decoded;
            }
        } catch (\JsonException $e) {
            $changes = $log->getChanges();
        }

        return $this->render('activity_log/show.html.twig', [
            'log' => $log,
            'affectedData' => $affectedData,
            'changes' => $changes,
        ]);
    }
}
