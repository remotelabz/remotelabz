<?php

namespace App\Instance;

class InstanceState
{
    public const STARTED = "started";
    public const STOPPED = "stopped";
    public const STARTING = "starting";
    public const STOPPING = "stopping";
    public const CREATING = "creating";
    public const DELETING = "deleting";
    public const EXPORTING = "exporting";
    public const CREATED = "created";
    public const DELETED = "deleted";
    public const ERROR = "error";
}
