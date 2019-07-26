<?php

namespace App\EventListener;

use App\Entity\LabInstance;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Repository\NetworkInterfaceInstanceRepository;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;

class InstanceDeleteListener
{
    private $labInstanceRepository;

    private $deviceInstanceRepository;

    private $networkInterfaceInstanceRepository;

    public function __construct(
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        NetworkInterfaceInstanceRepository $networkInterfaceInstanceRepository
        ) {
        $this->labInstanceRepository = $labInstanceRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->networkInterfaceInstanceRepository = $networkInterfaceInstanceRepository;
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        /** @var LabInstance $entity */
        $entity = $args->getObject();

        if (!$entity instanceof LabInstance) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $deviceInstances = $entity->getDeviceInstances();

        foreach ($deviceInstances as $deviceInstance) {
            $entityManager->remove($deviceInstance);
        }

        $networkInterfaceInstances = $entity->getNetworkInterfaceInstances();

        foreach ($networkInterfaceInstances as $networkInterfaceInstance) {
            $entityManager->remove($networkInterfaceInstance);
        }

        $entityManager->flush();
    }
}