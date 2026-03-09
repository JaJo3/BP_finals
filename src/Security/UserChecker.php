<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use App\Entity\User;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->getIsActive()) {
            // Throw an account status exception with a user-friendly message
            throw new CustomUserMessageAccountStatusException('Your account has been disabled.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // no-op for now; keep for future checks (e.g., password expiry)
    }
}
