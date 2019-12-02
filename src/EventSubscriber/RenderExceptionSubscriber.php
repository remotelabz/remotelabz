<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use App\Exception\WorkerException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RenderExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($exception instanceof WorkerException) {
            $this->logger->error($exception->getMessage(), [
                'uuid' => $exception->getInstance()->getUuid(),
                'response' => json_decode($exception->getResponse()->getBody()->getContents(), true)
            ]);
        } else {
            $this->logger->error($exception->getMessage());
        }

        // test if we want a json return
        if ($request->isXmlHttpRequest()) {
            $status = $response === null ? 400 : $response->getStatusCode();

            $response = new JsonResponse();

            if ($exception instanceof NotFoundHttpException) {
                $status = 404;
            }
            if ($exception instanceof MethodNotAllowedHttpException) {
                $status = 405;
            }

            $response
                ->setStatusCode($status)
                ->setData([
                    'code' => $status,
                    'message' => $exception->getMessage(),
                ])
            ;

            $event->setResponse($response);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
