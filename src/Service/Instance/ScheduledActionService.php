<?php

namespace App\Service\Instance;

use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\ScheduledAction;
use App\Entity\User;
use App\Repository\LabInstanceRepository;
use App\Repository\ScheduledActionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Remotelabz\Message\Message\InstanceStateMessage;
use Psr\Log\LoggerInterface;

/**
 * Business service for scheduled actions.
 *
 * Responsibilities:
 *   1. Create / validate / persist a ScheduledAction
 *   2. Execute a ScheduledAction (called by the runner command)
 *   3. Cancel / delete a pending ScheduledAction
 */
class ScheduledActionService
{
    public function __construct(
        private readonly EntityManagerInterface    $entityManager,
        private readonly ScheduledActionRepository $scheduledActionRepository,
        private readonly LabInstanceRepository     $labInstanceRepository,
        private readonly InstanceManager           $instanceManager,
        private readonly LoggerInterface           $logger,
    ) {}

    // =========================================================================
    // CRÉATION
    // =========================================================================

    /**
     * Creates and persists a new scheduled action.
     *
     * @throws \InvalidArgumentException if parameters are invalid
     * @throws \LogicException           if an identical pending action already exists
     */
    public function schedule(
        Lab              $lab,
        ?Group           $group,
        string           $action,
        \DateTimeInterface $scheduledAt,
        ?User            $createdBy = null
    ): ScheduledAction {
        // Validation de l'action
        if (!in_array($action, ScheduledAction::ACTIONS, true)) {
            throw new \InvalidArgumentException(
                "Invalid action '$action'. Accepted values: " . implode(', ', ScheduledAction::ACTIONS)
            );
        }

        // The scheduled date must be in the future
        if ($scheduledAt <= new \DateTimeImmutable()) {
            throw new \InvalidArgumentException(
                "The scheduled date must be in the future (received: {$scheduledAt->format('Y-m-d H:i:s')})."
            );
        }

        // Duplicate detection: same lab + same group + same action + status pending
        $existing = $this->scheduledActionRepository->findPendingForLabAndGroup($lab, $group, $action);
        if (!empty($existing)) {
            $first = $existing[0];
            throw new \LogicException(sprintf(
                "A pending '%s' action already exists for this lab/group (UUID: %s, scheduled at %s).",
                $action,
                $first->getUuid(),
                $first->getScheduledAt()->format('Y-m-d H:i:s')
            ));
        }

        $sa = new ScheduledAction();
        $sa->setLab($lab)
           ->setGroup($group)
           ->setAction($action)
           ->setScheduledAt($scheduledAt)
           ->setCreatedBy($createdBy);

        $this->entityManager->persist($sa);
        $this->entityManager->flush();

        $this->logger->info(sprintf(
            '[ScheduledActionService] Scheduled action created — uuid=%s action=%s lab=%s group=%s at=%s by=%s',
            $sa->getUuid(),
            $action,
            $lab->getName(),
            $group ? $group->getName() : 'all',
            $scheduledAt->format('Y-m-d H:i:s'),
            $createdBy ? $createdBy->getName() : 'system'
        ));

        return $sa;
    }

    // =========================================================================
    // EXÉCUTION
    // =========================================================================

    /**
     * Executes a ScheduledAction:
     *   - resolves all relevant LabInstances (lab + optional group)
     *   - applies the action on each instance / device
     *   - updates the status and stores the execution report
     *
     * @return array{ success: bool, report: array, errors: array }
     */
    public function execute(ScheduledAction $sa): array
    {
        if (!$sa->isPending()) {
            throw new \LogicException(
                "Cannot execute scheduled action {$sa->getUuid()}: current status = '{$sa->getStatus()}' (expected: pending)."
            );
        }

        // Mark as running to prevent double execution (concurrent runner)
        $sa->setStatus(ScheduledAction::STATUS_RUNNING);
        $this->entityManager->flush();

        $report = [];
        $errors = [];

        try {
            if ($sa->getAction() === ScheduledAction::ACTION_START) {
                // START: ensure a LabInstance exists for each target user, then start devices
                $users = $this->resolveTargetUsers($sa->getLab(), $sa->getGroup());

                $this->logger->info(sprintf(
                    '[ScheduledActionService] START — uuid=%s lab=%s group=%s users=%d',
                    $sa->getUuid(),
                    $sa->getLab()->getName(),
                    $sa->getGroup() ? $sa->getGroup()->getName() : 'all',
                    count($users)
                ));

                foreach ($users as $user) {
                    try {
                        // Create lab instance if it does not exist yet for this user
                        $labInstance = $this->labInstanceRepository->findOneBy([
                            'lab'  => $sa->getLab(),
                            'user' => $user,
                        ]);

                        if (!$labInstance) {
                            $labInstance = $this->instanceManager->create($sa->getLab(), $user);
                            $this->logger->info(sprintf(
                                '[ScheduledActionService] Created lab instance %s for user %s.',
                                $labInstance->getUuid(), $user->getName()
                            ));
                        }

                        $result  = $this->applyAction(ScheduledAction::ACTION_START, $labInstance);
                        $report  = array_merge($report, $result['report']);
                        $errors  = array_merge($errors, $result['errors']);

                    } catch (\Throwable $e) {
                        $this->logger->error(sprintf(
                            '[ScheduledActionService] Error during START for user %s: %s',
                            $user->getName(), $e->getMessage()
                        ));
                        $errors[] = ['user' => $user->getName(), 'error' => $e->getMessage()];
                    }
                }

            } else {
                // STOP / RESET / LEAVE: act on existing instances only
                $labInstances = $this->resolveLabInstances($sa);

                $this->logger->info(sprintf(
                    '[ScheduledActionService] Executing — uuid=%s action=%s lab=%s group=%s instances=%d',
                    $sa->getUuid(),
                    $sa->getAction(),
                    $sa->getLab()->getName(),
                    $sa->getGroup() ? $sa->getGroup()->getName() : 'all',
                    count($labInstances)
                ));

                foreach ($labInstances as $labInstance) {
                    $result = $this->applyAction($sa->getAction(), $labInstance);
                    $report = array_merge($report, $result['report']);
                    $errors = array_merge($errors, $result['errors']);
                }
            }

            $success = empty($errors);

            $sa->setStatus($success ? ScheduledAction::STATUS_DONE : ScheduledAction::STATUS_FAILED)
               ->setExecutedAt(new \DateTimeImmutable())
               ->setExecutionReport(['report' => $report, 'errors' => $errors]);

            if (!$success) {
                $sa->setErrorMessage(
                    count($errors) . ' error(s) during execution. See executionReport for details.'
                );
            }

        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                '[ScheduledActionService] Fatal error for uuid=%s: %s',
                $sa->getUuid(), $e->getMessage()
            ));

            $sa->setStatus(ScheduledAction::STATUS_FAILED)
               ->setExecutedAt(new \DateTimeImmutable())
               ->setErrorMessage($e->getMessage());

            $errors[] = ['error' => $e->getMessage()];
        } finally {
            $this->entityManager->flush();
        }

        return [
            'success' => empty($errors),
            'report'  => $report,
            'errors'  => $errors,
        ];
    }

    // =========================================================================
    // SUPPRESSION
    // =========================================================================

    /**
     * Deletes a ScheduledAction regardless of its status.
     * - pending  : cancelled before execution
     * - running  : force-deleted (use with caution)
     * - done/failed : history cleanup
     */
    public function delete(ScheduledAction $sa): void
    {
        $this->entityManager->remove($sa);
        $this->entityManager->flush();

        $this->logger->info(sprintf(
            '[ScheduledActionService] Scheduled action deleted — uuid=%s status=%s',
            $sa->getUuid(),
            $sa->getStatus()
        ));
    }

    /**
     * Deletes all done and failed scheduled actions visible to the given user.
     * Admins clear everything; teachers clear only their own.
     *
     * @return int Number of deleted entries
     */
    public function clearHistory(\App\Entity\User $user): int
    {
        $actions = $this->scheduledActionRepository->findForUser($user);

        $deleted = 0;
        foreach ($actions as $sa) {
            if ($sa->isDone() || $sa->isFailed()) {
                $this->entityManager->remove($sa);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->entityManager->flush();
        }

        $this->logger->info(sprintf(
            '[ScheduledActionService] History cleared — %d entry/entries deleted by %s',
            $deleted,
            $user->getName()
        ));

        return $deleted;
    }

    // =========================================================================
    // HELPERS PRIVÉS
    // =========================================================================

    /**
     * Resolves the LabInstances to process for STOP / RESET / LEAVE.
     *
     * Uses LabInstanceRepository::findByGroupNoAuth() which applies no access
     * control — safe to call from the cron runner where no User is authenticated.
     *
     * @return \App\Entity\LabInstance[]
     */
    private function resolveLabInstances(ScheduledAction $sa): array
    {
        $lab   = $sa->getLab();
        $group = $sa->getGroup();

        if ($group !== null) {
            return $this->labInstanceRepository->findByGroupNoAuth($group) ?: [];
        }

        return $this->labInstanceRepository->findBy(['lab' => $lab]) ?: [];
    }

    /**
     * Resolves the list of Users to create instances for during a START action.
     *
     * - With a group  : all members of the group
     * - Without group : lab author + all members of every group linked to the lab
     *
     * No authenticated user is required; uses entity collections only.
     *
     * @return User[]
     */
    private function resolveTargetUsers(Lab $lab, ?Group $group): array
    {
        if ($group !== null) {
            return array_map(
                fn($groupUser) => $groupUser->getUser(),
                $group->getUsers()->toArray()
            );
        }

        $users = [];
        $seen  = [];

        $add = function (User $user) use (&$users, &$seen): void {
            if (!isset($seen[$user->getId()])) {
                $seen[$user->getId()] = true;
                $users[]              = $user;
            }
        };

        if ($lab->getAuthor()) {
            $add($lab->getAuthor());
        }

        foreach ($lab->getGroups() as $labGroup) {
            foreach ($labGroup->getUsers() as $groupUser) {
                $add($groupUser->getUser());
            }
        }

        return $users;
    }

    /**
     * Applies the action on a LabInstance and returns a partial report.
     *
     * For START: if no LabInstance exists yet for the user, it is created
     * via InstanceManager::create() before starting the devices.
     *
     * @return array{ report: array, errors: array }
     */
    private function applyAction(string $action, \App\Entity\LabInstance $labInstance): array
    {
        $report = [];
        $errors = [];

        if ($action === ScheduledAction::ACTION_LEAVE) {
            try {
                $uuid = $labInstance->getUuid();
                $this->instanceManager->delete($labInstance);
                $report[] = [
                    'labInstanceUuid' => $uuid,
                    'status'          => 'deleted',
                ];
            } catch (\Throwable $e) {
                $this->logger->error("[ScheduledActionService] Error during leave on {$labInstance->getUuid()}: {$e->getMessage()}");
                $errors[] = [
                    'labInstanceUuid' => $labInstance->getUuid(),
                    'error'           => $e->getMessage(),
                ];
            }
            return compact('report', 'errors');
        }

        // start / stop / reset : agit sur chaque device de la lab instance
        foreach ($labInstance->getDeviceInstances() as $deviceInstance) {
            try {
                $skipped = false;

                switch ($action) {
                    case ScheduledAction::ACTION_START:
                        if (
                            $deviceInstance->getState() === InstanceStateMessage::STATE_STOPPED ||
                            $deviceInstance->getState() === InstanceStateMessage::STATE_ERROR
                        ) {
                            $this->instanceManager->start($deviceInstance);
                        } else {
                            $skipped = true;
                        }
                        break;

                    case ScheduledAction::ACTION_STOP:
                        if (
                            $deviceInstance->getState() === InstanceStateMessage::STATE_STARTED ||
                            $deviceInstance->getState() === InstanceStateMessage::STATE_STARTING ||
                            $deviceInstance->getState() === InstanceStateMessage::STATE_EXPORTING
                        ) {
                            $this->instanceManager->stop($deviceInstance);
                        } else {
                            $skipped = true;
                        }
                        break;

                    case ScheduledAction::ACTION_RESET:
                        if (strtolower($deviceInstance->getDevice()->getHypervisor()->getName()) !== 'natif') {
                            $this->instanceManager->reset($deviceInstance);
                        } else {
                            $skipped = true;
                        }
                        break;
                }

                $report[] = [
                    'labInstanceUuid' => $labInstance->getUuid(),
                    'deviceUuid'      => $deviceInstance->getUuid(),
                    'name'            => $deviceInstance->getDevice()->getName(),
                    'status'          => $skipped ? 'skipped' : $action . 'ed',
                ];

            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    '[ScheduledActionService] Error during %s on device %s: %s',
                    $action, $deviceInstance->getUuid(), $e->getMessage()
                ));
                $errors[] = [
                    'labInstanceUuid' => $labInstance->getUuid(),
                    'deviceUuid'      => $deviceInstance->getUuid(),
                    'name'            => $deviceInstance->getDevice()->getName(),
                    'error'           => $e->getMessage(),
                ];
            }
        }

        return compact('report', 'errors');
    }
}