<?php

namespace App\Service\Instance;

use App\Entity\Device;
use App\Entity\DeviceInstance;
use App\Entity\InstancierInterface;
use App\Entity\Lab;
use App\Entity\LabInstance;
use App\Entity\NetworkInterfaceInstance;
use App\Entity\OperatingSystem;
use App\Instance\InstanceState;
use Remotelabz\Message\Message\InstanceActionMessage;
use Remotelabz\Message\Message\InstanceStateMessage;
use App\Repository\DeviceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\NetworkInterfaceInstanceRepository;
use App\Repository\OperatingSystemRepository;
use App\Service\Network\NetworkManager;
use App\Service\Proxy\ProxyManager;
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
    protected $proxyManager;

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
        ProxyManager $proxyManager
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
        $this->proxyManager = $proxyManager;
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
        $uuid = $deviceInstance->getUuid();
        $device = $deviceInstance->getDevice();

        $this->logger->debug('Starting device instance with UUID '.$uuid.'.');

        if (true === $device->getVnc()) {
            $remotePort = $this->getRemoteAvailablePort();
            $deviceInstance->setRemotePort($remotePort);
            $this->entityManager->persist($deviceInstance);
        }

        $deviceInstance->setState(InstanceState::STARTING);
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
        $uuid = $deviceInstance->getUuid();

        $deviceInstance->setState(InstanceState::STOPPING);
        $this->entityManager->persist($deviceInstance);
        $this->entityManager->flush();

        $this->logger->debug('Stopping device instance with UUID '.$uuid.'.');

        $context = SerializationContext::create()->setGroups('stop_lab');
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

        $this->logger->info('Sending device instance '.$uuid.' stop message.', json_decode($labJson, true));
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_STOP)
        );
    }

    /**
     * Export a device instance to template.
     */
    public function export(DeviceInstance $deviceInstance)
    {
        $uuid = $deviceInstance->getUuid();

        $deviceInstance->setState(InstanceState::EXPORTING);
        $this->entityManager->persist($deviceInstance);
        $this->entityManager->flush();

        $this->logger->debug('Exporting device instance with UUID ' . $uuid . '.');

        // TODO: 
        //  - Send Export message to Worker
        //  - Transform into device + os
        /*
        $newOS = $this->copyOperatingSystem($operatingSystem);
        $newDevice = $this->copyDevice($deviceInstance->getDevice());
        */
    }

    public function setStopped(DeviceInstance $deviceInstance)
    {
        $this->logger->info('Device instance has been stopped correctly by worker. Performing state change.', [
            'instance' => $deviceInstance
        ]);
        $device = $deviceInstance->getDevice();

        if (true === $device->getVnc()) {
            $this->logger->info('Deleting proxy route');
            try {
                $this->proxyManager->deleteDeviceInstanceProxyRoute($deviceInstance->getUuid());
            } catch (ServerException $exception) {
                $this->logger->error($exception->getResponse()->getBody()->getContents());
                throw $exception;
            } catch (RequestException $exception) {
                $this->logger->warning('Route has already been deleted.', ['exception' => $exception]);
            }

            $deviceInstance->setRemotePort(null);
            $this->entityManager->persist($deviceInstance);
        }

        $this->entityManager->flush();

        $this->logger->info('Device instance state changed.');
    }

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

    /*
    public function copyDevice(Device device): Device
    {
        $newDevice = Device::create();
        $newDevice->setName($device->getName() . "_test");
        $newDevice->setBrand($device->getBrand());
        $newDevice->setModel($device->getModel());
        $newDevice->setFlavor($device->getFlavor());
        //$newDevice->setOperatingSystem();
        $newDevice->setIsTemplate(true);
    }
    */
}
