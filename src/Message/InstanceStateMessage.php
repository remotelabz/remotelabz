<?php

namespace App\Message;

class InstanceStateMessage
{
    private $type;
    private $uuid;
    private $state;
    const TYPE_LAB = "lab";
    const TYPE_DEVICE = "device";
    const STATE_STARTING = "starting";
    const STATE_STOPPING = "stopping";
    const STATE_STARTED = "started";
    const STATE_STOPPED = "stopped";
    const STATE_ERROR = "error";

    public function __construct(string $type, string $uuid, string $state)
    {
        $this->type = $type;
        $this->uuid = $uuid;
        $this->state = $state;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getState(): string
    {
        return $this->state;
    }
}
