<?php

namespace App\Exception;

use App\Entity\Instance;

class NotInstancedException extends InstanceException implements InstanceExceptionInterface
{
    public function __construct(?\Throwable $previous = null, ?int $code = 0)
    {
        $message = "Object was not instanced.";

        parent::__construct(null, $message, $previous, $code);
    }
}