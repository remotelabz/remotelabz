<?php

namespace App\Service\Monitor;

use Exception;
use GuzzleHttp\Client;

class WorkerServiceMonitor extends AbstractServiceMonitor
{
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
        $url = 'http://'.getenv('WORKER_SERVER').':'.getenv('WORKER_PORT').'/healthcheck';
        try {
            $response = $client->get($url);
        } catch (Exception $exception) {
            return false;
        }

        return $response->getStatusCode() < 400;
    }
}
