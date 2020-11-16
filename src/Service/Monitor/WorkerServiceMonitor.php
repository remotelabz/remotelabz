<?php

namespace App\Service\Monitor;

use Exception;
use GuzzleHttp\Client;

class WorkerServiceMonitor extends AbstractServiceMonitor
{
    protected $workerServer;
    protected $workerPort;

    public function __construct(
        string $workerPort,
        string $workerServer
    ) {
        $this->workerPort = $workerPort;
        $this->workerServer = $workerServer;
    }

    public static function getServiceName(): string
    {
        return 'remotelabz-worker';
    }

    public function start()
    {
        return true;
    }

    public function stop()
    {
        return true;
    }

    public function isStarted(): bool
    {
        $client = new Client();
        $url = 'http://'.$this->workerServer.':'.$this->workerPort.'/healthcheck';
        try {
            $response = $client->get($url);
        } catch (Exception $exception) {
            return false;
        }

        return $response->getStatusCode() < 400;
    }
}
