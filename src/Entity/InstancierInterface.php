<?php

namespace App\Entity;

use JMS\Serializer\Annotation as Serializer;

/**
 * Represents an entity who is able to own and control an instance.
 */
interface InstancierInterface
{
    #[VirtualProperty]
    #[Groups(["lab", "start_lab", "stop_lab", "api_get_lab_instance", "api_get_device_instance", "instances"])]
    public function getUuid(): string;

    public function getName(): ?string;

    public function getType(): string;
}
