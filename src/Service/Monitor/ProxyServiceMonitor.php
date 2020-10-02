<?php

namespace App\Service\Monitor;

class ProxyServiceMonitor extends AbstractServiceMonitor
{
    public static function getServiceName(): string
    {
        return 'remotelabz-proxy';
    }
}
