<?php

namespace App\Service\Monitor;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

abstract class AbstractServiceMonitor implements ServiceMonitorInterface
{
    protected $serviceName;

    public function isStarted(): bool
    {
        $process = new Process([
            'systemctl',
            'status',
            $this->serviceName,
        ]);

        $status = $process->run();

        return 0 === $status;
    }

    public function start()
    {
        $process = new Process([
            'sudo',
            'systemctl',
            'start',
            $this->serviceName,
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public function stop()
    {
        $process = new Process([
            'sudo',
            'systemctl',
            'stop',
            $this->serviceName,
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }
}
