<?php

namespace App\Command;

use App\Instance\InstanceState;
use App\Repository\DeviceInstanceRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Watchdog for device instances stuck in "starting".
 *
 * A device instance can stay in "starting" forever when the worker never
 * replies with an InstanceStateMessage (message lost, worker crash, exception
 * in a message handler, ...): nothing in the normal flow flips it back.
 * This command marks such instances as "error" so the user gets a
 * notification and can start them again.
 *
 * Usage
 * # Exécution normale
 * php bin/console app:instance:reconcile-starting
 *
 * # Sans rien modifier
 * php bin/console app:instance:reconcile-starting --dry-run
 *
 * # Délai minimum avant de considérer un device comme bloqué (minutes)
 * php bin/console app:instance:reconcile-starting --min-age=15
 */
#[AsCommand(
    name: 'app:instance:reconcile-starting',
    description: 'Detect device instances stuck in starting and flag them as error',
)]
class ReconcileStartingInstancesCommand extends Command
{
    private DeviceInstanceRepository $deviceInstanceRepository;
    private EntityManagerInterface $entityManager;
    private NotificationService $notificationService;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService
    ) {
        parent::__construct();
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->entityManager = $entityManager;
        $this->notificationService = $notificationService;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'min-age',
                null,
                InputOption::VALUE_REQUIRED,
                'Minutes a device instance may stay in starting before being flagged as error',
                '10'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report stuck device instances without modifying them'
            )
            ->setHelp(
                'This command flags device instances stuck in "starting" (no state received from the worker) as "error" and notifies their users.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $minAge = (int) $input->getOption('min-age');

        if ($minAge < 1) {
            $io->error('--min-age must be at least 1 minute');
            return Command::FAILURE;
        }

        $threshold = new \DateTime();
        $threshold->modify('-'.$minAge.' minutes');

        $stuckInstances = $this->deviceInstanceRepository->findStuckStartingBefore($threshold);

        if (empty($stuckInstances)) {
            $io->success('No device instance stuck in starting');
            return Command::SUCCESS;
        }

        $io->title(sprintf('%d device instance(s) in starting since more than %d minutes', count($stuckInstances), $minAge));

        foreach ($stuckInstances as $deviceInstance) {
            $uuid = $deviceInstance->getUuid();
            $since = $deviceInstance->getStateChangedAt();
            $since = $since instanceof \DateTime ? $since->format('Y-m-d H:i:s') : 'unknown';

            if ($dryRun) {
                $io->writeln(sprintf('  [dry-run] %s (last state change: %s)', $uuid, $since));
                continue;
            }

            $userIds = $this->getUserIdFromInstance($deviceInstance);

            $deviceInstance->setState(InstanceState::ERROR);
            $this->entityManager->flush();

            $this->notificationService->error(
                $userIds,
                'Instance '.$uuid.' did not report a state in time and has been flagged as errored. You can try to start it again.',
                $uuid
            );

            $io->writeln(sprintf('  flagged %s as error (last state change: %s)', $uuid, $since));
        }

        if ($dryRun) {
            $io->warning(sprintf('%d device instance(s) would be flagged as error', count($stuckInstances)));
        } else {
            $io->success(sprintf('%d device instance(s) flagged as error', count($stuckInstances)));
        }

        return Command::SUCCESS;
    }

    /**
     * Same rules as InstanceStateMessageHandler::getUserIdFromInstance():
     * hidden device -> lab author only, group instance -> group owner +
     * admins, otherwise the instance user.
     *
     * @return string[]
     */
    private function getUserIdFromInstance($instance): array
    {
        try {
            $userIds = [];

            if ($instance instanceof \App\Entity\DeviceInstance
                && null !== $instance->getDevice()
                && $instance->getDevice()->getHidden()
            ) {
                $author = $instance->getLabInstance()?->getLab()?->getAuthor();
                return $author ? [(string) $author->getId()] : [];
            }

            $group = $instance->getGroup();
            if (!is_null($group)) {
                $owner = $group->getOwner();
                if ($owner) {
                    $userIds[] = (string) $owner->getId();
                }
                foreach ($group->getAdmins() as $adminGroupUser) {
                    $adminId = (string) $adminGroupUser->getUser()->getId();
                    if (!in_array($adminId, $userIds)) {
                        $userIds[] = $adminId;
                    }
                }
                if (!empty($userIds)) {
                    return $userIds;
                }
            }

            $user = $instance->getUser();
            if (!is_null($user)) {
                return [(string) $user->getId()];
            }
        } catch (\Exception $e) {
            // Notifications are best-effort: never fail the reconciliation.
        }

        return [];
    }
}
