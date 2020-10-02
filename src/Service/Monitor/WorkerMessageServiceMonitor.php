<?php

namespace App\Service\Monitor;

use Exception;
use GuzzleHttp\Client;

class WorkerMessageServiceMonitor extends AbstractServiceMonitor
{
    public static function getServiceName(): string
    {
        return 'remotelabz-worker-message';
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

        $health = json_decode($response->getBody()->getContents(), true);

        return $health['remotelabz-worker']['isStarted'];
    }
}
