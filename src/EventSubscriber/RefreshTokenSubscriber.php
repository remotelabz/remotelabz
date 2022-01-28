<?php

namespace App\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RefreshTokenSubscriber implements EventSubscriberInterface
{
    private $tokenStorageInterface;
    /** @var Session $sessionInterface */
    private $sessionInterface;

    public function __construct(TokenStorageInterface $tokenStorageInterface, SessionInterface $sessionInterface)
    {
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->sessionInterface = $sessionInterface;
    }

    public static function getSubscribedEvents()
    {
        return [
            // KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    // public function onKernelRequest(RequestEvent $event)
    // {
    //     if ($event->isMasterRequest()) {
    //         $refreshTokenCookie = $event->getRequest()->cookies->get('rt');
    //         $refreshToken = $this->refreshTokenManager->get($refreshTokenCookie);
    //         if ($refreshToken && $refreshToken->isValid()) {
    //             $userEmail = $refreshToken->getUserIdentifier();
    //             $user = $this->userRepository->findOneBy(['email' => $userEmail]);
    //             $this->updatedJWTToken = $this->JWTManager->create($user);
    //             $event->getRequest()->cookies->set('bearer', $this->updatedJWTToken);
    //         }
    //     }
    // }

    public function onKernelResponse(ResponseEvent $event)
    {
        if ($event->getResponse()->getStatusCode() == 401) {
            $this->tokenStorageInterface->setToken(null);
            $this->sessionInterface->getFlashBag()->set('danger', 'You have been disconnected. Please log in again');
            $event->getResponse()->headers->clearCookie('PHPSESSID');
        }
    }

}