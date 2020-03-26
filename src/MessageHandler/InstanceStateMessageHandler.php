<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use App\Entity\DeviceInstance;
use App\Message\InstanceStateMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DeviceInstanceRepository;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class InstanceStateMessageHandler implements MessageHandlerInterface
{
    private $deviceInstanceRepository;
    private $entityManager;
    private $logger;

    public function __construct(DeviceInstanceRepository $deviceInstanceRepository, EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function __invoke(InstanceStateMessage $message)
    {
        $this->logger->info("Received InstanceState message !", [
            'uuid' => $message->getUuid(),
            'state' => $message->getState()
        ]);
        $deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $message->getUuid()]);
        $deviceInstance->setState($message->getState());
        $this->entityManager->persist($deviceInstance);
        $this->entityManager->flush();
    }
}
