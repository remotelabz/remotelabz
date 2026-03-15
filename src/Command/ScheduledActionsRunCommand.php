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
 * Runner générique des actions planifiées.
 *
 * Ce binaire est le seul à configurer dans le crontab du serveur :
 *
 *   * * * * * php /var/www/html/bin/console app:scheduled-actions:run >> /var/log/remotelabz/scheduled.log 2>&1
 *
 * Il interroge la table scheduled_action, sélectionne toutes les entrées
 * dont scheduledAt <= NOW et status = 'pending', et les exécute une par une
 * via ScheduledActionService::execute().
 *
 * Options :
 *   --dry-run   Affiche les actions qui seraient exécutées sans les lancer
 *   --uuid=...  Exécute une planification précise par UUID (utile pour les tests)
 */
#[AsCommand(
    name: 'app:scheduled-actions:run',
    description: 'Exécute les actions planifiées arrivées à échéance.',
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
                'Affiche les actions dues sans les exécuter'
            )
            ->addOption(
                'uuid',
                null,
                InputOption::VALUE_REQUIRED,
                'Exécute uniquement la planification avec cet UUID (ignoré si --dry-run)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $uuid   = $input->getOption('uuid');

        $io->title('RemoteLABZ — Runner des actions planifiées');

        // ── Sélection des actions à traiter ───────────────────────────────────
        if ($uuid) {
            $action = $this->scheduledActionRepository->findOneBy(['uuid' => $uuid]);
            if (!$action) {
                $io->error("Aucune planification trouvée avec l'UUID : $uuid");
                return Command::FAILURE;
            }
            $actions = [$action];
        } else {
            $actions = $this->scheduledActionRepository->findDue();
        }

        if (empty($actions)) {
            $io->success('Aucune action planifiée à exécuter pour le moment.');
            return Command::SUCCESS;
        }

        $io->writeln(sprintf('<info>%d action(s) à traiter</info>', count($actions)));

        // ── Dry-run : affichage seul ───────────────────────────────────────────
        if ($dryRun) {
            $io->section('Mode dry-run — aucune action ne sera exécutée');
            $rows = [];
            foreach ($actions as $sa) {
                $rows[] = [
                    $sa->getUuid(),
                    $sa->getLab()->getName(),
                    $sa->getGroup() ? $sa->getGroup()->getName() : '(tous)',
                    $sa->getAction(),
                    $sa->getScheduledAt()->format('Y-m-d H:i:s'),
                    $sa->getCreatedBy() ? $sa->getCreatedBy()->getName() : 'system',
                ];
            }
            $io->table(
                ['UUID', 'Lab', 'Groupe', 'Action', 'Planifiée le', 'Créée par'],
                $rows
            );
            return Command::SUCCESS;
        }

        // ── Exécution ─────────────────────────────────────────────────────────
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
                        '    → %d opération(s) réussie(s)',
                        count($result['report'])
                    ));
                    $totalSuccess++;
                } else {
                    $io->writeln('<fg=yellow>PARTIEL</>');
                    $io->writeln(sprintf(
                        '    → %d réussite(s), %d erreur(s)',
                        count($result['report']),
                        count($result['errors'])
                    ));
                    foreach ($result['errors'] as $err) {
                        $io->writeln('    ✗ ' . ($err['name'] ?? $err['labInstanceUuid'] ?? '?') . ': ' . $err['error']);
                    }
                    $totalFailed++;
                }

            } catch (\Throwable $e) {
                $io->writeln('<fg=red>ÉCHEC</>');
                $io->writeln('    ✗ ' . $e->getMessage());
                $this->logger->error("[Runner] Exception non gérée pour {$sa->getUuid()}: {$e->getMessage()}");
                $totalFailed++;
            }
        }

        // ── Bilan ─────────────────────────────────────────────────────────────
        $io->newLine();
        $io->writeln(sprintf(
            '<info>Bilan : %d/%d réussite(s)</info>',
            $totalSuccess, count($actions)
        ));

        if ($totalFailed > 0) {
            $io->warning("$totalFailed action(s) en échec. Consultez les logs pour le détail.");
            return Command::FAILURE;
        }

        $io->success('Toutes les actions planifiées ont été exécutées.');
        return Command::SUCCESS;
    }
}