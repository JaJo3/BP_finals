<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dash')]
    #[IsGranted('IS_AUTHENTICATED')]
    public function index(TokenStorageInterface $tokenStorage): Response
    {
        $token = $tokenStorage->getToken();
        $user = $token->getUser();
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            // Admins see the admin dashboard (includes realtime widgets)
            return $this->render('admin/index.html.twig');
        }

        if (in_array('ROLE_USER', $roles)) {
            $message = "You are logged in as Regular User. Limited access granted.";
        } 
        if (in_array('ROLE_STAFF', $roles)) {
            $message = "You are logged in as Staff. You have elevated privileges.";
        }
        else {
            $message = "You are authenticated.";
        }

        return $this->render('dashboard/index.html.twig', [
            'message' => $message,
        ]);
    }
}
