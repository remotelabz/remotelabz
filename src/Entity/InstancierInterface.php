<?php

namespace App\Entity;

use JMS\Serializer\Annotation as Serializer;

/**
 * Represents an entity who is able to own and control an instance.
 */
interface InstancierInterface
{
    /*
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager", "instances"})
     */
    public function getUuid();

    public function getName();
}
