<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\Service\LoginNotificationService;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $jwtManager;
    private $dispatcher;
    private $loginNotificationService;
    private $requestStack;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        LoginNotificationService $loginNotificationService,
        RequestStack $requestStack
    ) {
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
        $this->loginNotificationService = $loginNotificationService;
        $this->requestStack = $requestStack;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        $jwt = $this->jwtManager->create($user);

        $response = new JWTAuthenticationSuccessResponse($jwt);
        $event = new AuthenticationSuccessEvent(['token' => $jwt], $user, $response);
        $this->dispatcher->dispatch($event, Events::AUTHENTICATION_SUCCESS);
        $response->setData($event->getData());

        if ($this->loginNotificationService) {
            $ip = $request->server->get('REMOTE_ADDR', 'unknown');
            $userAgent = $request->server->get('HTTP_USER_AGENT', 'unknown');
            $this->loginNotificationService->logLogin($user, $user->getUserIdentifier(), $ip, $userAgent, 'api');
            $this->loginNotificationService->sendNotificationEmail($user, $ip, $userAgent, 'api', new \DateTime());
        }

        return $response;
    }
}
