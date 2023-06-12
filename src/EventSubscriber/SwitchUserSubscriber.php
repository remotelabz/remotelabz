<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
//use Symfony\Bundle\SecurityBundle\Security;

class SwitchUserSubscriber implements EventSubscriberInterface
{
    private $tokenStorageInterface;
    private $jwtManager;

    public function __construct(TokenStorageInterface $tokenStorageInterface, JWTTokenManagerInterface $jwtManager)
    {
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->jwtManager = $jwtManager;
    }

    public function onSwitchUser(SwitchUserEvent $event): void
    {
        $request = $event->getRequest();
        $user = $this->tokenStorageInterface->getToken()->getUser();

        if ($request->hasSession() && ($session = $request->getSession())) {
                setcookie(
                    'bearer',
                    $this->jwtManager->create($event->getTargetUser())
                );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // constant for security.switch_user
            SecurityEvents::SWITCH_USER => 'onSwitchUser',
        ];
    }
}