<?php

namespace App\Service\Worker;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Exception;
use Psr\Log\LoggerInterface;

class WorkerManager
{
    protected $publicAddress;

    public function __construct(
        string $publicAddress,
        string $workerServer,
        string $workerPort,
        LoggerInterface $logger,
        ClientInterface $client
    ) {
        $this->publicAddress = $publicAddress;
        $this->workerServer = $workerServer;
        $this->workerPort = $workerPort;
        $this->logger = $logger;
        $this->client = $client;
    }

    public function checkWorkersAction()
    {
        $client = new Client();
        $workers = explode(',', $this->workerServer);
        $usage = [];
        foreach($workers as $worker) {
            $url = 'http://'.$worker.':'.$this->workerPort.'/stats/hardware';
            try {
                $response = $client->get($url);
                $content = json_decode($response->getBody()->getContents(), true);
                $content['worker'] = $worker;
                array_push($usage, $content);
            } catch (Exception $exception) {
                $this->addFlash('danger', 'Worker is not available');
                $this->logger->error('Usage resources error - Web service or Worker is not available');
                $content['disk'] = $content['cpu'] = $content['memory'] = null;
                $content['worker'] = $worker;
                array_push($usage, $content);
            }
        }

            return $usage;
    }

    public function getFreeWorker()
    {
        $usages = $this->checkWorkersAction();
        $disk = 100;
        $worker ="";
        foreach($usages as $usage) {
            if ($usage['disk'] < $disk) {
                $disk = $usage['disk'];
                $worker = $usage['worker'];
            }
        }
        //$freeWorker = ['disk'=> $disk, 'worker'=> $worker];

        return $worker;
    }
}
