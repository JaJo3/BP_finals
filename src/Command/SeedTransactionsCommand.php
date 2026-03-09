<?php
namespace App\Command;

use App\Entity\User;
use App\Entity\Organizer;
use App\Entity\Event;
use App\Entity\Ticket;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Repository\TicketRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed:transactions', description: 'Seed demo data: users, events, tickets and transactions')]
final class SeedTransactionsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em,
                                private UserRepository $users,
                                private EventRepository $events,
                                private TicketRepository $tickets,
                                private TransactionRepository $txRepo)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('force', InputArgument::OPTIONAL, 'Force seeding even if transactions exist', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool)$input->getArgument('force');

        $existing = $this->txRepo->findOneBy([]);
        if ($existing && !$force) {
            $output->writeln('<comment>Transactions already exist in DB. Run with argument "force" to add sample data anyway.</comment>');
            return Command::SUCCESS;
        }

        // Create two users
        $userA = $this->users->findOneBy(['username' => 'alice@example.com']);
        if (!$userA) {
            $userA = new User();
            $userA->setUsername('alice@example.com');
            $userA->setPassword(password_hash('password', PASSWORD_BCRYPT));
            $this->em->persist($userA);
        }

        $userB = $this->users->findOneBy(['username' => 'bob@example.com']);
        if (!$userB) {
            $userB = new User();
            $userB->setUsername('bob@example.com');
            $userB->setPassword(password_hash('password', PASSWORD_BCRYPT));
            $this->em->persist($userB);
        }

        // Organizer
        $org = $this->em->getRepository(Organizer::class)->findOneBy(['org_name' => 'Demo Organizer']);
        if (!$org) {
            $org = new Organizer();
            $org->setOrgName('Demo Organizer');
            $org->setContact('+1-555-0000');
            $org->setEmail('org@example.com');
            $org->setContactPerson('Demo Team');
            $org->setDescription('Demo organizer for seeded events');
            $org->setDateCreated(new \DateTimeImmutable());
            $this->em->persist($org);
        }

        // Two events
        $e1 = $this->events->findOneBy(['event_name' => 'Live at the Park']);
        if (!$e1) {
            $e1 = new Event();
            $e1->setEventName('Live at the Park');
            $e1->setDescription('A fun day of live music and street food.');
            $e1->setDate((new \DateTime('+30 days')));
            $e1->setVenue('City Park');
            $e1->setCategory('Music');
            $e1->setStatus(Event::STATUS_UPCOMING);
            $e1->setOrganizer($org);
            $this->em->persist($e1);
        }

        $e2 = $this->events->findOneBy(['event_name' => 'City Night Festival']);
        if (!$e2) {
            $e2 = new Event();
            $e2->setEventName('City Night Festival');
            $e2->setDescription('An evening festival with performers and food trucks.');
            $e2->setDate((new \DateTime('+45 days')));
            $e2->setVenue('Downtown Arena');
            $e2->setCategory('Festival');
            $e2->setStatus(Event::STATUS_UPCOMING);
            $e2->setOrganizer($org);
            $this->em->persist($e2);
        }

        $this->em->flush();

        // Create tickets for events if none
        $createTickets = function(Event $ev, array $variants) {
            foreach ($variants as $v) {
                if (!$this->tickets->findOneBy(['event' => $ev, 'ticketType' => $v['type']])) {
                    $t = new Ticket();
                    $t->setTicketType($v['type']);
                    $t->setPrice($v['price']);
                    $t->setQuantity($v['qty']);
                    $t->setEvent($ev);
                    $this->em->persist($t);
                }
            }
        };

        $createTickets($e1, [
            ['type' => 'VIP',  'price' => 350.00, 'qty' => 40],
            ['type' => 'GA',   'price' => 200.00, 'qty' => 200],
            ['type' => 'VVIP', 'price' => 1000.00,'qty' => 12]
        ]);

        $createTickets($e2, [
            ['type' => 'Regular', 'price' => 150.00, 'qty' => 120],
            ['type' => 'VIP',     'price' => 500.00, 'qty' => 20],
            ['type' => 'GA',      'price' => 75.00,  'qty' => 300]
        ]);

        $this->em->flush();

        // Create sample transactions
        // tx1: alice buys 2 VIP from event1
        $tx1 = new Transaction();
        $tx1->setUser($userA);
        $tx1->setEvent($e1);
        $tx1->setTickets([
            ['ticketId' => $this->tickets->findOneBy(['event' => $e1, 'ticketType' => 'VIP'])->getId(), 'ticketType' => 'VIP', 'quantity' => 2, 'price' => 350.00]
        ]);
        $tx1->setPaymentMethod(Transaction::PAYMENT_GCASH);
        $tx1->setPaymentReference('GCASH-001-A');
        $tx1->setPaymentStatus(Transaction::STATUS_PAID);
        $this->em->persist($tx1);

        // deduct stock for tx1
        $vip1 = $this->tickets->findOneBy(['event' => $e1, 'ticketType' => 'VIP']);
        $vip1->setQuantity(max(0, $vip1->getQuantity() - 2));
        $this->em->persist($vip1);

        // We only create a single demo transaction (Alice) by default.

        $this->em->flush();

        $output->writeln('<info>Seeded sample users, events, tickets and 1 transaction successfully.</info>');
        $output->writeln('Run `php bin/console doctrine:query:sql "SELECT count(*) FROM transactions"` to verify.');

        return Command::SUCCESS;
    }
}
