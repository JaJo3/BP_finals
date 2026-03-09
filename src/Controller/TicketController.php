<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Entity\Event;
use App\Form\TicketType;
use App\Repository\TicketRepository;
use App\Repository\EventRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActivityLogger;

#[Route('/admin')]
class TicketController extends AbstractController
{
    public function __construct(private ActivityLogger $activityLogger)
    {
    }

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

    #[Route('/tickets', name: 'admin_tickets', methods: ['GET'])]
    public function index(TicketRepository $ticketRepository, EventRepository $eventRepository): Response
    {
        $this->ensureAdminOrStaff();
        // Pass current user info for template conditional rendering
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        return $this->render('ticket/index.html.twig', [
            'tickets' => $ticketRepository->findAll(),
            'events' => $eventRepository->findAll(),
            'currentSort' => 'createdAt',
            'currentOrder' => 'DESC',
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/tickets/new', name: 'admin_ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, EventRepository $eventRepository): Response
    {
        $this->ensureAdminOrStaff();
        $ticket = new Ticket();

        // If an eventId is provided in the query string, pre-select that event on the ticket form
        $event = null;
        $eventId = $request->query->get('eventId');
        if ($eventId) {
            $event = $eventRepository->find((int) $eventId);
            if ($event) {
                $ticket->setEvent($event);
            }
        }

        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($ticket);
            // set createdBy if a user is logged in
            $user = $this->getUser();
            if ($user instanceof User) {
                $ticket->setCreatedBy($user);
            }
            $entityManager->flush();

            $this->activityLogger->logCreate('Ticket', (int) $ticket->getId(), [
                'event' => $ticket->getEvent()?->getId(),
                'type' => $ticket->getTicketType(),
            ]);

            return $this->redirectToRoute('admin_tickets'); // Update this line
        }

        // If invalid and submitted, support Turbo/XHR by returning 422 with form HTML
        if ($form->isSubmitted() && !$form->isValid()) {
            $accept = (string) $request->headers->get('Accept', '');
            $isTurbo = str_contains($accept, 'turbo-stream') || $request->headers->has('Turbo-Frame') || strtolower($request->headers->get('X-Requested-With', '')) === 'xmlhttprequest';
            $html = $this->renderView('ticket/new.html.twig', [
                'ticket' => $ticket,
                'form' => $form->createView(),
                'preselected_event' => $event,
            ]);
            if ($isTurbo) {
                return new Response($html, Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/html']);
            }
            return new Response($html);
        }

        return $this->render('ticket/new.html.twig', [
            'ticket' => $ticket,
            'form' => $form->createView(),
            'preselected_event' => $event,
        ]);
    }

    #[Route('/tickets/{id}', name: 'admin_ticket_show', methods: ['GET'])]
    public function show(Ticket $ticket): Response
    {
        $this->ensureAdminOrStaff();
        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
        ]);
    }

    #[Route('/tickets/{id}/edit', name: 'admin_ticket_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Ticket $ticket, EntityManagerInterface $entityManager): Response
    {
        $this->ensureAdminOrStaff();
        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        // Ownership check: only admins or the creator can edit
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser || method_exists($ticket, 'getCreatedBy') && $ticket->getCreatedBy()?->getId() !== $currentUser->getId()) {
                throw $this->createAccessDeniedException('You can only edit your own tickets.');
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $old = [
                'type' => $ticket->getTicketType(),
                'price' => $ticket->getPrice(),
            ];
            $entityManager->flush();
            $this->activityLogger->logUpdate('Ticket', (int) $ticket->getId(), $old, [
                'type' => $ticket->getTicketType(),
                'price' => $ticket->getPrice(),
            ]);
            $this->addFlash('success', 'Ticket updated successfully!');
            return $this->redirectToRoute('admin_ticket_show', ['id' => $ticket->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $accept = (string) $request->headers->get('Accept', '');
            $isTurbo = str_contains($accept, 'turbo-stream') || $request->headers->has('Turbo-Frame') || strtolower($request->headers->get('X-Requested-With', '')) === 'xmlhttprequest';
            $html = $this->renderView('ticket/edit.html.twig', [
                'ticket' => $ticket,
                'form' => $form->createView(),
            ]);
            if ($isTurbo) {
                return new Response($html, Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/html']);
            }
            return new Response($html);
        }

        return $this->render('ticket/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/tickets/{id}/delete', name: 'admin_ticket_delete', methods: ['POST'])]
    public function delete(Request $request, ?Ticket $ticket = null, EntityManagerInterface $entityManager): Response
    {
        $this->ensureAdminOrStaff();
        if (!$ticket) {
            $this->addFlash('error', 'Ticket not found.');
            return $this->redirectToRoute('admin_tickets');
        }

        // Ownership check: only admins or the creator can delete
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser || method_exists($ticket, 'getCreatedBy') && $ticket->getCreatedBy()?->getId() !== $currentUser->getId()) {
                throw $this->createAccessDeniedException('You can only delete your own tickets.');
            }
        }

        if ($this->isCsrfTokenValid('delete'.$ticket->getId(), $request->request->get('_token'))) {
            $deletedId = $ticket->getId();
            $deletedType = $ticket->getTicketType();
            $entityManager->remove($ticket);
            $entityManager->flush();
            $this->activityLogger->logDelete('Ticket', (int) $deletedId, [
                'type' => $deletedType,
            ]);
            $this->addFlash('success', 'Ticket deleted successfully.');
        }

        return $this->redirectToRoute('admin_tickets');
    }

    #[Route('/event/{eventId}/tickets', name: 'app_event_tickets', methods: ['GET'])]
    public function eventTickets(
        int $eventId,
        EventRepository $eventRepository,
        TicketRepository $ticketRepository
    ): Response {
        $event = $eventRepository->find($eventId);
        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        return $this->render('ticket/index.html.twig', [
            'tickets' => $ticketRepository->findBy(['event' => $event]),
            'event' => $event,
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
        ]);
    }

    // Public JSON endpoint used by the transaction creation form to fetch available ticket types for an event
    #[Route('/event/{eventId}/tickets/json', name: 'api_event_tickets', methods: ['GET'])]
    public function eventTicketsJson(
        int $eventId,
        EventRepository $eventRepository,
        TicketRepository $ticketRepository
    )
    {
        $event = $eventRepository->find($eventId);
        if (!$event) {
            return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        }

        $tickets = $ticketRepository->findBy(['event' => $event]);

        $out = [];
        foreach ($tickets as $t) {
            $out[] = [
                'id' => $t->getId(),
                'ticketType' => $t->getTicketType(),
                'price' => (float) $t->getPrice(),
                'available' => (int) $t->getQuantity(),
                'status' => $t->getStatus(),
            ];
        }

        return $this->json($out);
    }
}
