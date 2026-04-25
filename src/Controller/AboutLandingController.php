<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutLandingController extends AbstractController
{
	#[Route('/about', name: 'app_about', methods: ['GET'])]
	public function index(): Response
	{
		$team = [
			[
				'name' => 'JJ Doe',
				'role' => 'Operations Manager',
				'initials' => 'AR',
				'image' => 'uploads/profile/suit_jj.png',
				'bio' => 'Oversees event creation, ticket pool management, and day-to-day operations. Ensures smooth coordination between organizers and the platform.',
			],
			[
				'name' => 'Johan Nalam',
				'role' => 'UI/UX Designer',
				'initials' => 'MS',
				'image' => 'uploads/profile/visa_jj.png',
				'bio' => 'Crafts intuitive interfaces and delightful user experiences. Focuses on making ticket purchasing and event discovery seamless for everyone.',
			],
			[
				'name' => 'Jacques Palalon',
				'role' => 'Backend Engineer',
				'initials' => 'CW',
				'image' => 'uploads/profile/ai_jac.png',
				'bio' => 'Builds and maintains the robust infrastructure powering BeatPass. Ensures security, performance, and reliability of all platform systems.',
			],
			[
				'name' => 'Chelyn Guitguit',
				'role' => 'Support & Customer Success',
				'initials' => 'FA',
				'image' => 'uploads/profile/chel.png',
				'bio' => 'Champions customer satisfaction by providing exceptional support and guidance. Resolves issues quickly and builds lasting relationships with users.',
			],
		];

		return $this->render('landing/about.html.twig', [
			'team' => $team,
		]);
	}
}
