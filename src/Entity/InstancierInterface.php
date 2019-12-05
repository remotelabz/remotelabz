<?php

namespace App\Entity;

use App\Entity\User;
use App\Entity\Instance;

/**
 * Represents an entity who is able to own and control an instance.
 */
interface InstancierInterface
{
    public function getUuid();

    public function getName();
}