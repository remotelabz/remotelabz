<?php

namespace App\Service\Instance;

use App\Entity\DeviceInstance;
use App\Entity\InstancierInterface;
use App\Entity\Lab;
use App\Entity\LabInstance;
use App\Entity\NetworkInterfaceInstance;
use App\Instance\InstanceState;
use Remotelabz\Message\Message\InstanceActionMessage;
use Remotelabz\Message\Message\InstanceStateMessage;
use App\Repository\DeviceInstanceRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\NetworkInterfaceInstanceRepository;
use App\Service\Network\NetworkManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Remotelabz\NetworkBundle\Exception\NoNetworkAvailableException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @since Remotelabz v2.2.0
 */
class InstanceManager
{
    protected $bus;
    /** @var Client */
    protected $client;
    protected $logger;
    protected $serializer;
    protected $entityManager;
    protected $userRepository;
    protected $groupRepository;
    protected $networkManager;
    protected $labInstanceRepository;
    protected $deviceInstanceRepository;
    protected $networkInterfaceInstanceRepository;
    protected $workerServer;
    protected $workerPort;
    protected $websocketProxyApiPort;
    protected $websocketProxyPort;

    public function __construct(
        LoggerInterface $logger,
        ClientInterface $client,
        MessageBusInterface $bus,
        NetworkManager $networkManager,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        NetworkInterfaceInstanceRepository $networkInterfaceInstanceRepository,
        string $workerServer,
        string $workerPort,
        string $websocketProxyPort,
        string $websocketProxyApiPort
    ) {
        $this->bus = $bus;
        $this->logger = $logger;
        $this->client = $client;
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
        $this->networkManager = $networkManager;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->networkInterfaceInstanceRepository = $networkInterfaceInstanceRepository;
        $this->workerServer = $workerServer;
        $this->workerPort = $workerPort;
        $this->websocketProxyPort = $websocketProxyPort;
        $this->websocketProxyApiPort = $websocketProxyApiPort;
    }

    /**
     * Creates a new lab instance.
     *
     * @param Lab                 $lab        the lab to instanciate
     * @param InstancierInterface $instancier the owner of the new instance
     *
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

        $network = $this->networkManager->getAvailableSubnet();

        if (!$network) {
            throw new NoNetworkAvailableException();
        }
        $labInstance
            ->setOwnedBy($instancier->getType())
            ->setState(InstanceStateMessage::STATE_CREATING)
            ->setNetwork($network)
            ->populate();

        $this->entityManager->persist($labInstance);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups('start_lab');
        $labJson = $this->serializer->serialize($labInstance, 'json', $context);
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $labInstance->getUuid(), InstanceActionMessage::ACTION_CREATE)
        );

        return $labInstance;
    }

    /**
     * Deletes a lab instance.
     *
     * @param LabInstance $lab the lab instance to delete
     *
     * @return void
     */
    public function delete(LabInstance $labInstance)
    {
        $context = SerializationContext::create()->setGroups('stop_lab');
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
     * @return void
     */
    public function start(DeviceInstance $deviceInstance)
    {
        $lab = $deviceInstance->getLab();
        $user = $deviceInstance->getUser();
        $uuid = $deviceInstance->getUuid();
        $device = $deviceInstance->getDevice();

        $this->logger->debug('Starting device instance with UUID '.$uuid.'.');

        /* @var NetworkInterfaceInstance */
        foreach ($deviceInstance->getNetworkInterfaceInstances() as $networkInterfaceInstance) {
            $networkInterface = $networkInterfaceInstance->getNetworkInterface();
            $this->logger->debug('Looking for Network interface '.$networkInterface->getName().' of device '.$device->getName().' in device instance '.$uuid);

            // if vnc access is requested, ask for a free port and register it
            if ('VNC' == $networkInterface->getSettings()->getProtocol()) {
                $this->logger->debug('Network interface '.$networkInterface->getName().' of device '.$device->getName().' for lab '.$lab->getName().' uses for VNC');
                $remotePort = $this->getRemoteAvailablePort();
                $networkInterfaceInstance->setRemotePort($remotePort);

                $this->entityManager->persist($networkInterfaceInstance);
            } else {
                $this->logger->debug('Network interface '.$networkInterface->getName().' of device '.$device->getName().' for lab '.$lab->getName().' no control protocol defined');
            }
        }

        $deviceInstance->setState(InstanceState::STARTING);
        // $networkInterfaceInstance don't exist outside of this block. We have to save it before to quit this block
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups('start_lab');
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

        $this->logger->info('Sending device instance '.$uuid.' start message.', json_decode($labJson, true));
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

        $this->logger->debug('Stopping device instance with UUID '.$uuid.'.');

        /* @var NetworkInterfaceInstance */
        foreach ($deviceInstance->getNetworkInterfaceInstances() as $networkInterfaceInstance) {
            $networkInterface = $networkInterfaceInstance->getNetworkInterface();
            $this->logger->debug('Looking for Network interface '.$networkInterface->getName().' of device '.$device->getName().' in device instance '.$uuid);

            // if vnc access is requested, ask for a free port and register it
            if ('VNC' == $networkInterface->getSettings()->getProtocol()) {
                $this->logger->debug('Network interface '.$networkInterface->getName().' of device '.$device->getName().' for lab '.$lab->getName().' uses for VNC');
                try {
                    $this->deleteDeviceInstanceProxyRoute($deviceInstance->getUuid());
                } catch (ServerException $exception) {
                    $this->logger->error($exception->getResponse()->getBody()->getContents());
                    throw $exception;
                } catch (RequestException $exception) {
                    $this->logger->warning('Route has already been deleted.', ['exception' => $exception]);
                }

                $networkInterfaceInstance->setRemotePort(0);
                $this->entityManager->persist($networkInterfaceInstance);
            } else {
                $this->logger->debug('Network interface '.$networkInterface->getName().' of device '.$device->getName().' for lab '.$lab->getName().' no control protocol defined');
            }
        }

        $deviceInstance->setState(InstanceState::STOPPING);
        // $networkInterfaceInstance don't exist outside of this block. We have to save it before to quit this block
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups('stop_lab');
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

        $this->logger->info('Sending device instance '.$uuid.' start message.', json_decode($labJson, true));
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
        $url = 'http://'.$this->workerServer.':'.$this->workerPort.'/worker/port/free';
        $this->logger->debug('Request the remote available port at '.$url);
        try {
            $response = $this->client->get($url);
        } catch (RequestException $exception) {
            throw $exception;
        }

        return (int) $response->getBody()->getContents();
    }

    /**
     * @return void
     */
    public function createDeviceInstanceProxyRoute(string $uuid, int $remotePort)
    {
        $url = 'http://localhost:'.$this->websocketProxyApiPort.'/api/routes/device/'.$uuid;
        $this->logger->debug('Create route in proxy '.$url);

        $this->client->post($url, [
            'body' => json_encode([
                'target' => 'ws://'.$this->workerServer.':'.($remotePort + 1000).'',
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * @param int $remotePort
     *
     * @return void
     */
    public function deleteDeviceInstanceProxyRoute(string $uuid)
    {
        $client = new Client();

        $url = 'http://localhost:'.$this->websocketProxyApiPort.'/api/routes/device/'.$uuid;
        $this->logger->debug('Delete route in proxy '.$url);

        $client->delete($url);
    }

    public function connectLabInstanceToInternet(string $uuid)
    {
        $labInstance = $this->labInstanceRepository->findOneBy(['uuid' => $uuid]);

        $this->logger->debug('Sending internet connection request message.', [
            'uuid' => $labInstance->getUuid()
        ]);

        $context = SerializationContext::create()->setGroups('start_lab');
        $labJson = $this->serializer->serialize($labInstance, 'json', $context);

        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_CONNECT)
        );
    }
}
