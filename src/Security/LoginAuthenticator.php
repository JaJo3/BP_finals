<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator, private UserRepository $users, private LoggerInterface $logger)
    {
    }

    public function authenticate(Request $request): Passport
    {
        // Read from POSTed form fields (standard HTML form)
        $username = (string) $request->request->get('username', '');
        $password = (string) $request->request->get('password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        // Debug logging to help diagnose CSRF mismatch issues
        try {
            $this->logger->debug('LoginAuthenticator debug', [
                'username' => $username,
                'csrf_token_submitted' => $csrfToken,
                'session_id' => $request->getSession()->getId(),
                //'session_all' => $request->getSession()->all(), // avoid noisy output
                'session_csrf_authenticate' => $request->getSession()->get('_csrf/authenticate'),
            ]);
        } catch (\Throwable $e) {
            // swallow logging errors to avoid breaking authentication flow
        }

        $userBadge = new UserBadge($username, function($userIdentifier) {
            $user = $this->users->findOneBy(['username' => $userIdentifier]);
            if (!$user) {
                throw new CustomUserMessageAuthenticationException('Username could not be found.');
            }

            if (method_exists($user, 'getIsActive') && !$user->getIsActive()) {
                throw new CustomUserMessageAuthenticationException('Your account has been disabled.');
            }
             // Check if user's email is verified (skip for admin users)
            if (method_exists($user, 'isVerified') && !$user->isVerified() && !in_array('ROLE_ADMIN', $user->getRoles())) {
                throw new CustomUserMessageAuthenticationException('Please verify your email address before logging in. Check your inbox for the verification link.');
            }
            return $user;
        });

        return new Passport(
            $userBadge,
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Redirect based on user role
        $user = $token->getUser();
        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        } elseif (in_array('ROLE_STAFF', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('app_event_index'));
        } else {
            return new RedirectResponse($this->urlGenerator->generate('app_home'));
        }
         // Regular users (ROLE_USER only) always go to landing page
        // Never redirect regular users to admin pages
        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        try {
            $username = (string) $request->request->get('username', '');
            $this->logger->warning('Authentication failure', [
                'username' => $username,
                'error' => $exception->getMessage(),
                'type' => get_class($exception),
            ]);
        } catch (\Throwable $e) {
            // ignore logging errors
        }

        // Delegate to the parent to build the proper Response (redirect back to login with error)
        return parent::onAuthenticationFailure($request, $exception);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
