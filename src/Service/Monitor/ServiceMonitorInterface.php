<?php

namespace App\Service\Monitor;

interface ServiceMonitorInterface
{
    /**
     * Tells if the service is started.
     */
    public function isStarted(): bool;

    /**
     * Start the service.
     */
    public function start();

    /**
     * Stop the service.
     */
    public function stop();

    public function getServiceName(): string;
}
