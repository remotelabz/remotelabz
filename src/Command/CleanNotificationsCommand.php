<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\UserNotification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/*
# Supprimer les notifications lues de plus de 30 jours
php bin/console app:notifications:clean

# Supprimer les notifications lues de plus de 7 jours
php bin/console app:notifications:clean --days=7

# Supprimer uniquement les notifications lues, garder toutes les non lues
php bin/console app:notifications:clean --keep-unread
*/

class CleanNotificationsCommand extends Command
{
    protected static $defaultName = 'app:notifications:clean';
    
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
            ->setDescription('Clean old read notifications from the database')
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Number of days to keep read notifications',
                30
            )
            ->addOption(
                'keep-unread',
                null,
                InputOption::VALUE_NONE,
                'Keep all unread notifications regardless of age'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $days = (int) $input->getOption('days');
        $keepUnread = $input->getOption('keep-unread');
        
        $io->title('Notifications Cleanup');
        $io->text(sprintf(
            'Deleting read notifications older than %d days%s',
            $days,
            $keepUnread ? ' (keeping all unread)' : ''
        ));
        
        $stats = $this->deleteOldNotifications($days, $keepUnread);
        
        if ($stats['deleted'] > 0) {
            $io->success(sprintf(
                'Cleanup completed! Deleted: %d notification(s)',
                $stats['deleted']
            ));
        } else {
            $io->info('No old notifications to delete.');
        }
        
        if (!empty($stats['errors'])) {
            $io->error('Errors occurred during cleanup:');
            $io->listing($stats['errors']);
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }

    /**
     * Delete old notifications from the database
     * 
     * @param int $days Number of days to keep notifications
     * @param bool $keepUnread Whether to keep all unread notifications
     * @return array Statistics about the cleanup operation
     */
    private function deleteOldNotifications(int $days, bool $keepUnread): array
    {
        $stats = [
            'deleted' => 0,
            'errors' => []
        ];

        try {
            $cutoffDate = new \DateTime();
            $cutoffDate->modify("-{$days} days");

            $qb = $this->entityManager
                ->createQueryBuilder()
                ->delete(UserNotification::class, 'n')
                ->where('n.createdAt < :cutoffDate')
                ->setParameter('cutoffDate', $cutoffDate);

            // Only delete read notifications if keepUnread option is set
            if ($keepUnread) {
                $qb->andWhere('n.isRead = :isRead')
                   ->setParameter('isRead', true);
            }

            $deletedCount = $qb->getQuery()->execute();
            $stats['deleted'] = $deletedCount;

            $this->logger->info('[CleanNotificationsCommand]::Deleted old notifications', [
                'count' => $deletedCount,
                'cutoffDate' => $cutoffDate->format('Y-m-d H:i:s'),
                'keepUnread' => $keepUnread
            ]);

        } catch (\Exception $e) {
            $errorMessage = sprintf('Failed to delete old notifications: %s', $e->getMessage());
            $stats['errors'][] = $errorMessage;
            
            $this->logger->error('[CleanNotificationsCommand]::Error during cleanup', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $stats;
    }
}