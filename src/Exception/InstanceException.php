<?php

namespace App\Exception;

use App\Entity\Instance;

class InstanceException extends \RuntimeException implements InstanceExceptionInterface
{
    private $instance;

    public function __construct(?Instance $instance = null, string $message = null, ?\Throwable $previous = null, ?int $code = 0)
    {
        $this->instance = $instance;

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
}