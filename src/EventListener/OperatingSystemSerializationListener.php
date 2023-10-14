<?php

namespace App\EventListener;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\Device;
use App\Entity\OperatingSystem;
use App\Entity\NetworkInterface;
use App\Service\ImageFileUploader;
use App\Repository\DeviceRepository;
use Symfony\Component\Asset\Packages;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use App\Repository\NetworkInterfaceInstanceRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface as JMSEventSubscriberInterface;

class OperatingSystemSerializationListener implements JMSEventSubscriberInterface
{
    # TODO: This class is useless because it should be merged in one listener file
    /** @var UrlGeneratorInterface */
    private $router;

    public function __construct(
        UrlGeneratorInterface $router
        ) {
        $this->router = $router;
    }

    /**
     * @inheritdoc
     */
    static public function getSubscribedEvents(): array
    {
        return [
            [
                'event' => 'serializer.pre_serialize',
                'class' => OperatingSystem::class,
                'method' => 'onOperatingSystemPreSerialize'
            ]
        ];
    }

    public function onOperatingSystemPreSerialize(PreSerializeEvent $event)
    {
        /** @var OperatingSystem $operatingSystem */
        $operatingSystem = $event->getObject();
        if ($operatingSystem->getImageUrl()) {
            $operatingSystem->setImage($operatingSystem->getImageUrl());
        } elseif ($operatingSystem->getImageFilename()) {
            $operatingSystem->setImage(
                /*$this->router->getContext()->getScheme() .
                '://' .
                $this->router->getContext()->getHost() .
                '/uploads/images/' .*/
                $operatingSystem->getImageFilename()
            );
        } else {
            $operatingSystem->setImage("");
        }
    }
}