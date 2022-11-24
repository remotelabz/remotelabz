<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use App\Instance\InstanceState;
use Remotelabz\Message\Message\InstanceActionMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DeviceInstanceRepository;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Remotelabz\Message\Message\WorkerHandshakeMessage;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class WorkerHandshakeMessageHandler implements MessageHandlerInterface
{
    private $deviceInstanceRepository;
    private $entityManager;
    private $serializer;
    private $logger;
    private $bus;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        MessageBusInterface $bus,
        LoggerInterface $logger
    ) {
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->bus = $bus;
    }

    public function __invoke(WorkerHandshakeMessage $message)
    {
        $this->logger->info("Received Worker Handshake message !", [
            'id' => $message->getId(),
        ]);

        $deviceInstances = $this->deviceInstanceRepository->findAllStartingOrStarted();

        foreach ($deviceInstances as $deviceInstance) {
            $deviceInstance->setState(InstanceState::STARTING);
            $this->entityManager->flush();
            $uuid = $deviceInstance->getUuid();
            $context = SerializationContext::create()->setGroups('worker');
            $deviceJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);
            $this->logger->info('Sending device instance '.$uuid.' start message.');
            $this->logger->debug('Sending device instance '.$uuid.' start message.', json_decode($deviceJson, true));
            $this->bus->dispatch(
                new InstanceActionMessage($deviceJson, $uuid, InstanceActionMessage::ACTION_START)
            );
        }
    }
}
