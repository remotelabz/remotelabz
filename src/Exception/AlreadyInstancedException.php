<?php

namespace App\Exception;

use App\Entity\Instance;

class AlreadyInstancedException extends InstanceException implements InstanceExceptionInterface
{
    public function __construct(Instance $instance, ?\Throwable $previous = null, ?int $code = 0)
    {
        $message = "Object is already instanced (UUID: " . $instance->getUuid() . ".";

        parent::__construct($instance, $message, $previous, $code);
    }
}