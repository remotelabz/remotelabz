<?php

namespace App\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RefreshTokenSubscriber implements EventSubscriberInterface
{
    private $tokenStorageInterface;
    private $requestStack;

    public function __construct(TokenStorageInterface $tokenStorageInterface, RequestStack $requestStack)
    {
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
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
            $session = $this->requestStack->getCurrentRequest()->getSession();
            $session->getFlashBag()->set('danger', 'You have been disconnected. Please log in again');
            $event->getResponse()->headers->clearCookie('PHPSESSID');
        }
    }

}