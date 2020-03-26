<?php

namespace App\Instance;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use App\Message\InstanceMessage;
use JMS\Serializer\SerializerInterface;
use App\Entity\NetworkInterfaceInstance;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use App\Repository\LabInstanceRepository;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
use App\Repository\DeviceInstanceRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Repository\NetworkInterfaceInstanceRepository;

class InstanceManager
{
    protected $bus;
    protected $logger;
    protected $serializer;
    protected $entityManager;
    protected $userRepository;
    protected $groupRepository;
    protected $labInstanceRepository;
    protected $deviceInstanceRepository;
    protected $networkInterfaceInstanceRepository;

    public function __construct(
        MessageBusInterface $bus,
        LoggerInterface $logger,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        NetworkInterfaceInstanceRepository $networkInterfaceInstanceRepository
    ) {
        $this->bus = $bus;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->networkInterfaceInstanceRepository = $networkInterfaceInstanceRepository;
    }

    /**
     * Start a device instance by it's UUID.
     *
     * @param string $uuid
     * @return void
     */
    public function start(string $uuid)
    {
        $deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid]);
        $device = $deviceInstance->getDevice();
        $lab = $deviceInstance->getLab();
        $user = $deviceInstance->getUser();
        $this->logger->debug("Starting device instance with UUID " . $uuid . ".");

        foreach ($device->getNetworkInterfaces() as $networkInterface) {
            $this->logger->debug("Looking for Network interface " . $networkInterface->getName() . " of device " . $device->getName() . " in device instance " . $uuid);

            if ($deviceInstance->getNetworkInterfaceInstance($networkInterface) == null) {
                $networkInterfaceInstance = NetworkInterfaceInstance::create()
                    ->setNetworkInterface($networkInterface)
                    ->setLab($lab)
                    ->setOwnedBy($deviceInstance->getOwnedBy());
                $this->logger->debug("Network interface instance created by " . $user->getUsername() . " for lab " . $lab->getName() . " and for " . $networkInterface->getName());

                // if vnc access is requested, ask for a free port and register it
                if ($networkInterface->getSettings()->getProtocol() == "VNC") {
                    $this->logger->debug("Network interface " . $networkInterface->getName() . " of device " . $device->getName() . " for lab " . $lab->getName() . " uses for VNC");
                    $remotePort = $this->getRemoteAvailablePort();
                    $networkInterfaceInstance->setRemotePort($remotePort);
                    try {
                        $this->createDeviceInstanceProxyRoute($deviceInstance->getUuid(), $remotePort);
                    } catch (ServerException $exception) {
                        $this->logger->error($exception->getResponse()->getBody()->getContents());
                        throw $exception;
                    }
                } else
                    $this->logger->debug("Network interface " . $networkInterface->getName() . " of device " . $device->getName() . " for lab " . $lab->getName() . " no control protocol defined");

                $networkInterface->addInstance($networkInterfaceInstance);
                $deviceInstance->addNetworkInterfaceInstance($networkInterfaceInstance);

                $this->entityManager->persist($networkInterfaceInstance);
                $this->entityManager->persist($deviceInstance);
                $this->entityManager->persist($networkInterface);
            } else
                $this->logger->debug("Network interface instance existed in lab " . $lab->getName());
        }

        $deviceInstance->setState(InstanceState::STARTING);
        // $networkInterfaceInstance don't exist outside of this block. We have to save it before to quit this block
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups("start_lab");
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

        $this->logger->info('Sending device instance ' . $uuid . ' start message.', json_decode($labJson, true));
        $this->bus->dispatch(
            new InstanceMessage($labJson, $uuid, InstanceMessage::ACTION_START)
        );
    }

    private function getRemoteAvailablePort(): int
    {
        $client = new Client();

        $url = "http://" . getenv('WORKER_SERVER') . ":" . getenv('WORKER_PORT') . "/worker/port/free";
        try {
            $response = $client->get($url);
        } catch (RequestException $exception) {
            throw $exception;
        }

        return (int) $response->getBody()->getContents();
    }

    /**
     * @param string $uuid
     * @param integer $remotePort
     * 
     * @throws RequestException 
     * 
     * @return void
     */
    private function createDeviceInstanceProxyRoute(string $uuid, int $remotePort)
    {
        $client = new Client();

        $url = 'http://' . getenv('WEBSOCKET_PROXY_SERVER') . ':' . getenv('WEBSOCKET_PROXY_API_PORT') . '/api/routes/device/' . $uuid;
        $this->logger->debug("Create route in proxy " . $url);

        try {
            $client->post($url, [
                'body' => json_encode([
                    'target' => 'ws://' . getenv('WORKER_SERVER') . ':' . ($remotePort + 1000) . ''
                ]),
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (RequestException $exception) {
        } catch (ServerException $exception) {
            throw $exception;
        }
    }
}
