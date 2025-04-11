<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use App\Instance\InstanceState;
use Remotelabz\Message\Message\InstanceActionMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DeviceInstanceRepository;
use App\Repository\DeviceRepository;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Remotelabz\Message\Message\WorkerHandshakeMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class WorkerHandshakeMessageHandler
{
    private DeviceInstanceRepository $deviceInstanceRepository;
    private DeviceRepository $deviceRepository;
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;
    private MessageBusInterface $bus;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        DeviceRepository $deviceRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        MessageBusInterface $bus,
        LoggerInterface $logger
    ) {
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->deviceRepository = $deviceRepository;
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
            $workerIp = $deviceInstance->getLabInstance()->getWorkerIp();         
            $deviceInstance->setState(InstanceState::STARTING);
            $this->entityManager->flush();
            $uuid = $deviceInstance->getUuid();
            $context = SerializationContext::create()->setGroups('worker');
            $device = $deviceInstance->getDevice();
            $deviceJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

            if ($device->getVirtuality() == 0) {
                $this->logger->info($device->getTemplate());
                preg_match_all('!\d+!', $device->getTemplate(), $templateNumber);
                $this->logger->info(json_encode($templateNumber, true));
                $template = $this->deviceRepository->find($templateNumber[0][0]);
                
                $tmp = json_decode($deviceJson, true, 4096, JSON_OBJECT_AS_ARRAY);
                foreach ($tmp['deviceInstances'] as $key => $tmpDeviceInstance) {
                    if ($tmpDeviceInstance['uuid'] == $deviceInstance->getUuid()) {
                        if ($template->getOutlet()) {
                            $tmp['deviceInstances'][$key]['device']['outlet'] = [
                                'outlet' => $template->getOutlet()->getOutlet(),
                                'pdu' => [
                                    'ip' => $template->getOutlet()->getPdu()->getIp(),
                                    'model' => $template->getOutlet()->getPdu()->getModel(),
                                    'brand' => $template->getOutlet()->getPdu()->getBrand()
                                ]
                            ];
                        }
                    }
                }
                $deviceJson = json_encode($tmp, 0, 4096);
            }

            $this->logger->info('Sending device instance '.$uuid.' start message.');
            //$this->logger->debug('Sending device instance '.$uuid.' start message.', json_decode($deviceJson, true));
            $this->bus->dispatch(
                new InstanceActionMessage($deviceJson, $uuid, InstanceActionMessage::ACTION_START), [
                    new AmqpStamp($workerIp, AMQP_NOPARAM, []),
                ]
            );
        }
    }
}
