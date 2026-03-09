<?php

namespace App\Controller;

use App\Entity\Organizer;
use App\Form\OrganizerType;
use App\Repository\OrganizerRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ActivityLogger;

#[Route('/organizer')]
final class OrganizerController extends AbstractController
{
    public function __construct(private ActivityLogger $activityLogger)
    {
    }

    #[Route('/bulk-delete', name: 'app_organizer_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bulk_delete', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_organizer_index');
        }

        $organizerIds = $request->request->all()['organizers'] ?? [];
        if (empty($organizerIds)) {
            $this->addFlash('error', 'No organizers selected');
            return $this->redirectToRoute('app_organizer_index');
        }

        try {
            $organizers = $entityManager->getRepository(Organizer::class)->findBy(['id' => $organizerIds]);
            foreach ($organizers as $organizer) {
                // Remove logo file if exists
                if ($organizer->getLogoFilename()) {
                    $logoPath = $this->getParameter('kernel.project_dir').'/public/uploads/organizers/'.$organizer->getLogoFilename();
                    if (is_file($logoPath)) {
                        @unlink($logoPath);
                    }
                }
                $entityManager->remove($organizer);
            }
            $entityManager->flush();

            $this->addFlash('success', count($organizerIds) . ' organizer(s) successfully deleted');
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while deleting the organizers');
        }

        return $this->redirectToRoute('app_organizer_index');
    }

    #[Route(name: 'app_organizer_index', methods: ['GET'])]
    public function index(Request $request, OrganizerRepository $organizerRepository): Response
    {
        $q = $request->query->get('q');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(50, (int) $request->query->get('limit', 10)));

        $result = $organizerRepository->findPaginated($q, $page, $limit);

        // Pass current user info for template conditional rendering
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        return $this->render('organizer/index.html.twig', [
            'organizers' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $q,
            'pages' => (int) ceil($result['total'] / $limit),
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/new', name: 'app_organizer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $organizer = new Organizer();
        $form = $this->createForm(OrganizerType::class, $organizer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($logoFile = $form->get('logoFile')->getData()) {
                $originalName = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = $slugger->slug($originalName);
                $newFilename = $safeName.'-'.uniqid().'.'.$logoFile->guessExtension();
                
                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/organizers';
                @mkdir($uploadDir, 0775, true);
                
                $logoFile->move($uploadDir, $newFilename);
                $organizer->setLogoFilename($newFilename); 
            }

            // set createdBy to current user if available
            $user = $this->getUser();
            if ($user instanceof User) {
                $organizer->setCreatedBy($user);
            }
            $entityManager->persist($organizer);
            $entityManager->flush();
            $this->activityLogger->logCreate('Organizer', (int) $organizer->getId(), [
                'name' => $organizer->getOrgName(),
            ]);

            $this->addFlash('success', 'Organizer successfully created!');
            return $this->redirectToRoute('app_organizer_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $accept = (string) $request->headers->get('Accept', '');
            $isTurbo = str_contains($accept, 'turbo-stream') || $request->headers->has('Turbo-Frame') || strtolower($request->headers->get('X-Requested-With', '')) === 'xmlhttprequest';
            $html = $this->renderView('organizer/new.html.twig', [
                'organizer' => $organizer,
                'form' => $form,
            ]);
            if ($isTurbo) {
                return new Response($html, Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/html']);
            }
            return new Response($html);
        }

        return $this->render('organizer/new.html.twig', [
            'organizer' => $organizer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organizer_show', methods: ['GET'])]
    public function show(Organizer $organizer): Response
    {
        return $this->render('organizer/show.html.twig', [
            'organizer' => $organizer,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_organizer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Organizer $organizer, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(OrganizerType::class, $organizer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logoFile */
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile instanceof UploadedFile) {
                // remove old if exists
                if ($organizer->getLogoFilename()) {
                    $old = $this->getParameter('kernel.project_dir').'/public/uploads/organizers/'.$organizer->getLogoFilename();
                    if (is_file($old)) { @unlink($old); }
                }
                $originalName = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = $slugger->slug($originalName);
                $newFilename = $safeName.'-'.uniqid().'.'.$logoFile->guessExtension();
                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/organizers';
                @mkdir($uploadDir, 0775, true);
                $logoFile->move($uploadDir, $newFilename);
                $organizer->setLogoFilename($newFilename);
            }
            $old = [
                'name' => $organizer->getOrgName(),
            ];
            $entityManager->flush();
            $this->activityLogger->logUpdate('Organizer', (int) $organizer->getId(), $old, [
                'name' => $organizer->getOrgName(),
            ]);

            $this->addFlash('success', 'Organizer successfully updated!');
            return $this->redirectToRoute('app_organizer_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $accept = (string) $request->headers->get('Accept', '');
            $isTurbo = str_contains($accept, 'turbo-stream') || $request->headers->has('Turbo-Frame') || strtolower($request->headers->get('X-Requested-With', '')) === 'xmlhttprequest';
            $html = $this->renderView('organizer/edit.html.twig', [
                'organizer' => $organizer,
                'form' => $form,
            ]);
            if ($isTurbo) {
                return new Response($html, Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/html']);
            }
            return new Response($html);
        }

        return $this->render('organizer/edit.html.twig', [
            'organizer' => $organizer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organizer_delete', methods: ['POST'])]
    public function delete(Request $request, Organizer $organizer, EntityManagerInterface $entityManager): Response
    {
        // Ownership check: only admins or creator may delete
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser || method_exists($organizer, 'getCreatedBy') && $organizer->getCreatedBy()?->getId() !== $currentUser->getId()) {
                throw $this->createAccessDeniedException('You can only delete your own organizers.');
            }
        }

        if ($this->isCsrfTokenValid('delete'.$organizer->getId(), $request->request->get('_token'))) {
            $deletedId = $organizer->getId();
            $deletedName = $organizer->getOrgName();
            $entityManager->remove($organizer);
            $entityManager->flush();
            $this->activityLogger->logDelete('Organizer', (int) $deletedId, [
                'name' => $deletedName,
            ]);
        }

        return $this->redirectToRoute('app_organizer_index', [], Response::HTTP_SEE_OTHER);
    }
}
