<?php

namespace App\Controller;

use App\Entity\Organizer;
use App\Form\OrganizerType;
use App\Repository\OrganizerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/organizer')]
final class OrganizerController extends AbstractController
{
    #[Route(name: 'app_organizer_index', methods: ['GET'])]
    public function index(Request $request, OrganizerRepository $organizerRepository): Response
    {
        $q = $request->query->get('q');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(50, (int) $request->query->get('limit', 10)));

        $result = $organizerRepository->findPaginated($q, $page, $limit);

        return $this->render('organizer/index.html.twig', [
            'organizers' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $q,
            'pages' => (int) ceil($result['total'] / $limit),
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
                $organizer->setLogoFilename($newFilename); // Changed from setFileName
            }

            $entityManager->persist($organizer);
            $entityManager->flush();

            $this->addFlash('success', 'Organizer successfully created!');
            return $this->redirectToRoute('app_organizer_index', [], Response::HTTP_SEE_OTHER);
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
            $entityManager->flush();

            $this->addFlash('success', 'Organizer successfully updated!');
            return $this->redirectToRoute('app_organizer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('organizer/edit.html.twig', [
            'organizer' => $organizer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organizer_delete', methods: ['POST'])]
    public function delete(Request $request, Organizer $organizer, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$organizer->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($organizer);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_organizer_index', [], Response::HTTP_SEE_OTHER);
    }
}
