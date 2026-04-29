<?php

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LandingController extends AbstractController
{
    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        $rows = $eventRepository->findUpcomingForLanding(6);

        $events = array_map(static function (array $row): array {
            /** @var \App\Entity\Event $event */
            $event = $row[0];
            $minPrice = $row['minPrice'] ?? null;

            return [
                'event' => $event,
                'minPrice' => $minPrice !== null ? (float) $minPrice : null,
            ];
        }, $rows);

        return $this->render('landing/index.html.twig', [
            'events' => $events,
        ]);
    }
}