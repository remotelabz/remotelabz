<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
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

#[AsCommand(
    name: 'app:route:monitor',
    description: 'Monitor and restore missing routes to lab instances',
)]
class RouteMonitorCommand extends Command
{

    private $routeManager;

    public function __construct(RouteManagerService $routeManager)
    {
        parent::__construct();
        $this->routeManager = $routeManager;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Check routes without adding missing ones'
            )
            ->setHelp('This command checks all lab instance routes and restores any missing routes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title($dryRun ? 'Route Monitor (DRY-RUN)' : 'Route Monitor');

        $stats = $this->routeManager->syncAllRoutes($dryRun);

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
                [$dryRun ? 'Routes missing (would be added)' : 'Routes added', $stats['added']],
                [$dryRun ? 'Routes wrong (would be corrected)' : 'Routes corrected', $stats['fixed']],
                ['Worker unavailable', $stats['worker_unavailable']],
                ['Failed', $stats['failed']],
            ]
        );

        if ($dryRun) {
            if ($stats['failed'] > 0) {
                $io->error('Some routes could not be checked');
                return Command::FAILURE;
            }

            $pending = $stats['added'] + $stats['fixed'];
            if ($pending > 0) {
                $io->comment(sprintf('%d route(s) are missing or incorrect and would be restored (no change made)', $pending));
            } else {
                $io->success('All routes are present and correct');
            }

            return Command::SUCCESS;
        }

        if ($stats['failed'] > 0) {
            $io->error('Some routes could not be restored');
            return Command::FAILURE;
        }

        $restored = $stats['added'] + $stats['fixed'];
        if ($restored > 0) {
            $io->success(sprintf('%d route(s) restored successfully', $restored));
        } else if ($stats['worker_unavailable'] == 0) {
            $io->success('All routes are present and correct');
        }

        return Command::SUCCESS;
    }
}