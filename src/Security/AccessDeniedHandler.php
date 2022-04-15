<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Core\Security;
use Psr\Log\LoggerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    private $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        
        $this->logger->info("Exception AccessDeniedHandler, request :".$request->query->get('GET'));
        
        return new RedirectResponse('/');
    }
}