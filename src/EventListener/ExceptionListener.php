<?php
// src/EventListener/ExceptionListener.php
namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    
    /**
     * @var string
     */
    private $environment;

    public function __construct(string $environment)
    {
        $this->environment = $environment;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        // You get the exception object from the received event
        $exception = $event->getThrowable();

        $message = sprintf(
            'My Error says: %s with code: %s',
            $exception->getMessage(),
            $exception->getCode()
        );

        //$message = 'Error';
        // Customize your response object to display the exception details
        $response = new Response();
        /*if ($this->environment ==="dev")
            $response->setContent($exception->getMessage(),$exception);
        else
            $response->setContent($message);*/

        // HttpExceptionInterface is a special type of exception that
        // holds status code and header details
        if ($exception instanceof HttpExceptionInterface) {
            if ($exception->getStatusCode() == 404) {
                preg_match(
                    '/\/api\/instances\/lab\/[0-9a-fA-F]{8}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{12}\/by-user\/[0-9a-fA-F]{8}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{12}/', 
                    $event->getRequest()->getRequestUri(),
                    $matchesUser
                );
                preg_match(
                    '/\/api\/instances\/lab\/[0-9a-fA-F]{8}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{12}\/by-group\/[0-9a-fA-F]{8}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{12}/', 
                    $event->getRequest()->getRequestUri(),
                    $matchesGroup
                );
                preg_match(
                    '/\/api\/instances\/lab\/[0-9a-fA-F]{8}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{12}\/by-guest\/[0-9a-fA-F]{8}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{4}[-][0-9a-fA-F]{12}/', 
                    $event->getRequest()->getRequestUri(),
                    $matchesGuest
                );
                if ($matchesUser == null && $matchesGroup == null && $matchesGuest == null) {
                    $response = new RedirectResponse('/');
                    // sends the modified response object to the event
                    $event->setResponse($response);
                }
            }
            /*else {
                $response->setStatusCode($exception->getStatusCode());
                $response->headers->replace($exception->getHeaders());
            }*/
        } /*else {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }*/
        // sends the modified response object to the event
        //$event->setResponse($response);        
    }
}