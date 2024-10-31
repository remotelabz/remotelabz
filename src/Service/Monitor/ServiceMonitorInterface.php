<?php

namespace App\Service\Monitor;

interface ServiceMonitorInterface
{
    /**
     * Tells if the service is started.
     */
    public function isStarted();

    /**
     * Start the service.
     */
    public function start();

    /**
     * Stop the service.
     */
    public function stop();

    public static function getServiceName(): string;
}
