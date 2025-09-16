<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\InstanceStateMessage;
use Remotelabz\Message\Message\InstanceActionMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Repository\DeviceRepository;
use App\Repository\OperatingSystemRepository;
use App\Service\Instance\InstanceManager;
use App\Controller\OperatingSystemController;
//To redirect to a route
//use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class InstanceStateMessageHandler
{
    private DeviceInstanceRepository $deviceInstanceRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private LabInstanceRepository $labInstanceRepository;
    private OperatingSystemRepository $operatingSystemRepository;
    private InstanceManager $instanceManager;
    private DeviceRepository $deviceRepository;
    private string $rootDirectory;
    //private $router;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        LabInstanceRepository $labInstanceRepository,
        OperatingSystemRepository $operatingSystemRepository,
        EntityManagerInterface $entityManager,
        InstanceManager $instanceManager,
        LoggerInterface $logger,
        DeviceRepository $deviceRepository,
        string $rootDirectory
    ) {
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->operatingSystemRepository=$operatingSystemRepository;
        $this->instanceManager = $instanceManager;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->rootDirectory = $rootDirectory;
        $this->deviceRepository = $deviceRepository;
    }

    public function __invoke(InstanceStateMessage $message)
    {
        $uuid=null;
    try {
      
        $this->logger->info("Received InstanceState message :", [
            'uuid' => $message->getUuid(),
            'type' => $message->getType(),
            'state_message' => $message->getState()
            ]);

        // Problem with instance because when it's an error during exporting, the uuid is a compose value and not only the uuid of the instance.
        // So if it's an error, in all case, we have to return, from the worker
        // the uuid in the first place of the first string.
        if (!is_null($message)) {
            $this->logger->debug("[InstanceStateMessageHandler:__invoke]::Message is not null; uuid:".$message->getUuid());
            $uuid=$message->getUuid();
        }
        else 
            $this->logger->debug("[InstanceStateMessageHandler:__invoke]::Message is null:");

        // Becarefull : in case of ACTION_COPY2WORKER_DEV, the instance has no uuid
        if ($message->getType() === InstanceStateMessage::TYPE_LAB)
            $instance = $this->labInstanceRepository->findOneBy(['uuid' => $uuid]);
        else if ($message->getType() === InstanceStateMessage::TYPE_DEVICE)
            $instance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid]);


        // if an error happened, set device instance in its previous state
        if ($message->getState() === InstanceStateMessage::STATE_ERROR) {
            $this->logger->debug("[InstanceStateMessageHandler:__invoke]::Error state received from instance uuid ". $message->getUuid());
            
            $options=$message->getOptions();
            if (!is_null($options)) {
                $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Show options of error message received : ', $options);
                if ( $options["state"] === InstanceActionMessage::ACTION_RENAMEOS ) {
                    $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Cancel renameOS');
                    $this->cancel_renameos($message->getUuid(),$options["old_name"],$options["new_name"]);
                }
                if ( $options["state"] === InstanceActionMessage::ACTION_EXPORT_DEV ) {
                    $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Cancel exported');
                    
                    $new_device_exported = $this->deviceRepository->findOneBy(['id' => $options["newDevice_id"]]);
                    try {
                        $labs = $new_device_exported->getLabs();
                        if (count($labs) > 0){
                            $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Device exists in labs '.$new_device_exported->getName());
                            for ($i = 0; $i < count($labs); $i++) {
                                $lab = $labs[$i];
                                $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Cancel exported from lab: '.$lab->getName());
                                $devices= $lab->getDevices();
                                $this->logger->debug('[InstanceStateMessageHandler:__invoke]::'.count($devices) .' devices found in the lab');
                                for ($j = 0; $j < count($devices); $j++) {
                                    $device = $devices[$j];
                                    $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Cancel exported device in labs; Delete device: '.$device->getName());
                                    $os=$device->getOperatingSystem();
                                    $this->entityManager->remove($device);
                                    if ( $os->getName() != "Natif" && $os->getName() != "Service" ) {
                                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Cancel exported OS in labs; Delete OS: '.$os->getImageFilename());
                                        $this->entityManager->remove($os);
                                    }
                                }
                                $this->entityManager->remove($lab);
                            }
                        }
                        else {
                            $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Device doesn\'t exist in labs');
                            $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Cancel exported device; Delete device: '.$device->getName());
                            $this->entityManager->remove($new_device_exported);
                            $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Cancel exported device; Delete OS: '.$os->getImageFilename());
                            $this->entityManager->remove($new_os_exported);
                        }
                        $this->entityManager->flush();
                    } catch (\Exception $e) {
                        $this->logger->error('[InstanceStateMessageHandler:__invoke]::Error while removing devices and OS during export cancel: '.$e->getMessage());
                    }
                }
                if ( $options["state"] === InstanceActionMessage::ACTION_COPY2WORKER_DEV ) {
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Error when try to copy '.$message->getUuid().' image to worker '.$options["worker_dest_ip"]);
                }

            }
            if (!is_null($instance)) {
                //$this->logger->debug("[InstanceStateMessageHandler:__invoke]::Instance not null and Error received from : ". $message->getUuid() ." message with state ".$message->getState()." and instance state :".$instance->getState());
                switch ($instance->getState()) {
                    case InstanceStateMessage::STATE_STARTING:
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Instance in error after '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_ERROR);
                        break;

                    case InstanceStateMessage::STATE_STOPPING:
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Instance in error after  '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_STARTED);
                        break;
                    
                    case InstanceStateMessage::STATE_RESETTING:
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Instance in error after  '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_STOPPED);
                        break;

                    case InstanceStateMessage::STATE_CREATING:
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Instance in error after  '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_DELETED);
                        break;

                    case InstanceStateMessage::STATE_CREATED:
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Instance in error after  '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_DELETED);
                        break;

                    case InstanceStateMessage::STATE_DELETING:
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Instance in error after  '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_CREATED);
                        break;

                    case InstanceStateMessage::STATE_EXPORTING:
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Instance in error after  '.$instance->getState());
                        if ($message->getOptions()['error_code'] === 1) //Device never started
                            $instance->setState(InstanceStateMessage::STATE_ERROR);
                        else
                            $instance->setState(InstanceStateMessage::STATE_DELETED);
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Error received during exporting, message options :',$message->getOptions());
                        break;

                    default:
                        $this->logger->debug('[InstanceStateMessageHandler:__invoke]::Default case; Instance in '.$instance->getState());
                        $instance->setState($message->getState());
                }
                $this->entityManager->persist($instance);
            } else {
                $this->logger->debug("[InstanceStateMessageHandler:__invoke]::Instance null and Error received from : ". $message->getUuid() ." ".$message->getState());
            }
            $this->logger->debug("[InstanceStateMessageHandler:__invoke]::Test message : ". $message->getUuid() ." ".$message->getState());
        }
        else {
            $this->logger->debug("[InstanceStateMessageHandler:__invoke]::No error received from :". $message->getUuid() .", instance message state:".$message->getState());
            if (!is_null($instance)) {
                //$this->logger->debug("[InstanceStateMessageHandler:__invoke]::InstanceStateMessageHandler Instance is not null");
                $instance->setState($message->getState());
                $this->entityManager->persist($instance);
                $this->entityManager->flush();

            }
            /*  else 
                  $this->logger->debug("[InstanceStateMessageHandler:__invoke]::InstanceStateMessageHandler Instance is null");
            */
            switch ($message->getState()) {
                case InstanceStateMessage::STATE_STOPPED:
                    $this->instanceManager->setStopped($instance);
                break;
                case InstanceStateMessage::STATE_EXPORTED:
                    $this->logger->debug("[InstanceStateMessageHandler:__invoke]::Instance state exported received");
                    
                    $this->instanceManager->delete($instance->getLabInstance());
                    $options_exported=$message->getOptions();

                    $this->instanceManager->Sync2OS($options_exported['workerIP'],$options_exported['hypervisor'],$options_exported['new_os_imagename']);

                break;
                case InstanceStateMessage::STATE_OS_DELETED:
                    $options_exported=$message->getOptions();
                    $this->logger->info($options_exported["hypervisor"]." image ".$options_exported["os_imagename"]." is deleted from worker ".$options_exported["workerIP"]);                  
                break;
                case InstanceStateMessage::STATE_DELETED:
                                
                    if ($message->getType() === InstanceStateMessage::TYPE_LAB) {
                        $this->logger->debug("[InstanceStateMessageHandler:__invoke]::\"Deleted\" Instance state message is type Lab");
                        $lab=$instance->getLab();
                        
                        $this->entityManager->remove($instance);
                        $this->entityManager->flush();

                        if (strstr($lab->getName(),"Sandbox_")) {
                            $this->logger->debug("[InstanceStateMessageHandler:__invoke]::\"Deleted\" Instance state message from Sandbox: ".$lab->getName());

                            foreach ($lab->getDevices() as $device) {
                                
                                // cascade so, no need to remove network interfaces
                                /*foreach($device->getNetworkInterfaces() as $net_int) {
                                    $this->entityManager->remove($net_int);
                                }*/

                                $this->logger->debug("[InstanceStateMessageHandler:__invoke]::Delete device name: ".$device->getName());
                                $this->entityManager->remove($device);
                                //$this->entityManager->persist($device);
                            }
                            if (null !== $lab->getPictures()) {
                            
                                foreach($lab->getPictures() as $picture) {
                                    $type = explode("image/",$picture->getType())[1];
                                    if(is_file($this->rootDirectory.'/assets/js/components/Editor2/images/pictures/lab'.$lab->getId().'-'.$picture->getName().'.'.$type)) {
                                        unlink($this->rootDirectory.'/assets/js/components/Editor2/images/pictures/lab'.$lab->getId().'-'.$picture->getName().'.'.$type);
                                    }
                                }
                            }
                            $this->entityManager->remove($lab);
                            
                        }
                    }
                    break;
            }
        }
        
        $this->entityManager->flush();
        }
        catch (\Exception $e) {
            $this->logger->error("[InstanceStateMessageHandler:__invoke]::Error while processing message: " . $e->getMessage());
            if (!is_null($lab))
                $this->logger->error("[InstanceStateMessageHandler:__invoke]::Lab name:" . $lab->getName()." uuid:".$lab->getUuid());
            if (!is_null($device))
                $this->logger->error("[InstanceStateMessageHandler:__invoke]::Device name:" . $device->getName()." uuid:".$device->getUuid());
        }
    }

    public function cancel_renameos($id,$old_name,$new_name){
        $this->logger->debug("Cancel rename OS id ".$id. "from name ".$old_name." to name ".$new_name);
        $operatingsystem = $this->operatingSystemRepository->find($id);
        $operatingsystem->setImageFilename($old_name);
        $this->entityManager->persist($operatingsystem);
        $this->entityManager->flush();
    }
}
