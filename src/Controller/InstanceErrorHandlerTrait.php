<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

trait InstanceErrorHandlerTrait
{
    protected function addFlashMsgError(SessionInterface $session, string $message): void
    {
        $session->getFlashBag()->add('danger', $message);
    }

    protected function addFlashMsgSuccess(SessionInterface $session, string $message): void
    {
        $session->getFlashBag()->add('success', $message);
    }
}