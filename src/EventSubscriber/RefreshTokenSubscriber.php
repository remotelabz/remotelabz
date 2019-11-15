<?php

namespace App\EventSubscriber;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\KernelEvents;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class RefreshTokenSubscriber implements EventSubscriberInterface
{
    // /**
    //  * @param RefreshTokenManagerInterface $refreshTokenManager
    //  */
    // private $refreshTokenManager;

    // /**
    //  * @param JWTTokenManagerInterface $JWTManager
    //  */
    // private $JWTManager;

    // /** @param string $updatedJWTToken */
    // private $updatedJWTToken;

    // private $userRepository;
    
    // public function __construct(JWTTokenManagerInterface $JWTManager, RefreshTokenManagerInterface $refreshTokenManager, UserRepository $userRepository)
    // {
    //     $this->refreshTokenManager = $refreshTokenManager;
    //     $this->JWTManager = $JWTManager;
    //     $this->updatedJWTToken = null;
    //     $this->userRepository = $userRepository;
    // }

    public static function getSubscribedEvents()
    {
        return [
            // KernelEvents::REQUEST => ['onKernelRequest', 100],
            // KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    // public function onKernelRequest(RequestEvent $event)
    // {
    //     if ($event->isMasterRequest()) {
    //         $refreshTokenCookie = $event->getRequest()->cookies->get('rt');
    //         $refreshToken = $this->refreshTokenManager->get($refreshTokenCookie);
    //         if ($refreshToken && $refreshToken->isValid()) {
    //             $userEmail = $refreshToken->getUsername();
    //             $user = $this->userRepository->findOneBy(['email' => $userEmail]);
    //             $this->updatedJWTToken = $this->JWTManager->create($user);
    //             $event->getRequest()->cookies->set('bearer', $this->updatedJWTToken);
    //         }
    //     }
    // }

    // public function onKernelResponse(ResponseEvent $event)
    // {
    //     if ($this->updatedJWTToken) {
    //         $event->getResponse()->headers->setCookie(Cookie::create('bearer', $this->updatedJWTToken));
    //     }
    // }

}