<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\InstanceStateMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Service\Instance\InstanceManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class InstanceStateMessageHandler implements MessageHandlerInterface
{
    private $deviceInstanceRepository;
    private $labInstanceRepository;
    private $instanceManager;
    private $entityManager;
    private $logger;

    public function __construct(
        DeviceInstanceRepository $deviceInstanceRepository,
        LabInstanceRepository $labInstanceRepository,
        EntityManagerInterface $entityManager,
        InstanceManager $instanceManager,
        LoggerInterface $logger
    ) {
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->instanceManager = $instanceManager;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function __invoke(InstanceStateMessage $message)
    {
        $this->logger->info("Received InstanceState message :", [
            'uuid' => $message->getUuid(),
            'type' => $message->getType(),
            'state' => $message->getState()
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
            if (!is_null($message->getOptions()))
                $this->logger->debug('Show options of message received : ', $message->getOptions());

            
            switch ($instance->getState()) {
                case InstanceStateMessage::STATE_STARTING:
                    $this->logger->debug('Instance in '.$instance->getState());
                    $instance->setState(InstanceStateMessage::STATE_ERROR);
                    break;

                case InstanceStateMessage::STATE_STOPPING:
                    $this->logger->debug('Instance in '.$instance->getState());
                    $instance->setState(InstanceStateMessage::STATE_STARTED);
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
                    $instance->setState(InstanceStateMessage::STATE_ERROR);
                    $this->logger->debug('Error received during exporting, message options :',$message->getOptions());

                    /* Remove newdevice template and OS created
                    As the worker doesn't send message with some information like name chosen by the user for the new device template created,
                    if we have an error, we have to delete creation done as soon as we click on Export button.
                    The solution to execute the new template creation only if the worker doesn't report an error, need to pass the name chosen
                    by the user. But this action is driven by message state receive and the worker doesn't send information in their message. It's only 
                    state message.
                    The format of $message->getUuid(), in this case is :
                        InstanceStateMessage::STATE_ERROR,
                        $deviceInstance['uuid'],
                        $labInstance["newOS_id"],
                        $labInstance["newDevice_id"],
                        $labInstance["new_os_name"],
                        $labInstance["new_os_imagename"]

                    */
                    //Uuid of the device created but to delete because an error occurs
                    //$message->getUuid();
                    // Test using options
                    // For transition, all uuid are copy in options
                    $this->instanceManager->deleteDev_fromexport($message->getUuid(),$message->getOptions());
                    break;

                default:
                    $this->logger->debug('Instance in '.$instance->getState());
                    $instance->setState($message->getState());
            }
        } else {
            $this->logger->debug("No error received from : ". $message->getUuid() ." ".$message->getState());
            if (!is_null($instance)) {//DeleteOS used instanceState message but with no instance. So $instance is null
                $this->logger->debug("InstanceStateMessageHandler Instance is not null");
                $instance->setState($message->getState());}
            else 
                $this->logger->debug("InstanceStateMessageHandler Instance is null");

            switch ($message->getState()) {
                case InstanceStateMessage::STATE_STOPPED:
                    $this->instanceManager->setStopped($instance);
                break;
                case InstanceStateMessage::STATE_EXPORTED:
                    $this->logger->debug("Instance state exported received");
                    //In export process, the instance is a device
                    $device=$instance->getDevice();
                    $lab=$instance->getLab();
                    $this->instanceManager->delete($instance->getLabInstance());
                    //Wait the message DELETED is received and so, the instance is deleted
//                    \App\Controller\Labcontroller::getdelete_lab($lab);
                    //TODO : define labController
                    //$this->labcontroler->delete_lab($lab);
                    //return $this->redirectToRoute('devices_sandbox');
                break;
            }
        }

        if (!is_null($instance) && $instance->getState() === InstanceStateMessage::STATE_DELETED) {
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
                $this->entityManager->remove($lab);
            }
            
        } else {
            $this->logger->debug("Instance state received:".$instance->getState());

            if (!is_null($instance))
                $this->entityManager->persist($instance);
        }

        $this->entityManager->flush();
    }
}
