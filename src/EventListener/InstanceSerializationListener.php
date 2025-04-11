<?php

namespace App\EventListener;

use App\Entity\User;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface as JMSEventSubscriberInterface;

class InstanceSerializationListener implements JMSEventSubscriberInterface
{
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
            ],
            // [
            //     'event' => 'serializer.pre_serialize',
            //     'class' => User::class,
            //     'method' => 'onUserPreSerialize'
            // ]
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
            /*  $this->router->getContext()->getScheme() .
                '://' .
                $this->router->getContext()->getHost() .
                '/uploads/images/' .*/
                $operatingSystem->getImageFilename()
            );
        } else {
            $operatingSystem->setImage("");
        }
    }

    // public function onUserPreSerialize(PreSerializeEvent $event)
    // {
    //     /** @var User $user */
    //     $user = $event->getObject();
    //     $user->getCreatedLabs()->map(function ($lab) use ($user) {
    //         $user->removeCreatedLab($lab);
    //         $user->add
    //     })
    //     $event->getVisitor()->prepare($user);
    // }
}