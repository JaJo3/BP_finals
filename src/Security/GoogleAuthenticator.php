<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Psr\Log\LoggerInterface;

class GoogleAuthenticator extends AbstractAuthenticator
{
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;
    private UserPasswordHasherInterface $passwordHasher;
    private ClientRegistry $clientRegistry;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        UserPasswordHasherInterface $passwordHasher,
        ClientRegistry $clientRegistry,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->passwordHasher = $passwordHasher;
        $this->clientRegistry = $clientRegistry;
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $googleUser = $client->fetchUser();
        $googleUserData = $googleUser->toArray();

        $email = $googleUserData['email'] ?? null;
        $username = $googleUserData['name'] ?? $email;

        if (!$email) {
            throw new AuthenticationException('Google email is missing');
        }

        return new SelfValidatingPassport(new UserBadge($email, function (string $userIdentifier) use ($email, $username) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                // Create new user on first Google login
                $user = new User();
                $user->setEmail($email);
                $user->setUsername($username);

                // Determine if user should be staff based on email domain/pattern
                $roles = $this->determineUserRoles($email);
                $user->setRoles($roles);

                $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(8))));

                // Auto-verify Google OAuth users
                if (method_exists($user, 'setIsVerified')) {
                    $user->setIsVerified(true);
                }

                $user->setIsActive(true);

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->logger->info('New Google user created', [
                    'email' => $email,
                    'username' => $username,
                    'roles' => $roles,
                ]);
            } else {
                // Existing user - ensure verified status for Google OAuth
                if (!$user->isVerified() && method_exists($user, 'setIsVerified')) {
                    $user->setIsVerified(true);
                    $this->entityManager->flush();

                    $this->logger->info('Google user auto-verified on login', ['email' => $email]);
                }

                // Check if account is disabled
                if (method_exists($user, 'getIsActive') && !$user->getIsActive()) {
                    throw new AuthenticationException('Your account has been disabled.');
                }
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Redirect based on user role - similar to LoginAuthenticator
        $user = $token->getUser();
        $roles = $user->getRoles();

        // Check roles in order of privilege (highest first)
        if (in_array('ROLE_ADMIN', $roles)) {
            // Admin dashboard
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        if (in_array('ROLE_STAFF', $roles)) {
            // Staff event management
            return new RedirectResponse($this->urlGenerator->generate('app_event_index'));
        }

        // Regular users (ROLE_USER only) always go to landing page
        // Never redirect regular users to admin pages
        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('Google authentication failure', [
            'error' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ]);

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    /**
     * Determine user roles based on email pattern or configuration
     * Can be extended to check against staff email list, domain, etc.
     */
    private function determineUserRoles(string $email): array
    {
        // Default role
        $roles = ['ROLE_USER'];

        // For now, check if email matches patterns that suggest staff/admin
        $staffDomains = isset($_ENV['STAFF_EMAIL_DOMAINS'])
            ? explode(',', $_ENV['STAFF_EMAIL_DOMAINS'])
            : [];

        $adminEmails = isset($_ENV['ADMIN_EMAIL_LIST'])
            ? explode(',', $_ENV['ADMIN_EMAIL_LIST'])
            : [];

        // Check for admin email
        if (in_array(trim($email), array_map('trim', $adminEmails))) {
            $roles = ['ROLE_ADMIN', 'ROLE_USER'];
        }
        // Check for staff domain
        else {
            foreach ($staffDomains as $domain) {
                $domain = trim($domain);
                if (!empty($domain) && str_ends_with($email, $domain)) {
                    $roles = ['ROLE_STAFF', 'ROLE_USER'];
                    break;
                }
            }
        }

        return $roles;
    }
}
