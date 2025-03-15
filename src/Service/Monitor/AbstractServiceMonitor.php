<?php

namespace App\Service\Monitor;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


abstract class AbstractServiceMonitor implements ServiceMonitorInterface
{
    protected $serviceName;
    
    
    public function isStarted()
    {

        $process = new Process([
            '/bin/systemctl','status',
            static::getServiceName()
        ]);

        $status = $process->run();

        return 0 === $status;
    }

    public function start()
    {
        $process = new Process([
            'sudo', '/bin/systemctl','start',
            static::getServiceName()
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
            return false;
        }

        return true;

    }

    public function stop()
    {
        $process = new Process([
            'sudo', '/bin/systemctl','stop',
            static::getServiceName()
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
            return false;
        }
        return true;
    }

    public static abstract function getServiceName(): string;
}
