<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use App\Instance\InstanceState;
use App\Message\InstanceStateMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class InstanceStateMessageHandler implements MessageHandlerInterface
{
    private $deviceInstanceRepository;
    private $labInstanceRepository;
    private $entityManager;
    private $logger;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        LabInstanceRepository $labInstanceRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function __invoke(InstanceStateMessage $message)
    {
        $this->logger->info("Received InstanceState message !", [
            'uuid' => $message->getUuid(),
            'type' => $message->getType(),
            'state' => $message->getState()
        ]);

        if ($message->getType() === InstanceStateMessage::TYPE_LAB)
            $instance = $this->labInstanceRepository->findOneBy(['uuid' => $message->getUuid()]);
        else if ($message->getType() === InstanceStateMessage::TYPE_DEVICE)
            $instance = $this->deviceInstanceRepository->findOneBy(['uuid' => $message->getUuid()]);

        // if an error happened, set device instance in its previous state
        // TODO: handle error
        if ($message->getState() === InstanceStateMessage::STATE_ERROR) {
            switch ($instance->getState()) {
                case InstanceState::STARTING:
                    $instance->setState(InstanceState::STOPPED);
                    break;

                case InstanceState::STOPPING:
                    $instance->setState(InstanceState::STARTED);
                    break;

                default:
                    $instance->setState($message->getState());
            }
        } else {
            $instance->setState($message->getState());
        }

        if ($instance->getState() === InstanceState::DELETED) {
            $this->entityManager->remove($instance);
        } else {
            $this->entityManager->persist($instance);
        }

        $this->entityManager->flush();
    }
}
