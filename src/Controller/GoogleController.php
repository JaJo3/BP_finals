<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Psr\Log\LoggerInterface;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(ClientRegistry $clientRegistry, LoggerInterface $logger): RedirectResponse
    {
        try {
            $logger->info('Google OAuth login initiated');
            
            return $clientRegistry
                ->getClient('google')
                ->redirect(['email', 'profile'], []);
        } catch (\Exception $e) {
            $logger->error('Google OAuth redirect failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(Request $request, LoggerInterface $logger)
    {
        // This route is handled by GoogleAuthenticator
        // The authenticator manages:
        // 1. Google OAuth token verification
        // 2. User creation/lookup
        // 3. Automatic email verification
        // 4. Role assignment (ROLE_STAFF or ROLE_USER based on email)
        // 5. Session persistence via Symfony security
        // 6. Role-based redirection (staff to dashboard/events, users to landing)
        
        $logger->info('Google check route - should be handled by GoogleAuthenticator');
        
        return new \Symfony\Component\HttpFoundation\Response('Authentication handled', 200);
    }
}
