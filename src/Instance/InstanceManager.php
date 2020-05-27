<?php

namespace App\Instance;

use Exception;
use App\Entity\Lab;
use GuzzleHttp\Client;
use App\Entity\LabInstance;
use Psr\Log\LoggerInterface;
use App\Entity\DeviceInstance;
use App\Exception\WorkerException;
use App\Entity\InstancierInterface;
use App\Message\InstanceStateMessage;
use App\Message\InstanceActionMessage;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * Creates a new lab instance.
     * 
     * @param Lab $lab The lab to instanciate.
     * @param InstancierInterface $instancier The owner of the new instance.
     * @return LabInstance
     */
    public function create(Lab $lab, InstancierInterface $instancier)
    {
        $labInstance = LabInstance::create()
            ->setLab($lab)
            ->setInternetConnected(false)
            ->setInterconnected(false);

        switch ($instancier->getType()) {
            case 'user':
                $labInstance->setUser($instancier)
                    ->setOwnedBy($instancier->getType());
                break;

            case 'group':
                $labInstance->setGroup($instancier);
                break;

            default:
                throw new Exception('Instancier must be an instance of User or Group.');
        }

        $labInstance
            ->setOwnedBy($instancier->getType())
            ->setState(InstanceStateMessage::STATE_CREATING)
            ->populate();

        $this->entityManager->persist($labInstance);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups("start_lab");
        $labJson = $this->serializer->serialize($labInstance, 'json', $context);
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $labInstance->getUuid(), InstanceActionMessage::ACTION_CREATE)
        );

        return $labInstance;
    }

    /**
     * Deletes a lab instance.
     * 
     * @param LabInstance $lab The lab instance to delete.
     * 
     * @return void
     */
    public function delete(LabInstance $labInstance)
    {
        $context = SerializationContext::create()->setGroups("stop_lab");
        $labJson = $this->serializer->serialize($labInstance, 'json', $context);
        $labInstance->setState(InstanceStateMessage::STATE_DELETING);
        $this->entityManager->persist($labInstance);
        $this->entityManager->flush();
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $labInstance->getUuid(), InstanceActionMessage::ACTION_DELETE)
        );
    }

    /**
     * Start a device instance.
     *
     * @param DeviceInstance $deviceInstance
     * @return void
     */
    public function start(DeviceInstance $deviceInstance)
    {
        $lab = $deviceInstance->getLab();
        $user = $deviceInstance->getUser();
        $uuid = $deviceInstance->getUuid();
        $device = $deviceInstance->getDevice();

        $this->logger->debug("Starting device instance with UUID " . $uuid . ".");

        /** @var NetworkInterfaceInstance */
        foreach ($deviceInstance->getNetworkInterfaceInstances() as $networkInterfaceInstance) {
            $networkInterface = $networkInterfaceInstance->getNetworkInterface();
            $this->logger->debug('Looking for Network interface ' . $networkInterface->getName() . ' of device ' . $device->getName() . ' in device instance ' . $uuid);

            // if vnc access is requested, ask for a free port and register it
            if ('VNC' == $networkInterface->getSettings()->getProtocol()) {
                $this->logger->debug('Network interface ' . $networkInterface->getName() . ' of device ' . $device->getName() . ' for lab ' . $lab->getName() . ' uses for VNC');
                $remotePort = $this->getRemoteAvailablePort();
                $networkInterfaceInstance->setRemotePort($remotePort);

                $this->entityManager->persist($networkInterfaceInstance);
            } else {
                $this->logger->debug('Network interface ' . $networkInterface->getName() . ' of device ' . $device->getName() . ' for lab ' . $lab->getName() . ' no control protocol defined');
            }
        }

        $deviceInstance->setState(InstanceState::STARTING);
        // $networkInterfaceInstance don't exist outside of this block. We have to save it before to quit this block
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups("start_lab");
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

        $this->logger->info('Sending device instance ' . $uuid . ' start message.', json_decode($labJson, true));
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_START)
        );
    }

    /**
     * Stop a device instance.
     */
    public function stop(DeviceInstance $deviceInstance)
    {
        $lab = $deviceInstance->getLab();
        $user = $deviceInstance->getUser();
        $uuid = $deviceInstance->getUuid();
        $device = $deviceInstance->getDevice();

        $this->logger->debug("Stopping device instance with UUID " . $uuid . ".");

        /** @var NetworkInterfaceInstance */
        foreach ($deviceInstance->getNetworkInterfaceInstances() as $networkInterfaceInstance) {
            $networkInterface = $networkInterfaceInstance->getNetworkInterface();
            $this->logger->debug("Looking for Network interface " . $networkInterface->getName() . " of device " . $device->getName() . " in device instance " . $uuid);

            // if vnc access is requested, ask for a free port and register it
            if ('VNC' == $networkInterface->getSettings()->getProtocol()) {
                $this->logger->debug('Network interface ' . $networkInterface->getName() . ' of device ' . $device->getName() . ' for lab ' . $lab->getName() . ' uses for VNC');
                try {
                    $this->deleteDeviceInstanceProxyRoute($deviceInstance->getUuid());
                } catch (ServerException $exception) {
                    $this->logger->error($exception->getResponse()->getBody()->getContents());
                    throw $exception;
                } catch (RequestException $exception) {
                    $this->logger->warning("Route has already been deleted.", ["exception" => $exception]);
                }

                $networkInterfaceInstance->setRemotePort(0);
                $this->entityManager->persist($networkInterfaceInstance);
            } else {
                $this->logger->debug('Network interface ' . $networkInterface->getName() . ' of device ' . $device->getName() . ' for lab ' . $lab->getName() . ' no control protocol defined');
            }
        }

        $deviceInstance->setState(InstanceState::STOPPING);
        // $networkInterfaceInstance don't exist outside of this block. We have to save it before to quit this block
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups("stop_lab");
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

        $this->logger->info('Sending device instance ' . $uuid . ' start message.', json_decode($labJson, true));
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_STOP)
        );
    }

    // public function state(DeviceInstance $deviceInstance)
    // {
    //     $client = new Client();
    //     $uuid = $deviceInstance->getUuid();

    //     $url = "http://" . getenv('WORKER_SERVER') . ":" . getenv('WORKER_PORT') . "/" . $uuid . "/state";

    //     try {
    //         $response = $client->get($url);
    //     } catch (RequestException $exception) {
    //         throw new NotFoundHttpException();
    //     }

    //     return $response->getBody()->getContents();
    // }

    public function getRemoteAvailablePort(): int
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
     * @return void
     */
    public function createDeviceInstanceProxyRoute(string $uuid, int $remotePort)
    {
        $client = new Client();

        $url = 'http://localhost:' . getenv('WEBSOCKET_PROXY_API_PORT') . '/api/routes/device/' . $uuid;
        $this->logger->debug("Create route in proxy " . $url);

        $client->post($url, [
            'body' => json_encode([
                'target' => 'ws://' . getenv('WORKER_SERVER') . ':' . ($remotePort + 1000) . ''
            ]),
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    /**
     * @param string $uuid
     * @param integer $remotePort
     * 
     * @return void
     */
    public function deleteDeviceInstanceProxyRoute(string $uuid)
    {
        $client = new Client();

        $url = 'http://localhost:' . getenv('WEBSOCKET_PROXY_API_PORT') . '/api/routes/device/' . $uuid;
        $this->logger->debug("Delete route in proxy " . $url);

        $client->delete($url);
    }
}
