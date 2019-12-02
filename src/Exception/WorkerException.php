<?php

namespace App\Exception;

use App\Entity\Instance;
use Psr\Http\Message\ResponseInterface;

class WorkerException extends \RuntimeException implements InstanceExceptionInterface
{
    private $instance;
    private $response;

    public function __construct(string $message = null, Instance $instance, ResponseInterface $response, ?\Throwable $previous = null, ?int $code = 0)
    {
        $this->instance = $instance;
        $this->response = $response;

        parent::__construct($message, $code, $previous);
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function setInstance(Instance $instance)
    {
        $this->instance = $instance;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
    }
}