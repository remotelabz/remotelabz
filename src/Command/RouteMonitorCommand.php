<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\Network\RouteManagerService;

    /* Usage
    * # Exécution normale
    * php bin/console app:route:monitor
    * 
    * # Mode dry-run (ne fait que vérifier sans ajouter)
    * php bin/console app:route:monitor --dry-run
    * 
    * # Avec plus de détails
    * php bin/console app:route:monitor -v
    * php bin/console app:route:monitor -vv
    * php bin/console app:route:monitor -vvv
    * 
    * # Forcer l'exécution sans interaction
    * php bin/console app:route:monitor --no-interaction
    */

class RouteMonitorCommand extends Command
{
    protected static $defaultName = 'app:route:monitor';
    protected static $defaultDescription = 'Monitor and restore missing routes to lab instances';

    private $routeManager;

    public function __construct(RouteManagerService $routeManager)
    {
        parent::__construct();
        $this->routeManager = $routeManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Check routes without adding missing ones (not yet implemented)'
            )
            ->setHelp('This command checks all lab instance routes and restores any missing routes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('DRY-RUN mode not yet implemented with refactored service');
        }

        $io->title('Route Monitor');

        $stats = $this->routeManager->syncAllRoutes();

        if ($stats['total'] === 0) {
            $io->warning('No lab instances found');
            return Command::SUCCESS;
        }

        // Display summary
        $io->title('Summary');
        $io->table(
            ['Status', 'Count'],
            [
                ['Total lab instances', $stats['total']],
                ['Routes OK', $stats['ok']],
                ['Routes added/restored', $stats['added']],
                ['Worker unavailable', $stats['worker_unavailable']],
                ['Failed', $stats['failed']],
            ]
        );

        if ($stats['failed'] > 0) {
            $io->error('Some routes could not be restored');
            return Command::FAILURE;
        }

        if ($stats['added'] > 0) {
            $io->success(sprintf('%d route(s) restored successfully', $stats['added']));
        } else if ($stats['worker_unavailable'] == 0) {
            $io->success('All routes are present');
        }

        return Command::SUCCESS;
    }
}