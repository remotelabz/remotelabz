<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Bundle\SecurityBundle\Security;;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    private $logger;
    private $router;

    public function __construct(
        LoggerInterface $logger,
        Security $security,
        UrlGeneratorInterface $router
    ) {
        $this->logger = $logger;
        $this->security = $security;
        $this->router = $router;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        
        $this->logger->info("Exception AccessDeniedHandler, request :".$request->query->get('GET'));
        
        if ($this->security->getUser()->hasRole('ROLE_GUEST')) {
            return new RedirectResponse($this->router->generate('show_lab_to_guest', ['id'=> $this->security->getUser()->getLab()->getId()]));
        }
        return new RedirectResponse('/');
    }
}