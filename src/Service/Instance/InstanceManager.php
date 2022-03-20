<?php

namespace App\Service\Instance;

use DateTime;
use App\Entity\Device;
use App\Entity\DeviceInstance;
use App\Entity\InstancierInterface;
use App\Entity\Lab;
use App\Entity\LabInstance;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;
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
    protected $OperatingSystemRepository;
    protected $DeviceRepository;
    protected $workerSerializationGroups = [
        'worker'
    ];

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
        DeviceRepository $DeviceRepository,
        OperatingSystemRepository $OperatingSystemRepository,
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
        $this->DeviceRepository=$DeviceRepository;
        $this->OperatingSystemRepository=$OperatingSystemRepository;
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

        $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
        $labJson = $this->serializer->serialize($labInstance, 'json', $context);
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $labInstance->getUuid(), InstanceActionMessage::ACTION_CREATE)
        );

        return $labInstance;
    }

    /**
     * Deletes a lab instance.
     *
     * @param LabInstance $labInstance the lab instance to delete
     *
     * @return void
     */
    public function delete(LabInstance $labInstance)
    {
        $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
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
     * @return string The lab instance JSON string
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

        $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);
        //$labJson = $this->serializer->serialize($deviceInstance, 'json', $context);

        $this->logger->info('Sending device instance '.$uuid.' start message.', json_decode($labJson, true));
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_START)
        );

        return $labJson;
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

        $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

        $this->logger->info('Sending device instance '.$uuid.' stop message.', json_decode($labJson, true));
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_STOP)
        );
    }

    /**
     * Export a device template to new device template.
     */
    public function export(DeviceInstance $deviceInstance, string $name)
    {
        $uuid = $deviceInstance->getUuid();
        $deviceInstance->setState(InstanceState::EXPORTING);
        $this->entityManager->persist($deviceInstance);
        $this->entityManager->flush();

        $this->logger->debug('Exporting device instance with UUID ' . $uuid . '.');
        
        $device = $deviceInstance->getDevice();
        $hypervisor=$device->getHypervisor();
        $operatingSystem = $device->getOperatingSystem();

        $now = new DateTime();
        $imageName = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $name);
        $id = uniqid();
        $imageName .= '_' . $now->format('Y-m-d-H:i:s') . '_' . substr($id, strlen($id) -3, strlen($id) -1);
        

       /* switch ($hypervisor) {
            case "lxc":
                $imageName="lxc://".$imageName;
                break;
            case "qemu":
                $imageName="qemu://".$imageName.'.img';
                break;
            default:
                $imageName="qemu://".$imageName.'.img';
        }*/
        $this->logger->debug('Export process. New name will be :'.$imageName);

        $newOS = $this->copyOperatingSystem($operatingSystem, $name, $imageName);
        $newDevice = $this->copyDevice($device, $newOS, $name);
        $this->entityManager->persist($newOS);
        $this->entityManager->persist($newDevice);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups('api_get_lab_instance');
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);
        $this->logger->debug('Param of device instance '.$uuid, json_decode($labJson, true));

        $tmp = json_decode($labJson, true, 4096, JSON_OBJECT_AS_ARRAY);
        $tmp['new_os_name']=$name;
        $tmp['new_os_imagename'] = $imageName;
        $tmp['newOS_id'] = $newOS->getId();
        $tmp['newDevice_id'] = $newDevice->getId();
        $labJson = json_encode($tmp, 0, 4096);

        $this->logger->debug('Sending device instance '.$uuid.' export message.', json_decode($labJson, true));
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_EXPORT)
        );
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
                $uuid=$deviceInstance->getUuid();
                $this->proxyManager->deleteDeviceInstanceProxyRoute($uuid);
                $this->logger->info('Route has been deleted for device uuid:'.$uuid);
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

    public function copyOperatingSystem(OperatingSystem $operatingSystem, string $name, string $imageName): OperatingSystem
    {
        $newOS = new OperatingSystem();
        $newOS->setName($name);
        $newOS->setImageFilename($imageName);
        $newOS->setHypervisor($operatingSystem->getHypervisor());

        return $newOS;
    }

    public function copyDevice(Device $device, OperatingSystem $os, string $name): Device
    {
        $newDevice = new Device();
        $newDevice->setName($name);
        $newDevice->setBrand($device->getBrand());
        $newDevice->setModel($device->getModel());
        $newDevice->setFlavor($device->getFlavor());
        $newDevice->setType($device->getType());
        $newDevice->setHypervisor($device->getHypervisor());
        $newDevice->setOperatingSystem($os);
        $newDevice->setIsTemplate(true);

        $i=0;
        foreach ($device->getNetworkInterfaces() as $network_int) {
            $new_network_inter=new NetworkInterface();
            $new_setting=new NetworkSettings();
            $new_setting=clone $network_int->getSettings();
            
            $new_network_inter->setSettings($new_setting);
            $new_network_inter->setName("int".$i."_".$name);
            $i=$i+1;
            $new_network_inter->setIsTemplate(true);
            $newDevice->addNetworkInterface($new_network_inter);
        }

        return $newDevice;
    }

    /**
     * Delete a device form a DeviceInstance, with its os defined in options.
     * This function is used when an error occurs in export process
     *
     * @param DeviceInstance $device the device to delete
     *
     * @return void
     */
    public function deleteDev_fromexport(string $uuid, array $options = null )
    {
        
        $this->logger->debug('Execute delete action of new device template created because error received by worker when export action request');
        
        /*$context = SerializationContext::create()->setGroups('del_dev');
        $labJson = $this->serializer->serialize($return_array, 'json', $context);

        $this->logger->debug('Json received to deleteDev: ', json_decode($labJson, true));
*/
        //Delete the instance because if we are in the lab, a lab instance exist and the device template is used.
        
        if ($options) {
        $this->logger->debug('Options received ', $options);
        $os = $this->OperatingSystemRepository->find($options["newOS_id"]);
        $device = $this->DeviceRepository->find($options["newDevice_id"]);
        
        $this->entityManager->remove($os);
        $this->entityManager->remove($device);
        $this->entityManager->flush();
        }
    }
}
