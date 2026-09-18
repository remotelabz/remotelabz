<?php

namespace App\MessageHandler;

use App\Bridge\Network\IPTools;
use App\Repository\LabInstanceRepository;
use App\Service\Instance\InstanceManager;
use App\Service\NotificationService;
use App\Service\Worker\LabPlacementCache;
use App\Service\Worker\WorkerManager;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\InstanceActionMessage;
use Remotelabz\Message\Message\InstanceStateMessage;
use Remotelabz\Message\Message\LabLaunchRequestMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Places a newly created lab instance on the best worker and launches it.
 *
 * This handler decouples the worker placement from the lab instance creation
 * (see InstanceManager::create()): it is the only place where the "best
 * worker" decision is made, using the real worker stats plus the memory
 * reservations of the other pending instances (LabPlacementCache).
 *
 * Routed to the "front" queue: a single consumer processes the requests one
 * by one, so each placement is taken into account (cache) before the next
 * one is computed. This removes the race condition that used to pile up all
 * the instances of a scheduled group start on the same worker.
 */
#[AsMessageHandler]
class LabLaunchRequestMessageHandler
{
    private LabInstanceRepository $labInstanceRepository;
    private WorkerManager $workerManager;
    private LabPlacementCache $placementCache;
    private InstanceManager $instanceManager;
    private SerializerInterface $serializer;
    private MessageBusInterface $bus;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private NotificationService $notificationService;
    private bool $singleServer;
    private array $workerSerializationGroups = [
        'worker'
    ];

    public function __construct(
        LabInstanceRepository $labInstanceRepository,
        WorkerManager $workerManager,
        LabPlacementCache $placementCache,
        InstanceManager $instanceManager,
        SerializerInterface $serializer,
        MessageBusInterface $bus,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        NotificationService $notificationService,
        bool $singleServer
    ) {
        $this->labInstanceRepository = $labInstanceRepository;
        $this->workerManager = $workerManager;
        $this->placementCache = $placementCache;
        $this->instanceManager = $instanceManager;
        $this->serializer = $serializer;
        $this->bus = $bus;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->notificationService = $notificationService;
        $this->singleServer = $singleServer;
    }

    public function __invoke(LabLaunchRequestMessage $message)
    {
        $uuid = $message->getLabInstanceUuid();

        $this->logger->info('[LabLaunchRequestMessageHandler]::Placement request received for lab instance ' . $uuid);

        $labInstance = $this->labInstanceRepository->findOneBy(['uuid' => $uuid]);
        if (is_null($labInstance)) {
            $this->logger->warning('[LabLaunchRequestMessageHandler]::Lab instance ' . $uuid . ' not found, dropping placement request.');
            $this->placementCache->remove($uuid);
            return;
        }

        // Idempotency: the instance is already placed (message redelivered)
        if (!is_null($labInstance->getWorkerIp())) {
            $this->logger->info('[LabLaunchRequestMessageHandler]::Lab instance ' . $uuid . ' is already placed on ' . $labInstance->getWorkerIp() . ', skipping.');
            $this->placementCache->remove($uuid);
            return;
        }

        // Best worker: real worker stats + memory reservations of the other pending instances
        $worker = $this->workerManager->getFreeWorker($labInstance->getLab());

        if ('' === $worker) {
            $this->logger->error('[LabLaunchRequestMessageHandler]::No worker available for lab instance ' . $uuid . '. The instance is left in error state.');
            $labInstance->setState(InstanceStateMessage::STATE_ERROR);
            $this->entityManager->persist($labInstance);
            $this->entityManager->flush();
            $this->placementCache->remove($uuid);

            $this->notificationService->error(
                $this->getNotificationUserIds($labInstance),
                'Your lab instance could not be started: no worker is available at the moment. Please try again later.',
                $uuid
            );
            return;
        }

        $labInstance->setWorkerIp($worker);
        $this->entityManager->persist($labInstance);
        $this->entityManager->flush();

        if (!$this->singleServer) {// One server for the Front and one server for the worker
            $network = $labInstance->getNetwork();
            if (IPTools::routeExists($network)) {
                $this->logger->debug('[LabLaunchRequestMessageHandler]::Route to ' . $network . ' exists, via ' . $worker);
            } else {
                $this->logger->debug('[LabLaunchRequestMessageHandler]::Route to ' . $network . " doesn't exist, adding it via " . $worker);
                IPTools::routeAdd($network, $worker);
            }
        }

        $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
        $labJson = $this->serializer->serialize($labInstance, 'json', $context);

        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $labInstance->getUuid(), InstanceActionMessage::ACTION_CREATE), [
                new AmqpStamp($worker, AMQP_NOPARAM, []),
            ]
        );

        // The lab is now placed: register its memory on this worker so that the
        // next placements take it into account until it is really running.
        $this->placementCache->assign($uuid, $worker);

        $this->logger->info('[LabLaunchRequestMessageHandler]::Lab instance ' . $uuid . ' launched on worker ' . $worker);

        if ($message->isAutoStartDevices()) {
            foreach ($labInstance->getDeviceInstances() as $deviceInstance) {
                if (
                    $deviceInstance->getState() === InstanceStateMessage::STATE_STOPPED ||
                    $deviceInstance->getState() === InstanceStateMessage::STATE_ERROR
                ) {
                    try {
                        $this->instanceManager->start($deviceInstance);
                        $this->logger->info('[LabLaunchRequestMessageHandler]::Start requested for device ' . $deviceInstance->getUuid() . ' of lab instance ' . $uuid);
                    } catch (\Throwable $e) {
                        $this->logger->error('[LabLaunchRequestMessageHandler]::Error starting device ' . $deviceInstance->getUuid() . ' of lab instance ' . $uuid . ': ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Resolves the user IDs to notify for a lab instance
     * (group owner + admins, or the owning user).
     *
     * @return string[]
     */
    private function getNotificationUserIds($labInstance): array
    {
        $userIds = [];

        if (method_exists($labInstance, 'getGroup') && !is_null($labInstance->getGroup())) {
            $group = $labInstance->getGroup();
            $owner = $group->getOwner();
            if ($owner) {
                $userIds[] = (string) $owner->getId();
            }
            foreach ($group->getAdmins() as $adminGroupUser) {
                $admin = $adminGroupUser->getUser();
                $adminId = (string) $admin->getId();
                if (!in_array($adminId, $userIds)) {
                    $userIds[] = $adminId;
                }
            }
            return $userIds;
        }

        if (method_exists($labInstance, 'getUser') && !is_null($labInstance->getUser())) {
            $userIds[] = (string) $labInstance->getUser()->getId();
        }

        return $userIds;
    }
}
