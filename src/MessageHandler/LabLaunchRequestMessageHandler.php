<?php

namespace App\MessageHandler;

use App\Repository\GroupRepository;
use App\Repository\InvitationCodeRepository;
use App\Repository\LabRepository;
use App\Repository\UserRepository;
use App\Service\Instance\InstanceManager;
use App\Service\Worker\WorkerManager;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\LabLaunchRequestMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LabLaunchRequestMessageHandler
{
    private $instanceManager;
    private $userRepository;
    private $invitationCodeRepository;
    private $groupRepository;
    private $labRepository;
    private $workerManager;
    private $logger;

    // Cache (TTL 15s) pour éviter de surcharger les workers d'appels HTTP réseau
    private static $cachedMetrics = null;
    private static $lastFetch = 0;

    public function __construct(
        InstanceManager $instanceManager,
        UserRepository $userRepository,
        InvitationCodeRepository $invitationCodeRepository,
        GroupRepository $groupRepository,
        LabRepository $labRepository,
        WorkerManager $workerManager,
        LoggerInterface $logger
    ) {
        $this->instanceManager = $instanceManager;
        $this->userRepository = $userRepository;
        $this->invitationCodeRepository = $invitationCodeRepository;
        $this->groupRepository = $groupRepository;
        $this->labRepository = $labRepository;
        $this->workerManager = $workerManager;
        $this->logger = $logger;
    }

    public function __invoke(LabLaunchRequestMessage $message)
    {
        $this->logger->info("[LabLaunchRequestMessageHandler] Traitement de la requête de lancement asynchrone pour le lab " . $message->getLabUuid());

        $lab = $this->labRepository->findOneBy(['uuid' => $message->getLabUuid()]);
        
        switch ($message->getInstancierType()) {
            case 'user':
                $instancier = $this->userRepository->findOneBy(['uuid' => $message->getInstancierUuid()]);
                break;
            case 'guest':
                $instancier = $this->invitationCodeRepository->findOneBy(['uuid' => $message->getInstancierUuid()]);
                break;
            case 'group':
                $instancier = $this->groupRepository->findOneBy(['uuid' => $message->getInstancierUuid()]);
                break;
            default:
                throw new \Exception("Unknown instancier type.");
        }

        if (!$lab || !$instancier) {
            $this->logger->error("[LabLaunchRequestMessageHandler] Lab ou Instancier introuvable.");
            return;
        }

        // 1. Rafraîchir les métriques réelles (TTL 15s OU si un Worker vient de tomber)
        //    Le flag WorkerManager::$cacheInvalidated est mis à true par checkWorkersLightAction()
        //    dès qu'un Worker ne répond plus, pour éviter qu'il reste dans le cache.
        if (time() - self::$lastFetch > 15 || self::$cachedMetrics === null || WorkerManager::$cacheInvalidated) {
            if (WorkerManager::$cacheInvalidated) {
                $this->logger->info("[LabLaunchRequestMessageHandler] Un Worker est tombé : cache invalidé, rechargement immédiat des métriques.");
                WorkerManager::$cacheInvalidated = false; // reset du flag
            } else {
                $this->logger->info("[LabLaunchRequestMessageHandler] Récupération des vraies métriques via checkWorkersLightAction.");
            }
            self::$cachedMetrics = $this->workerManager->checkWorkersLightAction();
            self::$lastFetch = time();
        }

        // 2. Sélection du meilleur worker (La charge cachée "creating" est lue en BDD)
        $memoryNeeded = $this->workerManager->Memory_Usage($lab);
        $bestWorkerIp = $this->workerManager->getBestWorkerFromMetrics(self::$cachedMetrics, $memoryNeeded);

        if (empty($bestWorkerIp)) {
            $this->logger->error("[LabLaunchRequestMessageHandler] Aucun worker disponible pour le lab " . $lab->getName());
            return;
        }

        $this->logger->info("[LabLaunchRequestMessageHandler] Worker assigné : " . $bestWorkerIp);

        // 3. Création de l'instance et délégation au worker ciblé
        $this->instanceManager->create($lab, $instancier, $bestWorkerIp);
    }
}
