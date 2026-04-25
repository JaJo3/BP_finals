<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created', method: 'onJWTCreated')]
class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();

        // Set the sub claim to the user's email (from getUserIdentifier)
        $payload['sub'] = $user->getUserIdentifier();

        // Add email for convenience
        $payload['email'] = $user->getUserIdentifier();

        // Remove the old username claim if it exists
        unset($payload['username']);

        $event->setData($payload);
    }
}
