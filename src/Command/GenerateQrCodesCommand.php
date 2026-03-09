<?php

namespace App\Command;

use App\Repository\TicketPurchaseRepository;
use App\Service\QrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:generate:qr-codes', description: 'Generate missing QR codes for TicketPurchase records')]
final class GenerateQrCodesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private TicketPurchaseRepository $purchases,
        private QrCodeService $qrCodeService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not persist changes, only show count')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit number of records to process', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = (bool) $input->getOption('dry-run');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;

        $qb = $this->purchases->createQueryBuilder('p')
            ->where('p.qrCode IS NULL OR p.qrCode = :empty')
            ->setParameter('empty', '')
            ->orderBy('p.id', 'ASC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $items = $qb->getQuery()->getResult();

        $count = count($items);
        if ($count === 0) {
            $io->success('No TicketPurchase records without QR code were found.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Found %d purchases without QR code', $count));
        if ($dryRun) {
            $io->text('Dry run mode: no changes will be written.');
        }

        $progress = $io->createProgressBar($count);
        $progress->start();

        $updated = 0;

        foreach ($items as $purchase) {
            $unique = $purchase->getUniqueTicketCode();
            if (!$unique) {
                $unique = sprintf('ticket-%d-%s', $purchase->getId() ?? rand(1000,9999), (new \DateTime())->format('YmdHis'));
            }

            try {
                $base64 = $this->qrCodeService->generate($unique);
                if (!$dryRun) {
                    $purchase->setQrCode($base64);
                    $this->em->persist($purchase);
                }
                $updated++;
            } catch (\Throwable $e) {
                // do not stop on single failure
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        if (!$dryRun && $updated > 0) {
            $this->em->flush();
        }

        $io->success(sprintf('Processed %d purchases, %d updated.', $count, $updated));

        return Command::SUCCESS;
    }
}
