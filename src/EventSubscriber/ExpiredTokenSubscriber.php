<?php

namespace App\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExpiredTokenSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        // return the subscribed events, their methods and priorities
        return [
            Events::JWT_EXPIRED => [
                ['refreshToken', 10],
            ]
        ];
    }

    public function refreshToken(JWTExpiredEvent $event)
    {
    }
}