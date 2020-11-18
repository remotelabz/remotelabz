<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\InstanceStateMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Service\Instance\InstanceManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class InstanceStateMessageHandler implements MessageHandlerInterface
{
    private $deviceInstanceRepository;
    private $labInstanceRepository;
    private $instanceManager;
    private $entityManager;
    private $logger;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        LabInstanceRepository $labInstanceRepository,
        EntityManagerInterface $entityManager,
        InstanceManager $instanceManager,
        LoggerInterface $logger
    ) {
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->instanceManager = $instanceManager;
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
                case InstanceStateMessage::STATE_STARTING:
                    $instance->setState(InstanceStateMessage::STATE_STOPPED);
                    break;

                case InstanceStateMessage::STATE_STOPPING:
                    $instance->setState(InstanceStateMessage::STATE_STARTED);
                    break;
                
                case InstanceStateMessage::STATE_CREATING:
                    $instance->setState(InstanceStateMessage::STATE_DELETED);
                    break;

                case InstanceStateMessage::STATE_DELETING:
                    $instance->setState(InstanceStateMessage::STATE_CREATED);
                    break;

                default:
                    $instance->setState($message->getState());
            }
        } else {
            $instance->setState($message->getState());

            switch ($message->getState()) {
                case InstanceStateMessage::STATE_STOPPED:
                    $this->instanceManager->setStopped($instance);
                break;
            }
        }

        if ($instance->getState() === InstanceStateMessage::STATE_DELETED) {
            $this->entityManager->remove($instance);
        } else {
            $this->entityManager->persist($instance);
        }

        $this->entityManager->flush();
    }
}
