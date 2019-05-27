<?php

namespace App\Exception;

interface InstanceExceptionInterface
{
    /**
     * Returns the instance.
     *
     * @return App\Entity\Instance Instance object
     */
    public function getInstance();
}