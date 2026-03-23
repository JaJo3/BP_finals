<?php

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function landing(EventRepository $eventRepository): Response
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

    #[Route('/home', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
}
