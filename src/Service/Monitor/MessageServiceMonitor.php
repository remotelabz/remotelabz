<?php

namespace App\Service\Monitor;

class MessageServiceMonitor extends AbstractServiceMonitor
{
    public static function getServiceName(): string
    {
        return 'remotelabz';
    }
}