<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Form\AdminSetPasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ActivityLogger;

#[Route('/user')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(private ActivityLogger $activityLogger) {}

    #[Route(name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        // Explicit server-side check: if repeated password fields exist, ensure they match
        if ($form->isSubmitted() && $form->has('plainPassword') && $form->get('plainPassword')->has('first') && $form->get('plainPassword')->has('second')) {
            $p1 = $form->get('plainPassword')->get('first')->getData();
            $p2 = $form->get('plainPassword')->get('second')->getData();
            if ($p1 !== $p2) {
                $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Password and repeat password do not match.'));
            }
        }
        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure newly created users are active by default
            if (method_exists($user, 'setIsActive')) {
                $user->setIsActive(true);
            }
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $form->get('profileImage')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                $newFilename = uniqid('profile_') . '.' . $uploadedFile->guessExtension();
                try {
                    $uploadedFile->move($uploadsDir, $newFilename);
                    $user->setProfileImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Failed to upload profile image.');
                }
            }
            // Handle password hashing from the plainPassword repeated field (if provided)
            $plain = $form->get('plainPassword')->getData();
            if ($plain) {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->activityLogger->logCreate('User', (int) $user->getId(), [
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
            ]);

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        // Return 422 Unprocessable Entity for validation failures on form submission
        $statusCode = $form->isSubmitted() ? 422 : 200;
        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ], response: new Response(status: $statusCode));
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);
        // Explicit server-side check for repeated password match on edit
        if ($form->isSubmitted() && $form->has('plainPassword') && $form->get('plainPassword')->has('first') && $form->get('plainPassword')->has('second')) {
            $p1 = $form->get('plainPassword')->get('first')->getData();
            $p2 = $form->get('plainPassword')->get('second')->getData();
            if ($p1 !== $p2) {
                $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Password and repeat password do not match.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // If admin provided a new password, hash it
            $plain = $form->get('plainPassword')->getData();
            if ($plain) {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $form->get('profileImage')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                $newFilename = uniqid('profile_') . '.' . $uploadedFile->guessExtension();
                try {
                    $uploadedFile->move($uploadsDir, $newFilename);
                    $user->setProfileImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Failed to upload profile image.');
                }
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        // Return 422 Unprocessable Entity for validation failures on form submission
        $statusCode = $form->isSubmitted() ? 422 : 200;
        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ], response: new Response(status: $statusCode));
    }

    #[Route('/{id}/change-password', name: 'app_user_change_password', methods: ['GET', 'POST'])]
    public function changePasswordForUser(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        // Use the same ChangePasswordType as profile so form contains current + new + repeat
        $form = $this->createForm(\App\Form\ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $current = $form->get('currentPassword')->getData();
            $new = $form->get('newPassword')->getData();

            // Require the currently-logged-in admin's password to authorize resetting another user's password
            $loggedIn = $this->getUser();
            if (!$loggedIn instanceof \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface || !$passwordHasher->isPasswordValid($loggedIn, $current)) {
                $form->get('currentPassword')->addError(new \Symfony\Component\Form\FormError('Current password is incorrect'));
            } else {
                if ($passwordHasher->isPasswordValid($user, $new)) {
                    $form->get('newPassword')->addError(new \Symfony\Component\Form\FormError('New password must be different from the current password.'));
                } else {
                    $user->setPassword($passwordHasher->hashPassword($user, $new));
                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->addFlash('success', 'Password updated successfully for user.');
                    return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
                }
            }
        }

        return $this->render('user/change_password.html.twig', [
            'passwordForm' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        // Use request->request to access POST form parameters (CSRF token)
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $token)) {
            $deletedUserId = $user->getId();
            $deletedUsername = $user->getUsername();

            // Get all events created by this user
            $eventsCreatedByUser = $entityManager->getRepository(\App\Entity\Event::class)
                ->findBy(['createdBy' => $user]);

            $eventCount = count($eventsCreatedByUser);

            // Delete all events created by this user
            foreach ($eventsCreatedByUser as $event) {
                $entityManager->remove($event);
            }

            // Get all transactions related to this user (either as user or createdBy)
            $userTransactions = $entityManager->getRepository(\App\Entity\Transaction::class)
                ->findBy(['user' => $user]);
            $createdByTransactions = $entityManager->getRepository(\App\Entity\Transaction::class)
                ->findBy(['createdBy' => $user]);

            // Merge arrays and remove duplicates
            $allTransactions = array_merge($userTransactions, $createdByTransactions);
            $allTransactions = array_unique($allTransactions, SORT_REGULAR);
            $transactionCount = count($allTransactions);

            // Clear user references from transactions (set to NULL via database cascade)
            foreach ($allTransactions as $transaction) {
                if ($transaction->getUser() === $user) {
                    $transaction->setUser(null);
                }
                if ($transaction->getCreatedBy() === $user) {
                    $transaction->setCreatedBy(null);
                }
                $entityManager->persist($transaction);
            }

            // Delete the user
            $entityManager->remove($user);
            $entityManager->flush();

            // Log the deletion
            $this->activityLogger->logDelete('User', (int) $deletedUserId, [
                'username' => $deletedUsername,
                'events_deleted' => $eventCount,
                'transactions_cleaned' => $transactionCount,
            ]);

            // Add success notice
            $deletionMessages = [];
            if ($eventCount > 0) {
                $deletionMessages[] = sprintf('%d event(s)', $eventCount);
            }
            if ($transactionCount > 0) {
                $deletionMessages[] = sprintf('%d transaction(s)', $transactionCount);
            }

            if (!empty($deletionMessages)) {
                $this->addFlash('success', sprintf(
                    'User "%s" deleted with %s cleaned up.',
                    $deletedUsername,
                    implode(' and ', $deletionMessages)
                ));
            } else {
                $this->addFlash('success', sprintf(
                    'User "%s" has been successfully deleted.',
                    $deletedUsername
                ));
            }
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'app_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        // Only ADMIN can disable/enable staff accounts
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('toggle_status_' . $user->getId(), $token)) {
            $currentStatus = $user->getIsActive();
            $newStatus = !$currentStatus;

            $user->setIsActive($newStatus);
            $entityManager->flush();

            // Log the status change
            $this->activityLogger->logUpdate(
                'User Status',
                (int) $user->getId(),
                ['isActive' => $currentStatus],
                ['isActive' => $newStatus, 'username' => $user->getUsername()]
            );

            // Flash message
            if ($newStatus) {
                $this->addFlash('success', sprintf('User "%s" has been enabled.', $user->getUsername()));
            } else {
                $this->addFlash('warning', sprintf('User "%s" has been disabled.', $user->getUsername()));
            }
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
