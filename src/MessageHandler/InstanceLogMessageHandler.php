<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use App\Entity\DeviceInstanceLog;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DeviceInstanceRepository;
use Remotelabz\Message\Message\InstanceLogMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class InstanceLogMessageHandler
{
    private DeviceInstanceRepository $deviceInstanceRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function __invoke(InstanceLogMessage $message)
    {
        $this->logger->info("Received Instance log message :", [
            'uuid' => $message->getUuid(),
            'type' => $message->getType(),
            'scope' => $message->getScope(),
            'content' => $message->getContent()
        ]);

        $deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $message->getUuid()]);

        // Not every worker log is attached to a device instance: the file copy
        // operations (ISO/image, ACTION_COPYFROMFRONT / ACTION_COPY2WORKER_DEV)
        // use the file name as uuid. Persisting such a log would create an
        // orphan DeviceInstanceLog row (device_instance_id = NULL) that no page
        // ever reads, so it is only logged here.
        if (null === $deviceInstance) {
            $this->logger->debug('[InstanceLogMessageHandler]::No device instance matches the log uuid, log not persisted', [
                'uuid' => $message->getUuid(),
                'type' => $message->getType(),
            ]);

            return;
        }

        $deviceInstanceLog = new DeviceInstanceLog();
        $deviceInstanceLog
            ->setContent($message->getContent())
            ->setType($message->getType())
            ->setScope($message->getScope())
            ->setDeviceInstance($deviceInstance)
        ;

        $this->entityManager->persist($deviceInstanceLog);
        $this->entityManager->flush();
    }
}
