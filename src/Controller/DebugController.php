<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DebugController extends AbstractController
{
    #[Route('/_debug/csrf', name: 'app_debug_csrf', methods: ['GET'])]
    public function csrf(Request $request): JsonResponse
    {
        $session = $request->getSession();

        $serverToken = $this->container->get('security.csrf.token_manager')->getToken('authenticate')->getValue();
        $sessionToken = $session->get('_csrf/authenticate');

        return new JsonResponse([
            'session_id' => $session ? $session->getId() : null,
            'server_csrf_token' => $serverToken,
            'session_csrf_key' => '_csrf/authenticate',
            'session_csrf_value' => $sessionToken,
            'cookies' => $request->cookies->all(),
            'request_uri' => $request->getUri(),
        ]);
    }
}
