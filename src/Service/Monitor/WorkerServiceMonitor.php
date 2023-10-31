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
        $workers = explode(',', $this->workerServer);
        $nbWorkers = count($workers);
        if ($nbWorkers > 1) {
            foreach($workers as $worker) {
                $client = new Client();
                $url = 'http://'.$worker.':'.$this->workerPort.'/service/remotelabz-worker';
                try {
                    $response = $client->get($url, [
                        'query' => [
                            'action' => 'start'
                        ]
                    ]);
                } catch (Exception $exception) {
                    return false;
                }
            }
        }
        else {
            $client = new Client();
            $url = 'http://'.$this->workerServer.':'.$this->workerPort.'/service/remotelabz-worker';
            try {
                $response = $client->get($url, [
                    'query' => [
                        'action' => 'start'
                    ]
                ]);
            } catch (Exception $exception) {
                return false;
            }
        }
        

        return true;
    }

    public function stop()
    {
        $workers = explode(',', $this->workerServer);
        $nbWorkers = count($workers);
        if ($nbWorkers > 1) {
            foreach($workers as $worker) {
                $client = new Client();
                $url = 'http://'.$worker.':'.$this->workerPort.'/service/remotelabz-worker';
                try {
                    $response = $client->get($url, [
                        'query' => [
                            'action' => 'stop'
                        ]
                    ]);
                } catch (Exception $exception) {
                    return false;
                }
            }
        }
        else {
            $client = new Client();
            $url = 'http://'.$this->workerServer.':'.$this->workerPort.'/service/remotelabz-worker';
            try {
                $response = $client->get($url, [
                    'query' => [
                        'action' => 'stop'
                    ]
                ]);
            } catch (Exception $exception) {
                return false;
            }
        }
        

        return true;
    }

    public function isStarted(): bool
    {
        $workers = explode(',', $this->workerServer);
        $nbWorkers = count($workers);
        if ($nbWorkers > 1) {
            $healths = [];
            foreach($workers as $worker) {
                $client = new Client();
                $url = 'http://'.$worker.':'.$this->workerPort.'/healthcheck';
                try {
                    $response = $client->get($url, [
                        'query' => [
                            'action' => 'start'
                        ]
                    ]);
                } catch (Exception $exception) {
                    return false;
                }
                $health = json_decode($response->getBody()->getContents(), true);
                array_push($healths, $health['remotelabz-worker']['isStarted']);
            }
            if (in_array(false, $healths)) {
                return $health['remotelabz-worker']['isStarted'] = false;
            }
            else {
                return $health['remotelabz-worker']['isStarted'] = true;
            }
        }
        else {
            $client = new Client();
            $url = 'http://'.$this->workerServer.':'.$this->workerPort.'/healthcheck';
            try {
                $response = $client->get($url);
            } catch (Exception $exception) {
                return false;
            }

            $health = json_decode($response->getBody()->getContents(), true);

            return $health['remotelabz-worker']['isStarted'];
        }
    }
}
