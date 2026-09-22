<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\LoginLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

#[AsCommand('app:login-logs:clean')]
class CleanLoginLogsCommand extends Command
{

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
            ->setDescription('Clean old login logs from the database')
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Number of days to keep login logs',
                365
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');

        $io->title('Login Logs Cleanup');
        $io->text(sprintf(
            'Deleting login logs older than %d days',
            $days
        ));

        $deletedCount = $this->deleteOldLoginLogs($days);

        if ($deletedCount > 0) {
            $io->success(sprintf(
                'Cleanup completed! Deleted: %d login log(s)',
                $deletedCount
            ));
        } else {
            $io->info('No old login logs to delete.');
        }

        return Command::SUCCESS;
    }

    private function deleteOldLoginLogs(int $days): int
    {
        try {
            $cutoffDate = new \DateTime();
            $cutoffDate->modify("-{$days} days");

            $deletedCount = $this->entityManager->getRepository(LoginLog::class)
                ->deleteBefore($cutoffDate);

            $this->logger->info('[CleanLoginLogsCommand]::Deleted old login logs', [
                'count' => $deletedCount,
                'cutoffDate' => $cutoffDate->format('Y-m-d H:i:s'),
                'days' => $days
            ]);

            return $deletedCount;
        } catch (\Exception $e) {
            $this->logger->error('[CleanLoginLogsCommand]::Error during cleanup', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 0;
        }
    }
}
