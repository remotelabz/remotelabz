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
