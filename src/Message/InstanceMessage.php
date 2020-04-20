<?php

namespace App\Message;

class InstanceMessage
{
    private $content;
    private $uuid;
    private $action;
    const ACTION_START = "start";
    const ACTION_STOP  = "stop";

    /**
     * @param string $content Descriptor of the instance (JSON-formatted).
     * @param string $uuid Uuid of the device to start.
     */
    public function __construct(string $content, string $uuid, string $action)
    {
        $this->content = $content;
        $this->uuid = $uuid;
        $this->action = $action;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
