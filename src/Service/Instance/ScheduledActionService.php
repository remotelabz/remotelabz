<?php

namespace App\Service\Instance;

use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\ScheduledAction;
use App\Entity\User;
use App\Repository\LabInstanceRepository;
use App\Repository\ScheduledActionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\InstanceStateMessage;

/**
 * Service métier pour les actions planifiées.
 *
 * Responsabilités :
 *   1. Créer / valider / persister une ScheduledAction
 *   2. Exécuter une ScheduledAction (appelé par la commande runner)
 *   3. Annuler / supprimer une ScheduledAction pending
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
     * Crée et persiste une nouvelle planification.
     *
     * @throws \InvalidArgumentException si les paramètres sont invalides
     * @throws \LogicException           si une planification identique est déjà en attente
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
                "Action '$action' invalide. Valeurs acceptées : " . implode(', ', ScheduledAction::ACTIONS)
            );
        }

        // La date doit être dans le futur
        if ($scheduledAt <= new \DateTimeImmutable()) {
            throw new \InvalidArgumentException(
                "La date planifiée doit être dans le futur (reçu : {$scheduledAt->format('Y-m-d H:i:s')})."
            );
        }

        // Détection de doublon : même lab + même groupe + même action + status pending
        $existing = $this->scheduledActionRepository->findPendingForLabAndGroup($lab, $group, $action);
        if (!empty($existing)) {
            $first = $existing[0];
            throw new \LogicException(sprintf(
                "Une planification '%s' est déjà en attente pour ce lab/groupe (UUID: %s, prévue le %s).",
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
            '[ScheduledActionService] Planification créée — uuid=%s action=%s lab=%s group=%s at=%s by=%s',
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
     * Exécute une ScheduledAction :
     *   - récupère toutes les LabInstances concernées (lab + groupe optionnel)
     *   - applique l'action sur chaque instance / device
     *   - met à jour le statut et stocke le rapport d'exécution
     *
     * @return array{ success: bool, report: array, errors: array }
     */
    public function execute(ScheduledAction $sa): array
    {
        if (!$sa->isPending()) {
            throw new \LogicException(
                "Impossible d'exécuter la planification {$sa->getUuid()} : statut courant = '{$sa->getStatus()}' (attendu: pending)."
            );
        }

        // Marquer comme en cours pour éviter une double exécution (runner concurrent)
        $sa->setStatus(ScheduledAction::STATUS_RUNNING);
        $this->entityManager->flush();

        $report = [];
        $errors = [];

        try {
            $labInstances = $this->resolveLabInstances($sa);

            $this->logger->info(sprintf(
                '[ScheduledActionService] Exécution — uuid=%s action=%s lab=%s group=%s instances=%d',
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

            $success = empty($errors);

            $sa->setStatus($success ? ScheduledAction::STATUS_DONE : ScheduledAction::STATUS_FAILED)
               ->setExecutedAt(new \DateTimeImmutable())
               ->setExecutionReport(['report' => $report, 'errors' => $errors]);

            if (!$success) {
                $sa->setErrorMessage(
                    count($errors) . ' erreur(s) lors de l\'exécution. Voir executionReport pour le détail.'
                );
            }

        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                '[ScheduledActionService] Erreur fatale pour uuid=%s : %s',
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
    // ANNULATION
    // =========================================================================

    /**
     * Annule une planification en attente.
     *
     * @throws \LogicException si la planification n'est plus en état pending
     */
    public function cancel(ScheduledAction $sa): void
    {
        if (!$sa->isPending()) {
            throw new \LogicException(
                "Impossible d'annuler la planification {$sa->getUuid()} : statut courant = '{$sa->getStatus()}'."
            );
        }

        $this->entityManager->remove($sa);
        $this->entityManager->flush();

        $this->logger->info("[ScheduledActionService] Planification annulée — uuid={$sa->getUuid()}");
    }

    // =========================================================================
    // HELPERS PRIVÉS
    // =========================================================================

    /**
     * Résout les LabInstances à traiter pour une ScheduledAction donnée.
     * - Si un groupe est spécifié : instances de ce lab appartenant au groupe
     * - Sinon : toutes les instances du lab
     *
     * @return \App\Entity\LabInstance[]
     */
    private function resolveLabInstances(ScheduledAction $sa): array
    {
        $lab   = $sa->getLab();
        $group = $sa->getGroup();

        if ($group !== null) {
            // Instances du lab dont le owner fait partie du groupe
            return $this->labInstanceRepository->findByLabAndGroup($lab, $group);
        }

        return $this->labInstanceRepository->findBy(['lab' => $lab]);
    }

    /**
     * Applique l'action sur une LabInstance et retourne un rapport partiel.
     *
     * @return array{ report: array, errors: array }
     */
    private function applyAction(string $action, \App\Entity\LabInstance $labInstance): array
    {
        $report = [];
        $errors = [];

        if ($action === ScheduledAction::ACTION_LEAVE) {
            // Leave = suppression de la lab instance entière
            try {
                $this->instanceManager->delete($labInstance);
                $report[] = [
                    'labInstanceUuid' => $labInstance->getUuid(),
                    'status'          => 'deleted',
                ];
            } catch (\Throwable $e) {
                $this->logger->error("[ScheduledActionService] Erreur leave sur {$labInstance->getUuid()}: {$e->getMessage()}");
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
                    '[ScheduledActionService] Erreur %s sur device %s : %s',
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