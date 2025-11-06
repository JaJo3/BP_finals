<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Organizer;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\OrganizerRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/event')]
final class EventController extends AbstractController
{
    #[Route(name: 'app_event_index', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $currentName = $request->query->get('name', '');
        $currentOrganizer = $request->query->get('organizer', '');
        $currentDate = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        $currentStatus = $request->query->get('status', '');

        // Filter events based on search criteria
        $criteria = [];
        if ($currentName) {
            $criteria['eventName'] = $currentName;
        }
        if ($currentOrganizer) {
            $criteria['organizer'] = $currentOrganizer;
        }

        $events = empty($criteria) ? $eventRepository->findAll() : $eventRepository->findBy($criteria);

        return $this->render('event/index.html.twig', [
            'events' => $events,
            'currentName' => $currentName,
            'currentOrganizer' => $currentOrganizer,
            'currentDate' => $currentDate,
            'currentStatus' => $currentStatus,
        ]);
    }


     #[Route('/ticket-tier/new/{event}', name: 'app_event_ticket_tier_new', methods: ['GET', 'POST'])]
    public function newTicketTier(Request $request, ?Event $event = null, EntityManagerInterface $entityManager): Response
    {
        if (!$event) {
            // If no event exists, create a new one
            $event = new Event();
            $entityManager->persist($event);
        }

        $ticketTier = new TicketTier();
        $ticketTier->setEvent($event);
        $event->addTicketTier($ticketTier);

        $form = $this->createForm(TicketTierType::class, $ticketTier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($event->getId()) {
                return $this->redirectToRoute('app_event_edit', ['id' => $event->getId()]);
            } else {
                return $this->redirectToRoute('app_event_new');
            }
        }

        return $this->render('event/ticket_tier_new.html.twig', [
            'form' => $form->createView(),
            'event' => $event
        ]);
    }

    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, OrganizerRepository $organizerRepository): Response
    {
        $event = new Event();
        // Check for organizer id in query parameters (supports both 'organizer' and 'organizerId')
        $organizerId = $request->query->get('organizer') ?? $request->query->get('organizerId');
        if ($organizerId) {
            $organizer = $organizerRepository->find($organizerId);
            if ($organizer instanceof Organizer) {
                $event->setOrganizer($organizer);
            }
        } else {
            // Optionally, if the logged-in user is an Organizer entity, preselect it
            $user = $this->getUser();
            if ($user instanceof Organizer) {
                $event->setOrganizer($user);
            }
        }

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $posterFile */
            $posterFile = $form->get('poster')->getData();
            if ($posterFile instanceof UploadedFile) {
                $newFilename = uniqid('poster_', true) . '.' . $posterFile->guessExtension();
                $posterFile->move($this->getParameter('kernel.project_dir') . '/public/uploads/posters', $newFilename);
                $event->setPoster('/uploads/posters/' . $newFilename);
            }
            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'Event created successfully!');
            return $this->redirectToRoute('app_event_index');
        }

        return $this->render('event/new.html.twig', [
            'event' => $event,
            'form' => $form,
            'preselected_organizer' => $event->getOrganizer(),
        ]);
    }

    #[Route('/ticket/event/{eventId}/tickets', name: 'app_event_tickets', methods: ['GET'])]
    public function showEventTickets(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
            'showTickets' => true
        ]);
    }

    #[Route('/{id}', name: 'app_event_show', methods: ['GET'])]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $posterFile */
            $posterFile = $form->get('poster')->getData();
            if ($posterFile instanceof UploadedFile) {
                $newFilename = uniqid('poster_', true) . '.' . $posterFile->guessExtension();
                $posterFile->move($this->getParameter('kernel.project_dir') . '/public/uploads/posters', $newFilename);
                $event->setPoster('/uploads/posters/' . $newFilename);
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_event_delete', methods: ['POST'])]
    public function delete(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$event->getId(), $request->getPayload()->getString('_token'))) {
            try {
                // Get ticket count for user feedback
                $ticketCount = $event->getTickets()->count();
                
                $entityManager->remove($event);
                $entityManager->flush();
                
                // Add success message
                $this->addFlash('success', sprintf(
                    'Event "%s" and its %d associated ticket(s) have been successfully deleted.',
                    $event->getEventName(),
                    $ticketCount
                ));
            } catch (\Exception $e) {
                // Add error message if deletion fails
                $this->addFlash('error', 'An error occurred while deleting the event. Please try again.');
            }
        }

        return $this->redirectToRoute('app_event_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/organizer/{organizerId}', name: 'app_event_by_organizer', methods: ['GET'])]
    public function eventsByOrganizer(int $organizerId, EventRepository $eventRepository, OrganizerRepository $organizerRepository): Response
    {
        $organizer = $organizerRepository->find($organizerId);
        if (!$organizer) {
            throw $this->createNotFoundException('Organizer not found');
        }

        $events = $eventRepository->findBy(['organizer' => $organizer]);

        return $this->render('event/index.html.twig', [
            'events' => $events,
            'organizer' => $organizer,
        ]);
    }
}
