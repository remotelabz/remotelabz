<?php

namespace App\Instance;

use App\Entity\User;
use App\Entity\Instance;

interface InstanciableInterface
{
    function getUuid();
}