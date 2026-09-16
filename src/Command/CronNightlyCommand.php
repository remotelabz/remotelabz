<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\LoginLog;

/**
 * Nightly cleanup scheduler.
 *
 * This command should be configured in the server crontab to run once per night:
 *
 *   0 2 * * * php /var/www/html/bin/console app:cron:nightly >> /var/log/remotelabz/nightly.log 2>&1
 *
 * It performs all scheduled maintenance tasks (login logs cleanup, etc.).
 */
class CronNightlyCommand extends Command
{
    protected static $defaultName = 'app:cron:nightly';

    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setDescription('Run all nightly maintenance tasks')
            ->setHelp('This command runs all scheduled nightly tasks: login logs cleanup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('RemoteLabz — Nightly Maintenance');
        $io->text(sprintf('Started at %s', date('Y-m-d H:i:s')));
        $io->newLine();

        $totalDeleted = $this->cleanOldLoginLogs($io);

        $io->newLine();
        $io->success('Nightly maintenance completed.');
        $io->text(sprintf('Finished at %s', date('Y-m-d H:i:s')));

        if ($totalDeleted > 0) {
            $io->text(sprintf('Total login logs deleted: %d', $totalDeleted));
        }

        return Command::SUCCESS;
    }

    private function cleanOldLoginLogs(SymfonyStyle $io): int
    {
        $io->section('Login Logs Cleanup');

        try {
            $days = 365;
            $cutoffDate = new \DateTime();
            $cutoffDate->modify("-{$days} days");

            $deletedCount = $this->entityManager->getRepository(LoginLog::class)
                ->deleteBefore($cutoffDate);

            if ($deletedCount > 0) {
                $io->text(sprintf(
                    'Deleted %d login log(s) older than %d days (before %s)',
                    $deletedCount,
                    $days,
                    $cutoffDate->format('Y-m-d H:i:s')
                ));
            } else {
                $io->text('No old login logs to delete.');
            }

            $this->logger->info('[CronNightlyCommand]::Nightly login logs cleanup', [
                'deleted' => $deletedCount,
                'cutoffDate' => $cutoffDate->format('Y-m-d H:i:s'),
                'days' => $days
            ]);

            return $deletedCount;

        } catch (\Exception $e) {
            $io->text('<error>Error: ' . $e->getMessage() . '</error>');
            $this->logger->error('[CronNightlyCommand]::Nightly login logs cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 0;
        }
    }
}
