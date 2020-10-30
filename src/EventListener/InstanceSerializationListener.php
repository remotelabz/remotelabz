<?php

namespace App\EventListener;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\Device;
use App\Entity\NetworkInterface;
use App\Repository\DeviceRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use App\Repository\NetworkInterfaceInstanceRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface as JMSEventSubscriberInterface;

class InstanceSerializationListener implements JMSEventSubscriberInterface
{
    private $tokenStorage;

    private $user;

    private $labInstanceRepository;
    
    private $deviceInstanceRepository;

    private $networkInterfaceInstanceRepository;

    private $deviceRepository;

    /** @var UrlGeneratorInterface */
    private $router;
    
    public function __construct(
        TokenStorageInterface $tokenStorage,
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        NetworkInterfaceInstanceRepository $networkInterfaceInstanceRepository,
        DeviceRepository $deviceRepository,
        UrlGeneratorInterface $router
        ) {
        $this->tokenStorage = $tokenStorage;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->networkInterfaceInstanceRepository = $networkInterfaceInstanceRepository;
        $this->deviceRepository = $deviceRepository;
        $token = $tokenStorage->getToken();
        if ($token)
            $this->user = $token->getUser();
        $this->router = $router;
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
                'method' => 'onLabPreSerialize'
            ],
            [
                'event' => 'serializer.pre_serialize',
                'class' => Device::class,
                'method' => 'onDevicePreSerialize'
            ],
            [
                'event' => 'serializer.pre_serialize',
                'class' => NetworkInterface::class,
                'method' => 'onNetworkInterfacePreSerialize'
            ],
            [
                'event' => 'serializer.pre_serialize',
                'class' => OperatingSystem::class,
                'method' => 'onOperatingSystemPreSerialize'
            ]
        ];
    }

    public function onLabPreSerialize(PreSerializeEvent $event)
    {
        /** @var Lab $lab */
        $lab = $event->getObject();
        if ($this->user instanceof User) {
            $labInstances = $this->labInstanceRepository->findBy(['user' => $this->user, 'lab' => $lab]);
            $lab->setInstances($labInstances);
        }
    }

    public function onDevicePreSerialize(PreSerializeEvent $event)
    {
        /** @var Device $device */
        $device = $event->getObject();
        if ($this->user instanceof User) {
            $deviceInstances = $this->deviceInstanceRepository->findBy(['user' => $this->user, 'device' => $device]);
            $device->setInstances($deviceInstances);
        }
    }

    public function onNetworkInterfacePreSerialize(PreSerializeEvent $event)
    {
        /** @var NetworkInterface $networkInterface */
        $networkInterface = $event->getObject();
        if ($this->user instanceof User) {
            $networkInterfaceInstances = $this->networkInterfaceInstanceRepository->findBy(['user' => $this->user, 'networkInterface' => $networkInterface]);
            $networkInterface->setInstances($networkInterfaceInstances);
        }
    }

    public function onOperatingSystemPreSerialize(PreSerializeEvent $event)
    {
        /** @var OperatingSystem $operatingSystem */
        $operatingSystem = $event->getObject();
        if ($operatingSystem->getImageUrl()) {
            $operatingSystem->setImage($operatingSystem->getImageUrl());
        } elseif ($operatingSystem->getImageFilename()) {
            $operatingSystem->setImage(
                $this->router->getContext()->getScheme() .
                '://' .
                $this->router->getContext()->getHost() .
                '/uploads/images/' .
                $operatingSystem->getImageFilename()
            );
        } else {
            $operatingSystem->setImage("");
        }
    }
}