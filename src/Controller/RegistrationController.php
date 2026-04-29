<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\EmailVerificationService; // NEW - Inject email service
use Symfony\Component\Routing\Generator\UrlGeneratorInterface; // NEW - For generating absolute URLs

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, EmailVerificationService $emailVerificationService): Response
    {
        $user = new User();

        $session = $request->getSession();

        // Create form and handle request
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        // Explicit check for repeated password fields
        if ($form->isSubmitted() && $form->has('plainPassword') && $form->get('plainPassword')->has('first') && $form->get('plainPassword')->has('second')) {
            $p1 = $form->get('plainPassword')->get('first')->getData();
            $p2 = $form->get('plainPassword')->get('second')->getData();
            if ($p1 !== $p2) {
                $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Password and repeat password do not match.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Generate verification token
            $verificationToken = $emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($verificationToken);
            $user->setIsVerified(false);

            $entityManager->persist($user);
            $entityManager->flush();

            // Generate verification URL
            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Send verification email
            $emailVerificationService->sendVerificationEmail($user, $verificationUrl);

            $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');

            // Clear any stored registration data
            $session->remove('registration_form_data');
            $session->remove('registration_form_errors');

            return $this->redirectToRoute('app_login');
        }

        // If form has errors, store data in session and redirect (Post-Redirect-Get pattern)
        if ($form->isSubmitted() && !$form->isValid()) {
            // Store submitted data
            $formData = [
                'firstName' => $form->get('firstName')->getData(),
                'lastName' => $form->get('lastName')->getData(),
                'email' => $form->get('email')->getData(),
            ];

            // Store errors for each field
            $formErrors = [];
            foreach ($form->getErrors(true, true) as $error) {
                $origin = $error->getOrigin();
                if ($origin && $origin->getName()) {
                    $fieldName = $origin->getName();
                    if (!isset($formErrors[$fieldName])) {
                        $formErrors[$fieldName] = $error->getMessage();
                    }
                }
            }

            $session->set('registration_form_data', $formData);
            $session->set('registration_form_errors', $formErrors);

            return $this->redirectToRoute('app_register');
        }

        // On GET request, check if we have stored form data from previous error submission
        if ($request->isMethod('GET') && $session->has('registration_form_data')) {
            $formData = $session->get('registration_form_data');
            $user->setFirstName($formData['firstName'] ?? '');
            $user->setLastName($formData['lastName'] ?? '');
            $user->setEmail($formData['email'] ?? '');

            // Recreate form with restored data
            $form = $this->createForm(RegistrationFormType::class, $user);

            // Store errors in a variable for the template, then clear session
            $errors = $session->get('registration_form_errors', []);
            $session->remove('registration_form_data');
            $session->remove('registration_form_errors');

            return $this->render('registration/register.html.twig', [
                'registrationForm' => $form,
                'fieldErrors' => $errors,
            ]);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'fieldErrors' => [],
        ]);
    }
}
