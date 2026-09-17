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
use Psr\Log\LoggerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $jwtManager;
    private $dispatcher;
    private $loginNotificationService;
    private $requestStack;
    private $logger;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        LoginNotificationService $loginNotificationService,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
        $this->loginNotificationService = $loginNotificationService;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        $jwt = $this->jwtManager->create($user);

        $response = new JWTAuthenticationSuccessResponse($jwt);
        $event = new AuthenticationSuccessEvent(['token' => $jwt], $user, $response);
        $this->dispatcher->dispatch($event, Events::AUTHENTICATION_SUCCESS);
        $response->setData($event->getData());

        $this->logger->debug('[AuthenticationSuccessHandler:onAuthenticationSuccess]::Login of user: ' . $user->getUserIdentifier(), [
            'remote_addr' => $request->server->get('REMOTE_ADDR', 'unknown'),
            'x_forwarded_for' => $request->server->get('HTTP_X_FORWARDED_FOR', 'unknown'),
            'x_real_ip' => $request->server->get('HTTP_X_REAL_IP', 'unknown'),
            'client_real_ip' => $request->server->get('HTTP_CLIENT_REAL_IP', 'unknown'),
            'cf_connecting_ip' => $request->server->get('HTTP_CF_CONNECTING_IP', 'unknown'),
            'resolved_client_ip' => $request->getClientIp(),
            'user_agent' => $request->server->get('HTTP_USER_AGENT', 'unknown'),
        ]);

        if ($this->loginNotificationService) {
            $ip = $request->getClientIp() ?: $request->server->get('REMOTE_ADDR', 'unknown');
            $userAgent = $request->server->get('HTTP_USER_AGENT', 'unknown');
            $this->logger->debug('[AuthenticationSuccessHandler:onAuthenticationSuccess]::IP stored in login notification: ' . $ip, [
                'x_forwarded_for' => $request->server->get('HTTP_X_FORWARDED_FOR', 'unknown'),
            ]);
            $this->loginNotificationService->logLogin($user, $user->getUserIdentifier(), $ip, $userAgent, 'api');
            $this->loginNotificationService->sendNotificationEmail($user, $ip, $userAgent, 'api', new \DateTime());
        }

        return $response;
    }
}
