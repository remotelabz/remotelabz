<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanNotificationsCommand extends Command
{
    protected static $defaultName = 'app:notifications:clean';
    
    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->notificationService->deleteOldNotifications(30);
        $output->writeln('Old notifications cleaned successfully');
        return Command::SUCCESS;
    }
}