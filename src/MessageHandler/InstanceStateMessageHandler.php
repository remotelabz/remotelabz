<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\InstanceStateMessage;
use Remotelabz\Message\Message\InstanceActionMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Repository\OperatingSystemRepository;
use App\Service\Instance\InstanceManager;
use App\Controller\OperatingSystemController;
//use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
    private string $rootDirectory;
    //private $router;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        LabInstanceRepository $labInstanceRepository,
        OperatingSystemRepository $operatingSystemRepository,
        EntityManagerInterface $entityManager,
        InstanceManager $instanceManager,
        LoggerInterface $logger,
        string $rootDirectory
    ) {
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->operatingSystemRepository=$operatingSystemRepository;
        $this->instanceManager = $instanceManager;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->rootDirectory = $rootDirectory;
    }

    public function __invoke(InstanceStateMessage $message)
    {
        $this->logger->info("Received InstanceState message :", [
            'uuid' => $message->getUuid(),
            'type' => $message->getType(),
            'state_message' => $message->getState()
            ]);

        // Problem with instance because when it's an error during exporting, the uuid is a compose value and not only the uuid of the instance.
        // So if it's an error, in all case, we have to return, from the worker
        // the uuid in the first place of the first string.
        
        $uuid=$message->getUuid();
        
        if ($message->getType() === InstanceStateMessage::TYPE_LAB)
            $instance = $this->labInstanceRepository->findOneBy(['uuid' => $uuid]);
        else if ($message->getType() === InstanceStateMessage::TYPE_DEVICE)
            $instance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid]);

        // if an error happened, set device instance in its previous state
        if ($message->getState() === InstanceStateMessage::STATE_ERROR) {
            $this->logger->debug("Error state received from instance uuid ". $message->getUuid());
            $options=$message->getOptions();
            if (!is_null($options)) {
                $this->logger->debug('Show options of error message received : ', $options);
                if ( $options["state"] === InstanceActionMessage::ACTION_RENAMEOS ) {
                    $this->cancel_renameos($message->getUuid(),$options["old_name"],$options["new_name"]);
                }
            }
            if (!is_null($instance)) {
                $this->logger->debug("Instance not null and Error received from : ". $message->getUuid() ." message with state ".$message->getState()." and instance state :".$instance->getState());
                switch ($instance->getState()) {
                    case InstanceStateMessage::STATE_STARTING:
                        $this->logger->debug('Instance in '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_ERROR);
                        break;

                    case InstanceStateMessage::STATE_STOPPING:
                        $this->logger->debug('Instance in '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_STARTED);
                        break;
                    
                    case InstanceStateMessage::STATE_RESETTING:
                        $this->logger->debug('Instance in '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_STOPPED);
                        break;

                    case InstanceStateMessage::STATE_CREATING:
                        $this->logger->debug('Instance in '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_DELETED);
                        break;

                    case InstanceStateMessage::STATE_CREATED:
                        $this->logger->debug('Instance in '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_DELETED);
                        break;

                    case InstanceStateMessage::STATE_DELETING:
                        $this->logger->debug('Instance in '.$instance->getState());
                        $instance->setState(InstanceStateMessage::STATE_CREATED);
                        break;

                    case InstanceStateMessage::STATE_EXPORTING:
                        $this->logger->debug('Instance in '.$instance->getState());
                        if ($message->getOptions()['error_code'] === 1) //Device never started
                            $instance->setState(InstanceStateMessage::STATE_ERROR);
                        else
                            $instance->setState(InstanceStateMessage::STATE_DELETED);
                        
                        $this->logger->debug('Error received during exporting, message options :',$message->getOptions());

                       
                        break;

                    default:
                        $this->logger->debug('Instance in '.$instance->getState());
                        $instance->setState($message->getState());
                }
            } else {
                $this->logger->debug("Instance null and Error received from : ". $message->getUuid() ." ".$message->getState());
            }
            $this->logger->debug("Test message : ". $message->getUuid() ." ".$message->getState());

        } else {
            $this->logger->debug("No error received from : ". $message->getUuid() ." ".$message->getState());
            if (!is_null($instance)) {//DeleteOS used instanceState message but with no instance. So $instance is null
                $this->logger->debug("InstanceStateMessageHandler Instance is not null");
                $instance->setState($message->getState());
                $this->entityManager->persist($instance);
            }
            else 
                $this->logger->debug("InstanceStateMessageHandler Instance is null");

            switch ($message->getState()) {
                case InstanceStateMessage::STATE_STOPPED:
                    $this->instanceManager->setStopped($instance);
                break;
                case InstanceStateMessage::STATE_EXPORTED:
                    $this->logger->debug("Instance state exported received");
                    //In export process, the instance is a device
//                    $device=$instance->getDevice();
//                    $lab=$instance->getLab();
                    $this->instanceManager->delete($instance->getLabInstance());
                    $options_exported=$message->getOptions();

                    $this->instanceManager->Sync2OS($options_exported['workerIP'],$options_exported['hypervisor'],$options_exported['new_os_imagename']);

                    //TODO redirect to route labs
                    //$this->redirectToRoute('labs');
                    
                break;
                case InstanceStateMessage::STATE_OS_DELETED:
                    $options_exported=$message->getOptions();
                    $this->logger->info($options_exported["hypervisor"]." image ".$options_exported["os_imagename"]." is deleted from worker ".$options_exported["workerIP"]);                  
                break;

                
            }
        }
        if (!is_null($instance)) {

            if ($instance->getState() === InstanceStateMessage::STATE_DELETED) {
                $this->logger->debug("Instance state deleted received");
                //When the instance is from a sandbox, we can delete the lab and its devices.
                
                $lab=$instance->getLab();
                $this->entityManager->remove($instance);
                $this->entityManager->flush();

                if (strstr($instance->getLab()->getName(),"Sandbox_")) {
                    foreach ($lab->getDevices() as $device) {
                        foreach($device->getNetworkInterfaces() as $net_int) {
                            $this->entityManager->remove($net_int);
                        }
                        $this->entityManager->flush();

                        $this->logger->debug("Delete device name: ".$device->getName());
                        $this->entityManager->remove($device);
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
                
            } else {
                $this->logger->debug("Instance state received: ".$instance->getState());
                $this->entityManager->persist($instance);
                }
        }
        $this->entityManager->flush();
    }

    public function cancel_renameos($id,$old_name,$new_name){
        $this->logger->debug("Cancel rename OS id ".$id. "from name ".$old_name." to name ".$new_name);
        $operatingsystem = $this->operatingSystemRepository->find($id);
        $operatingsystem->setImageFilename($old_name);
        $this->entityManager->persist($operatingsystem);
        $this->entityManager->flush();
    }
}
