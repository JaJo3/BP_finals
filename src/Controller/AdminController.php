<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Form\TicketType;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $dashboardData = [
            'stats' => [
                'totalEvents' => 3500,
                'totalTickets' => 1200000,
                'revenue' => 45000.00,
                'activeOrganizers' => 250,
                'totalUsers' => 800000
            ],
            'chartData' => [
                'ticketSales' => [
                    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    'data' => [30, 45, 60, 75, 90, 100]
                ],
                'popularEvents' => [
                    'concerts' => 60,
                    'festivals' => 30,
                    'other' => 10
                ]
            ]
        ];
        
        return $this->render('admin/index.html.twig', [
            'data' => $dashboardData
        ]);
    }

    #[Route('/tickets', name: 'admin_tickets')]
    public function tickets(): Response
    {
        // Redirect to the ticket controller's index
        return $this->redirectToRoute('app_ticket_index');
    }

    #[Route('/tickets/new', name: 'admin_ticket_new')]
    public function newTicket(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ticket = new Ticket();
        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($ticket);
            $entityManager->flush();

            return $this->redirectToRoute('admin_tickets');
        }

        return $this->render('admin/tickets/new.html.twig', [
            'ticket' => $ticket,
            'form' => $form
        ]);
    }

    #[Route('/tickets/{id}/edit', name: 'admin_ticket_edit')]
    public function editTicket(Request $request, Ticket $ticket, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('admin_tickets');
        }

        return $this->render('admin/tickets/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form
        ]);
    }

    #[Route('/tickets/{id}/delete', name: 'admin_ticket_delete', methods: ['POST'])]
    public function deleteTicket(Request $request, Ticket $ticket, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ticket->getId(), $request->request->get('_token'))) {
            $entityManager->remove($ticket);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_tickets');
    }
}
