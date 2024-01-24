<?php

namespace App\Service\Instance;

use DateTime;
use App\Entity\Device;
use App\Entity\DeviceInstance;
use App\Entity\InstancierInterface;
use App\Entity\EditorData;
use App\Entity\Lab;
use App\Entity\Picture;
use App\Entity\TextObject;
use App\Entity\LabInstance;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;
use App\Entity\NetworkInterfaceInstance;
use App\Entity\OperatingSystem;
use App\Entity\ControlProtocolType;
use App\Entity\ControlProtocolTypeInstance;
use App\Instance\InstanceState;
use Remotelabz\Message\Message\InstanceActionMessage;
use Remotelabz\Message\Message\InstanceStateMessage;
use App\Repository\DeviceRepository;
use App\Repository\TextObjectRepository;
use App\Repository\PictureRepository;
use App\Repository\DeviceInstanceRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\NetworkInterfaceInstanceRepository;
use App\Repository\ConfigWorkerRepository;
use App\Repository\ControlProtocolTypeRepository;
use App\Repository\ControlProtocolTypeInstanceRepository;
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
use App\Service\Worker\WorkerManager;
use App\Service\Lab\BannerManager;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


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
    protected $controlProtocolTypeInstanceRepository;
    protected $workerServer;
    protected $workerPort;
    protected $publicAddress;
    protected $rootDirectory;
    protected $proxyManager;
    protected $OperatingSystemRepository;
    protected $DeviceRepository;
    protected $configWorkerRepository;
    protected $bannerManager;
    protected $workerSerializationGroups = [
        'worker'
    ];
    protected $tokenStorage;

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
        ControlProtocolTypeInstanceRepository $controlProtocolTypeInstanceRepository,
        DeviceRepository $DeviceRepository,
        TextObjectRepository $TextObjectRepository,
        PictureRepository $PictureRepository,
        OperatingSystemRepository $OperatingSystemRepository,
        ConfigWorkerRepository $configWorkerRepository,
        string $workerServer,
        string $workerPort,
        string $rootDirectory,
        ProxyManager $proxyManager,
        WorkerManager $workerManager,
        BannerManager $bannerManager,
        TokenStorageInterface $tokenStorage
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
        $this->controlProtocolTypeInstanceRepository = $controlProtocolTypeInstanceRepository;
        $this->DeviceRepository=$DeviceRepository;
        $this->TextObjectRepository=$TextObjectRepository;
        $this->PictureRepository=$PictureRepository;
        $this->OperatingSystemRepository=$OperatingSystemRepository;
        $this->workerServer = $workerServer;
        $this->workerPort = $workerPort;
        $this->rootDirectory = $rootDirectory;
        $this->proxyManager = $proxyManager;
        $this->workerManager = $workerManager;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->bannerManager = $bannerManager;
        $this->tokenStorage = $tokenStorage;
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
        
        $worker = $this->workerManager->getFreeWorker($lab);
        if ($worker == null) {
            $this->logger->error('Could not create instance. No worker available');
            throw new BadRequestHttpException('No worker available');
        }

        $this->logger->debug("worker avalaible from create function in InstanceManager:".$worker);
        $labInstance = LabInstance::create()
            ->setLab($lab)
            ->setworkerIp($worker)
            ->setInternetConnected(false)
            ->setInterconnected(false);

        switch ($instancier->getType()) {
            case 'user':
                $labInstance->setUser($instancier)
                    ->setOwnedBy($instancier->getType());
                break;

            case 'guest':
                $labInstance->setGuest($instancier)
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

        if ($lab->getHasTimer() == true) {
            $timer = explode(":",$lab->getTimer());
            $date = new \DateTime();
            $date->modify('+ '.$timer[0].' hours + ' . $timer[1]. ' minutes + ' .$timer[2]. ' seconds');
            $labInstance->setTimerEnd($date);
        }

        $this->entityManager->persist($labInstance);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
        $labJson = $this->serializer->serialize($labInstance, 'json', $context);
 
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $labInstance->getUuid(), InstanceActionMessage::ACTION_CREATE), [
                new AmqpStamp($worker, AMQP_NOPARAM, []),
            ]
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
        $workerIP = $labInstance->getWorkerIp();
        $worker = $this->configWorkerRepository->findOneBy(["IPv4"=>$workerIP]);
        if ($worker->getAvailable() == true) {
            $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
            $labJson = $this->serializer->serialize($labInstance, 'json', $context);
            $labInstance->setState(InstanceStateMessage::STATE_DELETING);
            $this->entityManager->persist($labInstance);
            $this->entityManager->flush();
            $this->bus->dispatch(
                new InstanceActionMessage($labJson, $labInstance->getUuid(), InstanceActionMessage::ACTION_DELETE), [
                    new AmqpStamp($workerIP, AMQP_NOPARAM, []),
                ]
            );
        }
        else {
            $this->logger->error('Could not delete instance. Worker '.$workerIP.' is suspended.');
            throw new BadRequestHttpException('Worker '.$workerIP.' is suspended');
        }
        
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

        $workerIP = $deviceInstance->getLabInstance()->getWorkerIp();
        $worker = $this->configWorkerRepository->findOneBy(["IPv4"=>$workerIP]);
        if ($worker->getAvailable() == true) {
            foreach ($deviceInstance->getControlProtocolTypeInstances() as $control_protocol_instance) {
                $control_protocol_instance->setPort($this->getRemoteAvailablePort($workerIP));
                $this->entityManager->persist($control_protocol_instance);
            }

            $deviceInstance->setState(InstanceState::STARTING);
            $this->entityManager->flush();

            $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
            $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);
            //$labJson = $this->serializer->serialize($deviceInstance, 'json', $context);

            $this->logger->info('Sending device instance '.$uuid.' start message');
            $this->logger->debug('Sending device instance '.$uuid.' start message', json_decode($labJson, true));
            $this->bus->dispatch(
                new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_START), [
                    new AmqpStamp($workerIP, AMQP_NOPARAM, []),
                ]
            );

            return $labJson;
        }
        else {
            $this->logger->error('Could not start device instance '.$uuid.'. Worker '.$workerIP.' is suspended.');
            throw new BadRequestHttpException('Worker '.$workerIP.' is suspended');
        }
    }

    /**
     * Stop a device instance.
     */
    public function stop(DeviceInstance $deviceInstance)
    {
        $uuid = $deviceInstance->getUuid();
        $workerIP = $deviceInstance->getLabInstance()->getWorkerIp();
        $worker = $this->configWorkerRepository->findOneBy(["IPv4"=>$workerIP]);

        if ($worker->getAvailable() == true) {
            $deviceInstance->setState(InstanceState::STOPPING);
            $this->entityManager->persist($deviceInstance);
            $this->entityManager->flush();

            $this->logger->debug('Stopping device instance with UUID '.$uuid.'.');

            $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
            $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

            
            $this->logger->debug('Sending device instance '.$uuid.' stop message.', json_decode($labJson, true));
            $this->logger->info('Sending device instance '.$uuid.' stop message.');
            $this->bus->dispatch(
                new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_STOP), [
                    new AmqpStamp($workerIP, AMQP_NOPARAM, []),
                ]
            );
        }
        else {
            $this->logger->error('Could not stop device instance '.$uuid.'. Worker '.$workerIP.' is suspended.');
            throw new BadRequestHttpException('Worker '.$workerIP.' is suspended');
        }
    }

    /**
     * Reset a device instance.
     */
    public function reset(DeviceInstance $deviceInstance)
    {
        $uuid = $deviceInstance->getUuid();
        $workerIP = $deviceInstance->getLabInstance()->getWorkerIp();
        $worker = $this->configWorkerRepository->findOneBy(["IPv4"=>$workerIP]);
        $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
        
        $deviceJson = $this->serializer->serialize($deviceInstance, 'json', $context);
        $tmp = json_decode($deviceJson, true, 4096, JSON_OBJECT_AS_ARRAY);
        $tmp['labInstance']['uuid'] = $deviceInstance->getLabInstance()->getUuid();
        $tmp['labInstance']['ownedBy'] = $deviceInstance->getLabInstance()->getOwnedBy();
        $tmp['labInstance']['owner']['uuid'] = $deviceInstance->getLabInstance()->getOwner()->getUuid();
        $deviceJson = json_encode($tmp, 0, 4096);

        if ($worker->getAvailable() == true) {
            $deviceInstance->setState(InstanceState::RESETTING);
            $this->entityManager->persist($deviceInstance);
            $this->entityManager->flush();

            $this->logger->debug('Resetting device instance with UUID '.$uuid.'.');

            $this->bus->dispatch(
                new InstanceActionMessage($deviceJson, $uuid, InstanceActionMessage::ACTION_RESET), [
                    new AmqpStamp($workerIP, AMQP_NOPARAM, []),
                ]
            );
        }
        else {
            $this->logger->error('Could not stop device instance '.$uuid.'. Worker '.$workerIP.' is suspended.');
            throw new BadRequestHttpException('Worker '.$workerIP.' is suspended');
        }
    }

    /**
     * Export a device template to new device template.
     */
    public function exportDevice(DeviceInstance $deviceInstance, string $name)
    {
        $uuid = $deviceInstance->getUuid();
        $worker = $this->workerManager->getFreeWorker($deviceInstance->getDevice());

        if ($worker == null) {
            $this->logger->error('Could not export device. No worker available');
            throw new BadRequestHttpException('No worker available');
        }
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
        $imageName .= '_' . $now->format('YmdHis') . '_' . substr($id, strlen($id) -3, strlen($id) -1);
        
        $this->logger->debug('Export process. Hypervisor is :'.$hypervisor->getName());

        switch ($hypervisor->getName()) {
            case "qemu":
                $imageName=$imageName.'.img';
                break;
            default:
                $imageName=$imageName;
        }
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
            new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_EXPORT_DEV), [
                new AmqpStamp($worker, AMQP_NOPARAM, []),
            ]
        );
    }

    function exportlab(LabInstance $labInstance, string $name) {
        $uuid = $labInstance->getUuid();
        $this->logger->debug('Exporting lab instance with UUID ' . $uuid . '.');

        $now = new DateTime();

        $lab = $this->copyLab($labInstance->getLab(), $name);

        $worker = $this->workerManager->getFreeWorker($lab);
        if ($worker == null) {
            $this->logger->error('Could not export lab. No worker available');
            throw new BadRequestHttpException('No worker available');
        }
        
        $this->entityManager->persist($lab);
        $this->entityManager->flush();
        if (count($lab->getPictures()) >= 1) {
            foreach($lab->getPictures() as $picture) {
                $type = explode("image/",$picture->getType())[1];
                file_put_contents($this->rootDirectory.'/assets/js/components/Editor2/images/pictures/lab'.$lab->getId().'-'.$picture->getName().'.'.$type, $picture->getData());
            }
        }
        $this->bannerManager->copyBanner($labInstance->getLab()->getId(), $lab->getId());
        if (count($labInstance->getDeviceInstances()) >= 1) {
            foreach($labInstance->getDeviceInstances() as $deviceInstance) {
                $device = $deviceInstance->getDevice();
                $osName = $device->getOperatingSystem()->getName()."_".$name;
                $deviceName = $device->getName()."_".$name;
                $deviceInstanceUuid = $deviceInstance->getUuid();
                $imageName = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $osName);
                $id = uniqid();
                $imageName .= '_' . $now->format('YmdHis') . '_' . substr($id, strlen($id) -3, strlen($id) -1);
    
                $this->logger->debug('Export process. New operatingSystem name will be :'.$imageName);
    
                $newOS = $this->copyOperatingSystem($device->getOperatingSystem(), $osName, $imageName);
                $newDevice = $this->copyDevice($device, $newOS, $deviceName);
                if ($device->getTemplate() !== null) {
                    $newDevice->setTemplate($device->getTemplate());
                }
                $this->entityManager->persist($newOS);
    
                $newEditorData = new EditorData();
                $newEditorData->setY($device->getEditorData()->getY());
                $newEditorData->setX($device->getEditorData()->getX());
                $this->entityManager->persist($newEditorData);
                $newDevice->setEditorData($newEditorData);
                $this->entityManager->persist($newDevice);
                $newEditorData->setDevice($newDevice);
    
                $lab->addDevice($newDevice);
                $this->entityManager->flush();
    
                $context = SerializationContext::create()->setGroups('api_get_lab_instance');
                $labJson = $this->serializer->serialize($labInstance, 'json', $context);
                $this->logger->debug('Param of device instance '.$deviceInstanceUuid, json_decode($labJson, true));
    
                $tmp = json_decode($labJson, true, 4096, JSON_OBJECT_AS_ARRAY);
                for($i = 0; $i < count($tmp['deviceInstances']); $i++) {
                    if ($tmp['deviceInstances'][$i]['uuid'] == $deviceInstanceUuid) {
                        $tmp['deviceInstances'][$i]['new_os_name'] = $deviceName;
                        $tmp['deviceInstances'][$i]['new_os_imagename'] = $imageName;
                        $tmp['deviceInstances'][$i]['newOS_id'] = $newOS->getId();
                        $tmp['deviceInstances'][$i]['newDevice_id'] = $newDevice->getId();
                    }
                }
    
                $labJson = json_encode($tmp, 0, 4096);
    
            }
        }

        $this->logger->debug('Sending lab instance '.$uuid.' export message.', json_decode($labJson, true));
            $this->bus->dispatch(
                new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_EXPORT_LAB), [
                    new AmqpStamp($worker, AMQP_NOPARAM, []),
                ]
            );
        
    }

    public function setStopped(DeviceInstance $deviceInstance)
    {
        $this->logger->info('Device instance has been stopped correctly by worker. Performing state change.', [
            'instance' => $deviceInstance
        ]);
        $device = $deviceInstance->getDevice();
        
        $vnc=false;
        foreach($device->getControlProtocolTypes() as $controlProtocolType){
            $this->logger->debug('Test ControlProtocolType for device stopped');
            if ($controlProtocolType->getName()!="") {
                $this->logger->debug('Route found for device stopped');
                $vnc=($vnc || true);
            }
        }

        if ($vnc) {
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

            //$deviceInstance->setRemotePort(null);
            $this->entityManager->persist($deviceInstance);
        }

        $this->entityManager->flush();
        $this->logger->info('Device instance state changed.');
    }

    public function getRemoteAvailablePort($worker): int
    {
        $url = 'http://'.$worker.':'.$this->workerPort.'/worker/port/free';
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

    public function copyLab(Lab $lab, string $name): Lab
    {
        $newLab = new Lab();
        $newLab->setName($name);
        $newLab->setShortDescription($lab->getShortDescription());
        $newLab->setDescription($lab->getDescription());
        $newLab->setIsTemplate(true);
        $newLab->setTasks($lab->getTasks());
        $newLab->setVersion($lab->getVersion());
        $newLab->setScripttimeout($lab->getScripttimeout());
        $newLab->setLocked($lab->getLocked());
        $newLab->setBanner($lab->getBanner());
        $newLab->setIsInternetAuthorized($lab->isInternetAuthorized());

        if (count($lab->getTextobjects()) >= 1) {
            foreach($lab->getTextobjects() as $textObject) {
                $newTextObject = new TextObject();
                $newTextObject->setName($textObject->getName());
                $newTextObject->setType($textObject->getType());
                $newTextObject->setData($textObject->getData());
                $newTextObject->setLab($newLab);
                $this->entityManager->persist($newTextObject);
                $newLab->addTextobject($newTextObject);
            }
        }
        if (count($lab->getPictures()) >= 1) {
            foreach($lab->getPictures() as $picture) {
                $newPicture = new Picture();
                $newPicture->setName($picture->getName());
                $newPicture->setHeight($picture->getHeight());
                $newPicture->setWidth($picture->getWidth());
                $newPicture->setMap($picture->getMap());
                $newPicture->setType($picture->getType());
    
                $type = explode("image/",$picture->getType())[1];
                $fileName = $this->rootDirectory.'/assets/js/components/Editor2/images/pictures/lab'.$lab->getId().'-'.$picture->getName().'.'.$type;
                $fp = fopen($fileName, 'r');
                $size = filesize($fileName);
                if ($fp !== False) {
                    $data = fread($fp, $size);
                    $newPicture->setData($data);
                }
                $this->entityManager->persist($newPicture);
                $newLab->addPicture($newPicture);
            }
        }
        
        $newLab->setAuthor($this->tokenStorage->getToken()->getUser());

        return $newLab;
    }

    public function copyDevice(Device $device, OperatingSystem $os, string $name): Device
    {
        $newDevice = new Device();
        $newDevice->setName($name);
        $newDevice->setBrand($device->getBrand());
        $newDevice->setModel($device->getModel());
        $newDevice->setFlavor($device->getFlavor());
        $newDevice->setType($device->getType());
        $newDevice->setNbCpu($device->getNbCpu());
        $newDevice->setHypervisor($device->getHypervisor());
        $newDevice->setOperatingSystem($os);
        $newDevice->setIsTemplate(true);
        $newDevice->setNetworkInterfaceTemplate($device->getNetworkInterfaceTemplate());
        if($device->getIcon() != NULL) {
            $newDevice->setIcon($device->getIcon());
        }
        $newDevice->setNbSocket($device->getNbSocket());
        $newDevice->setNbCore($device->getNbCore());
        $newDevice->setNbThread($device->getNbThread());
        $newDevice->setAuthor($this->tokenStorage->getToken()->getUser());

        //$i=0;
        foreach ($device->getNetworkInterfaces() as $network_int) {
            $new_network_inter=new NetworkInterface();
            $new_setting=new NetworkSettings();
            $new_setting=clone $network_int->getSettings();
            
            $new_network_inter->setSettings($new_setting);
            $new_network_inter->setName($network_int->getName());
            $new_network_inter->setVlan($network_int->getVlan());
            $new_network_inter->setConnection($network_int->getConnection());
            $new_network_inter->setConnectorLabel($network_int->getConnectorLabel());
            $new_network_inter->setConnectorType($network_int->getConnectorType());
            //$i=$i+1;
            $new_network_inter->setIsTemplate(true);
            $newDevice->addNetworkInterface($new_network_inter);
        }
        foreach ($device->getControlProtocolTypes() as $control_protocol) {
            $newDevice->addControlProtocolType($control_protocol);
        }

        return $newDevice;
    }

    // TODO : Problem with the entitymanager closed when called to this function
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
