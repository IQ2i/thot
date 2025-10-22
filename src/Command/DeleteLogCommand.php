<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '7 days', from: '00:00')]
#[AsCommand(name: 'app:log:delete', description: 'Delete old logs based on retention period')]
class DeleteLogCommand extends Command
{
    private const int DEFAULT_RETENTION_DAYS = 7;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to retain logs (default: 7 days)', self::DEFAULT_RETENTION_DAYS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $retentionDays = (int) $input->getOption('days');
        if ($retentionDays <= 0) {
            $io->error('Retention days must be a positive number.');

            return Command::FAILURE;
        }

        $cutoffDate = new \DateTime();
        $cutoffDate->modify(\sprintf('-%d days', $retentionDays));

        $logsToDelete = $this->entityManager->getRepository(Log::class)->findToDelete($cutoffDate);
        $io->progressStart(\count($logsToDelete));

        foreach ($logsToDelete as $log) {
            $this->entityManager->remove($log);
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        return Command::SUCCESS;
    }
}
