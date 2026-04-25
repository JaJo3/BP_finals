<?php

namespace App\Controller;

use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactLandingController extends AbstractController
{
	#[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
	public function index(Request $request): Response
	{
		$form = $this->createForm(ContactFormType::class);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();

			// TODO: Send email notification or save to database
			// For now, just redirect to thank you page

			return $this->redirectToRoute('app_contact_thank_you');
		}

		return $this->render('landing/contact.html.twig', [
			'form' => $form->createView(),
		]);
	}

	#[Route('/contact/thank-you', name: 'app_contact_thank_you', methods: ['GET'])]
	public function thankYou(): Response
	{
		return $this->render('landing/thank_you.html.twig');
	}
}
