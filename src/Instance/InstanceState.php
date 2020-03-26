<?php

namespace App\Instance;

class InstanceState
{
    public const STARTED = "started";
    public const STOPPED = "stopped";
    public const STARTING = "starting";
    public const STOPPING = "stopping";
    public const ERROR = "error";
}
