<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use App\Exception\WorkerException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class RenderExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
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
        if ('json' === $request->getRequestFormat() || $request->isXmlHttpRequest()) {
            $status = $response === null ? 400 : $response->getStatusCode();

            $response = new JsonResponse(null, 500);

            if ($exception instanceof HttpException) {
                $response->setStatusCode($exception->getStatusCode());
            }

            if (!empty($exception->getMessage())) {
                $response->setData(['message' => $exception->getMessage()]);
            } else {
                $response->setContent(null)
                    ->headers->set('Content-Type', 'text/plain');
            }

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
