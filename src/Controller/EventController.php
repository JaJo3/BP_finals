<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Organizer;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\OrganizerRepository;
use App\Entity\User;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ActivityLogger;

#[Route('/admin/event')]
final class EventController extends AbstractController
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

    #[Route(name: 'app_event_index', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $this->ensureAdminOrStaff();
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

        // Pass current user info for template conditional rendering
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        return $this->render('event/index.html.twig', [
            'events' => $events,
            'currentName' => $currentName,
            'currentOrganizer' => $currentOrganizer,
            'currentDate' => $currentDate,
            'currentStatus' => $currentStatus,
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
        ]);
    }


    // Ticket tier handling removed — TicketTier entity no longer exists.

    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, OrganizerRepository $organizerRepository): Response
    {
        $this->ensureAdminOrStaff();
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
            // set createdBy if a user is logged in
            $user = $this->getUser();
            if ($user instanceof User) {
                $event->setCreatedBy($user);
            }
            $entityManager->flush();

            $this->addFlash('success', 'Event created successfully!');
            $this->activityLogger->logCreate('Event', (int) $event->getId(), [
                'name' => $event->getEventName(),
            ]);
            return $this->redirectToRoute('app_event_index');
        }

        // If the form was submitted but invalid and this is a Turbo/XHR request,
        // return 422 with the rendered form HTML so Turbo will replace the frame.
        if ($form->isSubmitted() && !$form->isValid()) {
            $accept = (string) $request->headers->get('Accept', '');
            $isTurbo = str_contains($accept, 'turbo-stream') || $request->headers->has('Turbo-Frame') || strtolower($request->headers->get('X-Requested-With', '')) === 'xmlhttprequest';
            $html = $this->renderView('event/new.html.twig', [
                'event' => $event,
                'form' => $form,
                'preselected_organizer' => $event->getOrganizer(),
            ]);
            if ($isTurbo) {
                return new Response($html, Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/html']);
            }
            return new Response($html);
        }

        return $this->render('event/new.html.twig', [
            'event' => $event,
            'form' => $form,
            'preselected_organizer' => $event->getOrganizer(),
        ]);
    }

    #[Route('/{id}', name: 'app_event_show', methods: ['GET'])]
    public function show(Event $event): Response
    {
        $this->ensureAdminOrStaff();
        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $this->ensureAdminOrStaff();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        // Ownership check: only admins or the creator can edit
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser || method_exists($event, 'getCreatedBy') && $event->getCreatedBy()?->getId() !== $currentUser->getId()) {
                throw $this->createAccessDeniedException('You can only edit your own events.');
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $posterFile */
            $posterFile = $form->get('poster')->getData();
            if ($posterFile instanceof UploadedFile) {
                $newFilename = uniqid('poster_', true) . '.' . $posterFile->guessExtension();
                $posterFile->move($this->getParameter('kernel.project_dir') . '/public/uploads/posters', $newFilename);
                $event->setPoster('/uploads/posters/' . $newFilename);
            }
            $old = [
                'name' => $event->getEventName(),
                'date' => $event->getDate(),
            ];
            $entityManager->flush();

            $this->activityLogger->logUpdate('Event', (int) $event->getId(), $old, [
                'name' => $event->getEventName(),
                'date' => $event->getDate(),
            ]);

            return $this->redirectToRoute('app_event_index', [], Response::HTTP_SEE_OTHER);
        }

        // Handle invalid form submission for Turbo/XHR requests
        if ($form->isSubmitted() && !$form->isValid()) {
            $accept = (string) $request->headers->get('Accept', '');
            $isTurbo = str_contains($accept, 'turbo-stream') || $request->headers->has('Turbo-Frame') || strtolower($request->headers->get('X-Requested-With', '')) === 'xmlhttprequest';
            $html = $this->renderView('event/edit.html.twig', [
                'event' => $event,
                'form' => $form,
            ]);
            if ($isTurbo) {
                return new Response($html, Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/html']);
            }
            return new Response($html);
        }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_event_delete', methods: ['POST'])]
    public function delete(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        // Ownership check: only admins or the creator can delete
        if (!$this->isGranted('ROLE_ADMIN')) {
                /** @var \App\Entity\User|null $currentUser */
                $currentUser = $this->getUser();
            if (!$currentUser || method_exists($event, 'getCreatedBy') && $event->getCreatedBy()?->getId() !== $currentUser->getId()) {
                throw $this->createAccessDeniedException('You can only delete your own events.');
            }
        }

        if ($this->isCsrfTokenValid('delete'.$event->getId(), $request->request->get('_token'))) {
            try {
                // Get ticket count for user feedback
                $ticketCount = $event->getTickets()->count();
                
                $entityManager->remove($event);
                $deletedId = $event->getId();
                $deletedName = $event->getEventName();
                $entityManager->flush();
                $this->activityLogger->logDelete('Event', (int) $deletedId, [
                    'name' => $deletedName,
                ]);
                
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
