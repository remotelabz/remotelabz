<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutEventSubscriber implements EventSubscriberInterface
{
    private $apiKeyCookieName;

    public function __construct(string $apiKeyCookieName, RouterInterface $router)
    {
        $this->router = $router;
        $this->apiKeyCookieName = $apiKeyCookieName;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogoutSuccess',
        ];
    }

    public function onLogoutSuccess(LogoutEvent $event)
    {
        $response = new RedirectResponse('/');
        $response->headers->clearCookie($this->apiKeyCookieName);

        if ($event->getRequest()->server->has('eppn')) {
            $response->setTargetUrl(
                $this->router->generate('shib_logout', [
                    'return' => $this->router->generate('login'),
                ])
            );
        } else {
            $response->setTargetUrl($this->router->generate('login'));
        }

        $event->setResponse($response);
    }
}
