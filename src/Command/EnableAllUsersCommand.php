<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:user:enable-all', description: 'Enable all user accounts (sets isActive = true)')]
final class EnableAllUsersCommand extends Command
{
    public function __construct(private UserRepository $userRepository, private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->userRepository->findAll();
        $count = 0;

        foreach ($users as $user) {
            if (method_exists($user, 'setIsActive')) {
                $user->setIsActive(true);
                $this->em->persist($user);
                $count++;
            }
        }

        $this->em->flush();

        $output->writeln(sprintf('<info>Enabled %d users.</info>', $count));

        return Command::SUCCESS;
    }
}
