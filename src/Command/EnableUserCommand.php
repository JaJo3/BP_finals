<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:user:enable', description: 'Enable a user account by username (sets isActive = true)')]
class EnableUserCommand extends Command
{
    public function __construct(private UserRepository $users, private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'The username to enable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');

        $user = $this->users->findOneBy(['username' => $username]);
        if (!$user) {
            $output->writeln(sprintf('<error>User "%s" not found.</error>', $username));
            return Command::FAILURE;
        }

        if (method_exists($user, 'setIsActive')) {
            $user->setIsActive(true);
            $this->em->persist($user);
            $this->em->flush();
            $output->writeln(sprintf('<info>User "%s" enabled.</info>', $username));
            return Command::SUCCESS;
        }

        $output->writeln('<error>User entity does not have setIsActive method.</error>');
        return Command::FAILURE;
    }
}
