<?php

namespace App\EventListener;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\Device;
use App\Repository\DeviceRepository;
use App\Repository\InstanceRepository;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface as JMSEventSubscriberInterface;

class InstanceSerializationListener implements JMSEventSubscriberInterface
{
    private $tokenStorage;

    private $user;

    private $instanceRepository;

    private $deviceRepository;

    public function __construct(TokenStorageInterface $tokenStorage, InstanceRepository $instanceRepository, DeviceRepository $deviceRepository) {
        $this->tokenStorage = $tokenStorage;
        $this->instanceRepository = $instanceRepository;
        $this->deviceRepository = $deviceRepository;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    /**
     * @inheritdoc
     */
    static public function getSubscribedEvents()
    {
        return [
            [
                'event' => 'serializer.pre_serialize',
                'class' => Lab::class,
                'method' => 'onPreSerialize'
            ],
            [
                'event' => 'serializer.pre_serialize',
                'class' => Device::class,
                'method' => 'onDevicePreSerialize'
            ]
        ];
    }

    public function onPreSerialize(PreSerializeEvent $event)
    {
        /** @var Lab $lab */
        $lab = $event->getObject();
        if ($this->user instanceof User) {
            $labInstances = $this->instanceRepository->findBy(['user' => $this->user, 'lab' => $lab]);
            $lab->setInstances($labInstances);
        }
    }

    public function onDevicePreSerialize(PreSerializeEvent $event)
    {
        /** @var Lab $device */
        $device = $event->getObject();
        if ($this->user instanceof User) {
            $deviceInstances = $this->instanceRepository->findBy(['user' => $this->user, 'device' => $device]);
            $device->setInstances($deviceInstances);
        }
    }
}