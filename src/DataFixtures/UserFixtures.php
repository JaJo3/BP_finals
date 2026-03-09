<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        
        // NEWUSER
        $newuser = new User();
        $newuser->setUsername('newuser');
        $newuser->setEmail('newuser@gmail.com');
        $newuser->setRoles(['ROLE_USER']);
        $newuser->setPassword(
            $this->passwordHasher->hashPassword($newuser, 'password123')
        );
        $newuser->setIsActive(true);
        $manager->persist($newuser);

        $manager->flush();
    }
}
