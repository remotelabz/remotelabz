<?php

namespace App\Service\Monitor;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class WorkerServiceMonitor extends AbstractServiceMonitor
{
    protected $workerServer;
    protected $workerPort;
    private $logger;

    public function __construct(
        string $workerPort,
        string $workerServer,
        LoggerInterface $logger=null
    ) {
        $this->workerPort = $workerPort;
        $this->workerServer = $workerServer;
        $this->logger = $logger ?: new NullLogger();       
    }

    public static function getServiceName(): string
    {
        return 'remotelabz-worker';
    }

    public function getServiceSubName(): string
    {
        return $this->workerServer;
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

    public function isStarted(): array
    {
            //$this->logger->info("Worker: ".$this->workerServer);

            $client = new Client();
            $url = 'http://'.$this->workerServer.':'.$this->workerPort.'/healthcheck';
            try {
                $response = $client->get($url);
            }
            catch (Exception $e) {
                return array(
                    "statut" => (bool) false,
                    "error_code" => 1,
                    "value" => $e->getMessage());
            }
           
            $health = json_decode($response->getBody()->getContents(), true);
            $this->logger->debug("isStarted: ",$health);            
            return array("statut" => (bool) true,
                            "error_code" => 0,
                            "value" => $health['remotelabz-worker']['isStarted']
                        );
    }
}
