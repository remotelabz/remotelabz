<?php

namespace App\Command;

use App\Entity\ScheduledAction;
use App\Repository\ScheduledActionRepository;
use App\Service\Instance\ScheduledActionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generic runner for scheduled actions.
 *
 * This binary is the only one to configure in the server crontab:
 *
 *   * * * * * php /var/www/html/bin/console app:scheduled-actions:run >> /var/log/remotelabz/scheduled.log 2>&1
 *
 * It queries the scheduled_action table, selects all entries
 * where scheduledAt <= NOW and status = 'pending', and executes them one by one
 * via ScheduledActionService::execute().
 *
 * Options:
 *   --dry-run   Displays the actions that would be executed without running them
 *   --uuid=...  Executes a specific scheduled action by UUID (useful for testing)
 */
#[AsCommand(
    name: 'app:scheduled-actions:run',
    description: 'Executes scheduled actions that are due.',
)]
class ScheduledActionsRunCommand extends Command
{
    public function __construct(
        private readonly ScheduledActionRepository $scheduledActionRepository,
        private readonly ScheduledActionService    $scheduledActionService,
        private readonly LoggerInterface           $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Displays due actions without executing them'
            )
            ->addOption(
                'uuid',
                null,
                InputOption::VALUE_REQUIRED,
                'Executes only the scheduled action with this UUID (ignored if --dry-run)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $uuid   = $input->getOption('uuid');

        $io->title('RemoteLabz — Scheduled actions runner');

        // ── Select actions to process ─────────────────────────────────────────
        if ($uuid) {
            $action = $this->scheduledActionRepository->findOneBy(['uuid' => $uuid]);
            if (!$action) {
                $io->error("No scheduled action found with UUID: $uuid");
                return Command::FAILURE;
            }
            $actions = [$action];
        } else {
            $actions = $this->scheduledActionRepository->findDue();
        }

        if (empty($actions)) {
            $io->success('No scheduled actions to execute at this time.');
            return Command::SUCCESS;
        }

        $io->writeln(sprintf('<info>%d action(s) to process</info>', count($actions)));

        // ── Dry-run: display only ─────────────────────────────────────────────
        if ($dryRun) {
            $io->section('Dry-run mode — no action will be executed');
            $rows = [];
            foreach ($actions as $sa) {
                $rows[] = [
                    $sa->getUuid(),
                    $sa->getLab()->getName(),
                    $sa->getGroup() ? $sa->getGroup()->getName() : '(all)',
                    $sa->getAction(),
                    $sa->getScheduledAt()->format('Y-m-d H:i:s'),
                    $sa->getCreatedBy() ? $sa->getCreatedBy()->getName() : 'system',
                ];
            }
            $io->table(
                ['UUID', 'Lab', 'Group', 'Action', 'Scheduled at', 'Created by'],
                $rows
            );
            return Command::SUCCESS;
        }

        // ── Execution ─────────────────────────────────────────────────────────
        $totalSuccess = 0;
        $totalFailed  = 0;

        foreach ($actions as $sa) {
            $label = sprintf(
                '[%s] %s — lab=%s group=%s',
                $sa->getUuid(),
                strtoupper($sa->getAction()),
                $sa->getLab()->getName(),
                $sa->getGroup() ? $sa->getGroup()->getName() : 'all'
            );

            $io->write("  ▶ $label ... ");

            try {
                $result = $this->scheduledActionService->execute($sa);

                if ($result['success']) {
                    $io->writeln('<fg=green>OK</>');
                    $io->writeln(sprintf(
                        '    → %d operation(s) succeeded',
                        count($result['report'])
                    ));
                    $totalSuccess++;
                } else {
                    $io->writeln('<fg=yellow>PARTIAL</>');
                    $io->writeln(sprintf(
                        '    → %d success(es), %d error(s)',
                        count($result['report']),
                        count($result['errors'])
                    ));
                    foreach ($result['errors'] as $err) {
                        $io->writeln('    ✗ ' . ($err['name'] ?? $err['labInstanceUuid'] ?? '?') . ': ' . $err['error']);
                    }
                    $totalFailed++;
                }

            } catch (\Throwable $e) {
                $io->writeln('<fg=red>FAILED</>');
                $io->writeln('    ✗ ' . $e->getMessage());
                $this->logger->error("[Runner] Unhandled exception for {$sa->getUuid()}: {$e->getMessage()}");
                $totalFailed++;
            }
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $io->newLine();
        $io->writeln(sprintf(
            '<info>Summary: %d/%d succeeded</info>',
            $totalSuccess, count($actions)
        ));

        if ($totalFailed > 0) {
            $io->warning("$totalFailed action(s) failed. Check the logs for details.");
            return Command::FAILURE;
        }

        $io->success('All scheduled actions have been executed successfully.');
        return Command::SUCCESS;
    }
}