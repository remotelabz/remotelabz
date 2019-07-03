<?php

namespace App\Exception;

use App\Entity\Instance;
use App\Instance\InstanciableInterface;

class NotInstancedException extends InstanceException implements InstanceExceptionInterface
{
    public function __construct(?InstanciableInterface $object, ?\Throwable $previous = null, ?int $code = 0)
    {
        $message = "Object was not instanced. (UUID: " . $object->getUuid() . ".";

        parent::__construct(null, $message, $previous, $code);
    }
}