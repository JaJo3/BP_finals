<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Entity\Event;
use App\Form\TicketType;
use App\Repository\TicketRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class TicketController extends AbstractController
{
    #[Route('/tickets', name: 'admin_tickets', methods: ['GET'])]
    public function index(TicketRepository $ticketRepository, EventRepository $eventRepository): Response
    {
        return $this->render('ticket/index.html.twig', [
            'tickets' => $ticketRepository->findAll(),
            'events' => $eventRepository->findAll(),
            'currentSort' => 'createdAt',
            'currentOrder' => 'DESC'
        ]);
    }

    #[Route('/tickets/new', name: 'admin_ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, EventRepository $eventRepository): Response
    {
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
            $entityManager->flush();

            return $this->redirectToRoute('admin_tickets'); // Update this line
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
        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
        ]);
    }

    #[Route('/tickets/{id}/edit', name: 'admin_ticket_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Ticket $ticket, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Ticket updated successfully!');
            return $this->redirectToRoute('admin_ticket_show', ['id' => $ticket->getId()]);
        }

        return $this->render('ticket/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/tickets/{id}/delete', name: 'admin_ticket_delete', methods: ['POST'])]
    public function delete(Request $request, ?Ticket $ticket = null, EntityManagerInterface $entityManager): Response
    {
        if (!$ticket) {
            $this->addFlash('error', 'Ticket not found.');
            return $this->redirectToRoute('admin_tickets');
        }

        if ($this->isCsrfTokenValid('delete'.$ticket->getId(), $request->request->get('_token'))) {
            $entityManager->remove($ticket);
            $entityManager->flush();
            
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

        return $this->render('ticket/index.html.twig', [
            'tickets' => $ticketRepository->findBy(['event' => $event]),
            'event' => $event,
        ]);
    }
}
