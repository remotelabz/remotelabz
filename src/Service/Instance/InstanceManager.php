<?php

namespace App\Service\Instance;

use DateTime;
use ErrorException;
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
use App\Bridge\Network\IPTools;
use Symfony\Component\Process\Exception\ProcessFailedException;



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
    protected $singleServer = true;

    public function __construct(
        LoggerInterface $logger,
        ClientInterface $client,
        MessageBusInterface $bus,
        NetworkManager $networkManager,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        DeviceRepository $deviceRepository,
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
        bool $singleServer,
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
        $this->deviceRepository = $deviceRepository;
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
        $this->singleServer = $singleServer;
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

        // Test is this user, guest or group has already started an instance of this lab
        // It's can occur if the user click to time, too quickly, on the "Join lab" button
        $is_exist_labinstance=null;
        switch ($instancier->getType()) {
            case 'user':
                $is_exist_labinstance=$this->labInstanceRepository->findByUserAndLab($instancier,$lab);
                break;
            case 'guest':
                $is_exist_labinstance=$this->labInstanceRepository->findByGuestAndLab($instancier,$lab);
                break;
            case 'group':
                if (count($this->labInstanceRepository->findByGroupAndLabUuid($instancier,$lab))==0)
                    $is_exist_labinstance=null;
                break;
            default:
                throw new BadRequestHttpException('Instancier type must be one of "user" or "group".');
        }     

        if (is_null($is_exist_labinstance)) {

                $worker = $this->workerManager->getFreeWorker($lab);

            if ($worker == null) {
                $this->logger->error('Could not create instance. No worker available');
                throw new Exception('No worker available');
            }
            //$this->logger->info("Worker choosen is :".$worker);
            $this->logger->debug("Worker available from create function in InstanceManager:".$worker);
            
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
        //TODO: test with local env if deploy the front and the worker on the same server
        if (!$this->singleServer) {// One server for the Front and one server for the worker
            if (IPTools::routeExists($network))
                $this->logger->debug("Route to ".$network." exists, via ".$worker);
            else {
                $this->logger->debug("Route to ".$network." doesn't exist, via ".$worker);
                IPTools::routeAdd($network,$worker);
            }
        }

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
        } else 
            return null;
        
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
            $network=$labInstance->getNetwork();
            if (IPTools::routeExists($network)) {
                $this->logger->debug("Route to ".$network." exists via ".$workerIP);
               try {
                //IPTools::routeDelete($network,$workerIP);
                IPTools::routeDelete($network,null);
               }
               catch (ErrorException $e) {
                $this->logger->error("ERROR route delete : ".$e->getMessage());
               }
            }
            else {
            $this->logger->debug("Route to ".$network." doesn't exist. The gateway must be ".$workerIP);
            }

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

        $this->logger->info('Device instance state'.$deviceInstance->getState());
        
        if ($deviceInstance->getState() == InstanceStateMessage::STATE_CREATING || 
                $deviceInstance->getState() == InstanceStateMessage::STATE_STARTING ||
                $deviceInstance->getState() == InstanceStateMessage::STATE_STARTED ||
                $deviceInstance->getState() == InstanceStateMessage::STATE_RESETTING) {
            $this->logger->info('Device instance '.$deviceInstance->getUuid().' is already running.');
            throw new BadRequestHttpException('Device already running or started');
        } else {
            $this->logger->info('Starting device instance '.$deviceInstance->getUuid().'.');
            
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
                if ($device->getVirtuality() == 0) {
                    $this->logger->info($device->getTemplate());
                    preg_match_all('!\d+!', $device->getTemplate(), $templateNumber);
                    $this->logger->info(json_encode($templateNumber, true));
                    $template = $this->deviceRepository->find($templateNumber[0][0]);
                    
                    $tmp = json_decode($labJson, true, 4096, JSON_OBJECT_AS_ARRAY);
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
                    $labJson = json_encode($tmp, 0, 4096);
                }

                $this->logger->info('Sending device instance '.$uuid.' start message');
                //$this->logger->debug('Sending device instance '.$uuid.' start message', json_decode($labJson, true));
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
    }

    /**
     * Stop a device instance.
     */
    public function stop(DeviceInstance $deviceInstance)
    {
        $uuid = $deviceInstance->getUuid();
        $workerIP = $deviceInstance->getLabInstance()->getWorkerIp();
        $worker = $this->configWorkerRepository->findOneBy(["IPv4"=>$workerIP]);
        $device = $deviceInstance->getDevice();

        if ($worker->getAvailable() == true) {
            $deviceInstance->setState(InstanceState::STOPPING);
            $this->entityManager->persist($deviceInstance);
            $this->entityManager->flush();

            $this->logger->debug('Stopping device instance with UUID '.$uuid.'.');

            $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
            $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);

            if ($device->getVirtuality() == 0) {
                $this->logger->info($device->getTemplate());
                preg_match_all('!\d+!', $device->getTemplate(), $templateNumber);
                $this->logger->info(json_encode($templateNumber, true));
                $template = $this->deviceRepository->find($templateNumber[0][0]);
                
                $tmp = json_decode($labJson, true, 4096, JSON_OBJECT_AS_ARRAY);
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
                $labJson = json_encode($tmp, 0, 4096);
            }

            //$this->logger->debug('Sending device instance '.$uuid.' stop message.', json_decode($labJson, true));
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
        $device = $deviceInstance->getDevice();
        $workerIP = $deviceInstance->getLabInstance()->getWorkerIp();
        $worker = $this->configWorkerRepository->findOneBy(["IPv4"=>$workerIP]);
        $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
        
        $deviceJson = $this->serializer->serialize($deviceInstance, 'json', $context);
        $tmp = json_decode($deviceJson, true, 4096, JSON_OBJECT_AS_ARRAY);
        $tmp['labInstance']['uuid'] = $deviceInstance->getLabInstance()->getUuid();
        $tmp['labInstance']['ownedBy'] = $deviceInstance->getLabInstance()->getOwnedBy();
        $tmp['labInstance']['owner']['uuid'] = $deviceInstance->getLabInstance()->getOwner()->getUuid();
        $deviceJson = json_encode($tmp, 0, 4096);

        if ($device->getVirtuality() == 0) {
            $this->logger->info($device->getTemplate());
            preg_match_all('!\d+!', $device->getTemplate(), $templateNumber);
            $this->logger->info(json_encode($templateNumber, true));
            $template = $this->deviceRepository->find($templateNumber[0][0]);
            
            $tmp = json_decode($deviceJson, true, 4096, JSON_OBJECT_AS_ARRAY);
            if ($tmp['uuid'] == $deviceInstance->getUuid()) {
                if ($template->getOutlet()) {
                    $tmp['device']['outlet'] = [
                        'outlet' => $template->getOutlet()->getOutlet(),
                        'pdu' => [
                            'ip' => $template->getOutlet()->getPdu()->getIp(),
                            'model' => $template->getOutlet()->getPdu()->getModel(),
                            'brand' => $template->getOutlet()->getPdu()->getBrand()
                        ]
                    ];
                }
            }
            $deviceJson = json_encode($tmp, 0, 4096);
        }

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
     * @return device the new device created
     */
    public function exportDevice(DeviceInstance $deviceInstance, string $name)
    {
        $uuid = $deviceInstance->getUuid();
        $worker = $deviceInstance->getLabInstance()->getWorkerIp();

        if ($worker == null) {
            $this->logger->error('Could not export device. No worker available');
            throw new BadRequestHttpException('No worker available');
        }
        $deviceInstance->setState(InstanceState::EXPORTING);
        $this->entityManager->persist($deviceInstance);
        $this->entityManager->flush();

        $this->logger->debug('Exporting device instance process with UUID ' . $uuid . '.');
        
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
        $this->entityManager->flush();
        
        $context = SerializationContext::create()->setGroups('api_get_lab_instance');
        $labJson = $this->serializer->serialize($deviceInstance->getLabInstance(), 'json', $context);
        //$this->logger->debug('Param of device instance '.$uuid, json_decode($labJson, true));

        $tmp = json_decode($labJson, true, 4096, JSON_OBJECT_AS_ARRAY);
        $tmp['new_os_name']=$name;
        $tmp['new_os_imagename'] = $imageName;
        $tmp['newOS_id'] = $newOS->getId();
        $tmp['newDevice_id'] = $newDevice->getId();
        $labJson = json_encode($tmp, 0, 4096);

        //$this->logger->debug('Sending device instance '.$uuid.' export message.', json_decode($labJson, true));
        $this->bus->dispatch(
            new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_EXPORT_DEV), [
                new AmqpStamp($worker, AMQP_NOPARAM, []),
            ]
        );
        return $newDevice;
       
    }

    // Copy OS on all other workers
    //TODO : change parameters to entities worker and OS, beside string. It's avoid 
    // to make a query to get worker and OS entities
    function Sync2OS(string $workerIP,string $hypervisorName,string $imageName){
        
        $workers = $this->configWorkerRepository->findAll();

        foreach ($workers as $otherWorker) {
            $otherWorkerIP=$otherWorker->getIPv4();
            if (strcmp($otherWorkerIP,$workerIP) && $otherWorker->getAvailable()) { //strcmp return 0 if equal; avoid to copy itself
                $tmp=array();
                $tmp['Worker_Dest_IP'] = $otherWorkerIP;
                $tmp['hypervisor'] = $hypervisorName;
                $tmp['os_imagename'] = $imageName;
                $deviceJsonToCopy = json_encode($tmp, 0, 4096);
                // the case of qemu image with link.
                $this->logger->debug("OS to sync from ".$workerIP." -> ".$tmp['Worker_Dest_IP'],$tmp);
                $this->bus->dispatch(
                    new InstanceActionMessage($deviceJsonToCopy, "", InstanceActionMessage::ACTION_COPY2WORKER_DEV), [
                        new AmqpStamp($workerIP, AMQP_NOPARAM, [])
                        ]
                    );
            }
        }
    }

    // Copy device on all other workers
    //TODO : change parameters to entities worker and device, beside string. It's avoid
    // to make a query to get worker and device entities
    
    function exportlab(LabInstance $labInstance, string $name) {
        $uuid = $labInstance->getUuid();
        $this->logger->info('Exporting lab instance with UUID ' . $uuid . '.');

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

                $new_name = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $device->getName()."_".$name);
                //$id = uniqid();
                $new_name .= '_' . $now->format('YmdHis');

                if ($device->getHypervisor()->getName() != "natif" && $device->getOperatingSystem()->getName() != "Service") {
                    $this->stop($deviceInstance);                   
                    $newDevice=$this->exportDevice($deviceInstance, $new_name);                  

                    $newDevice->getEditorData()->setX($device->getEditorData()->getX());
                    $newDevice->getEditorData()->setY($device->getEditorData()->getY());

                    $newDevice->setNetworkInterfaceTemplate($device->getNetworkInterfaceTemplate());
                    if($device->getIcon() != NULL) {
                        $newDevice->setIcon($device->getIcon()); 
                    }
                    /*foreach ($device->getNetworkInterfaces() as $network_int) {
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
                    }*/

                    /* $osName = $device->getOperatingSystem()->getName()."_".$name;
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
                    */

                    //$newEditorData = new EditorData();
                    //$newDevice->setEditorData($device->getEditorData());
                    //$this->logger->debug('Export process. '.$new_name." Coordinates X,Y:".$device->getEditorData()->getX().",".$device->getEditorData()->getY());                    
                    $this->entityManager->persist($newDevice);

                    $lab->addDevice($newDevice);

                    $this->entityManager->persist($lab);
                    $this->entityManager->flush();

                    /*
                    $context = SerializationContext::create()->setGroups('api_get_lab_instance');
                    $labJson = $this->serializer->serialize($labInstance, 'json', $context);
                    //$this->logger->debug('Param of device instance '.$deviceInstanceUuid, json_decode($labJson, true));

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
                    */
                } elseif ($device->getHypervisor()->getName() == "natif" || $device->getOperatingSystem()->getName() == "Service") { 
                    // Switch interne or DHCP server
                    $this->logger->debug("Copying \"system\" device instance with UUID " . $deviceInstance->getUuid() . " and name ".$deviceInstance->getDevice()->getName().".");
                    //$newOS = $this->copyOperatingSystem($device->getOperatingSystem(), $new_name, $new_name);
                    $newDevice = $this->copyDevice($device, $device->getOperatingSystem(), $new_name);

                    $newDevice->getEditorData()->setX($device->getEditorData()->getX());
                    $newDevice->getEditorData()->setY($device->getEditorData()->getY());

                    if ($device->getTemplate() !== null) {
                        $newDevice->setTemplate($device->getTemplate());
                    }

                    $this->entityManager->persist($newDevice);

                    //$this->logger->debug('Export process. '.$new_name." Coordinates X,Y:".$device->getEditorData()->getX().",".$device->getEditorData()->getY());
                    $lab->addDevice($newDevice);
                    $this->entityManager->persist($lab);
                    $this->entityManager->flush();                    
                }
            }
        }

        //$this->logger->debug('Sending lab instance '.$uuid.' export message.', json_decode($labJson, true));
        /*$this->logger->debug('Sending lab instance '.$uuid.' export message.');
            $this->bus->dispatch(
                new InstanceActionMessage($labJson, $uuid, InstanceActionMessage::ACTION_EXPORT_LAB), [
                    new AmqpStamp($worker, AMQP_NOPARAM, []),
                ]
            );*/
        
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
        $this->entityManager->persist($newOS);
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
        $newLab->setVirtuality($lab->getVirtuality());
        if ($lab->getHasTimer()) {
            $newLab->setHasTimer($lab->getHasTimer());
            $newLab->setTimer($lab->getTimer());
        }
        

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
        $this->entityManager->persist($newLab);
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
        $newDevice->setVirtuality($device->getVirtuality());

        //$newCoordinate=new EditorData();
        //$newDevice->setEditorData($newCoordinate);
        //$this->entityManager->persist($newCoordinate);

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
        $this->entityManager->persist($newDevice);
        return $newDevice;
    }
}
