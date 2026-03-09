<?php

namespace App\Controller;

use App\Form\ChangePasswordType;
use App\Form\AdminProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AdminAccountController extends AbstractController
{
    #[Route('/admin/profile', name: 'admin_profile')]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Admin area is restricted to staff and admins only.');
        }

        $form = $this->createForm(AdminProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
                /** @var UploadedFile|null $uploadedFile */
                $uploadedFile = $form->get('profileImage')->getData();

                if ($uploadedFile) {
                    $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile';
                    if (!is_dir($uploadsDir)) {
                        mkdir($uploadsDir, 0755, true);
                    }
                    $newFilename = uniqid() . '.' . $uploadedFile->guessExtension();
                    try {
                        $uploadedFile->move($uploadsDir, $newFilename);
                        $user->setProfileImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('danger', 'Failed to upload image.');
                    }
                }

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Profile updated successfully.');
                return $this->redirectToRoute('admin_profile');
            }

        return $this->render('admin/profile.html.twig', [
            'user' => $user,
            'profileForm' => $form->createView(),
        ]);
    }

    #[Route('/admin/profile/password', name: 'admin_change_password')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Admin area is restricted to staff and admins only.');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $current = $form->get('currentPassword')->getData();
            $new = $form->get('newPassword')->getData();

            // validate current password matches the stored password
            if (!$passwordHasher->isPasswordValid($user, $current)) {
                $form->get('currentPassword')->addError(new FormError('Current password is incorrect'));
            } else {
                // Ensure the new password is not the same as the current password
                if ($passwordHasher->isPasswordValid($user, $new)) {
                    $form->get('newPassword')->addError(new FormError('New password must be different from the current password.'));
                } else {
                    // At this point, RepeatedType already ensures the two new password fields match.
                    $hashed = $passwordHasher->hashPassword($user, $new);
                    $user->setPassword($hashed);
                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'Password updated successfully.');
                    return $this->redirectToRoute('admin_profile');
                }
            }
        }

        return $this->render('admin/change_password.html.twig', [
            'passwordForm' => $form->createView(),
        ]);
    }
}
