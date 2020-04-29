<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use App\Entity\DeviceInstance;
use App\Instance\InstanceState;
use App\Message\InstanceStateMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DeviceInstanceRepository;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class InstanceStateMessageHandler implements MessageHandlerInterface
{
    private $deviceInstanceRepository;
    private $entityManager;
    private $logger;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
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

        // if an error happened, set device instance in its previous state
        // TODO: handle error
        if ($message->getState() === InstanceStateMessage::STATE_ERROR) {
            switch ($deviceInstance->getState()) {
                case InstanceState::STARTING:
                    $deviceInstance->setState(InstanceState::STOPPED);
                    break;

                case InstanceState::STOPPING:
                    $deviceInstance->setState(InstanceState::STARTED);
                    break;

                default:
                    $deviceInstance->setState($message->getState());
            }
        } else {
            $deviceInstance->setState($message->getState());
        }

        $this->entityManager->persist($deviceInstance);
        $this->entityManager->flush();
    }
}
