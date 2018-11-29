<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RenewTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(TokenStorageInterface $tokenStorage) {
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        // If targeted controller is a Web controller, user is logged in from web app
        if ($controller[0] instanceof \App\Controller\WebController) {
            // Try to get the token cookie
            if (!($event->getRequest()->cookies->get('bearer'))) {
                throw new UnauthorizedHttpException("Bearer");
            }
        }
    }

    public function onKernelException(GetResponseForExceptionEvent $event) {
        $exception = $event->getException();

        // When an Unauthorized exception is thrown, user's token may be expired
        // Ask user to sign in again to get a new token
        // We could use refresh token, but it means refresh token would be stored for a long time, which can be a security issue
        if ($exception instanceof UnauthorizedHttpException) {
            $event->setResponse(new RedirectResponse('/logout'));
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
