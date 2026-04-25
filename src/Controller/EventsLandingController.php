<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EventsLandingController extends AbstractController
{
	#[Route('/events', name: 'app_events', methods: ['GET'])]
	public function index(EventRepository $eventRepository, Request $request): Response
	{
		$page = max(1, (int)$request->query->get('page', 1));
		$pageSize = 10;

		// Get all events first
		$now = new \DateTimeImmutable();
		$query = $eventRepository->createQueryBuilder('e')
			->leftJoin('e.organizer', 'o')
			->where('e.date >= :now')
			->andWhere('e.status IN (:statuses)')
			->setParameter('now', $now)
			->setParameter('statuses', [
				'Upcoming',
				'Active',
				'Ongoing',
			])
			->orderBy('e.date', 'ASC')
			->getQuery();

		$allEvents = $query->getResult();

		// Calculate pagination manually
		$totalItems = count($allEvents);
		$totalPages = (int)ceil($totalItems / $pageSize);

		// Validate page
		if ($page > $totalPages && $totalPages > 0) {
			$page = $totalPages;
		}

		// Get slice
		$offset = ($page - 1) * $pageSize;
		$pageEvents = array_slice($allEvents, $offset, $pageSize);

		// Format for template and group by month
		$events = [];
		$groupedEvents = [];

		foreach ($pageEvents as $event) {
			$minPrice = null;
			foreach ($event->getTickets() as $ticket) {
				if ($minPrice === null || $ticket->getPrice() < $minPrice) {
					$minPrice = $ticket->getPrice();
				}
			}

			$formattedEvent = [
				'event' => $event,
				'minPrice' => $minPrice,
			];

			$events[] = $formattedEvent;

			// Group by month
			$monthKey = $event->getDate() ? $event->getDate()->format('F Y') : 'TBA';
			if (!isset($groupedEvents[$monthKey])) {
				$groupedEvents[$monthKey] = [];
			}
			$groupedEvents[$monthKey][] = $formattedEvent;
		}

		return $this->render('landing/events.html.twig', [
			'events' => $events,
			'groupedEvents' => $groupedEvents,
			'currentPage' => $page,
			'totalPages' => $totalPages,
			'totalItems' => $totalItems,
			'limit' => $pageSize,
		]);
	}
}
