<?php

namespace App\Service\Worker;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Exception;
use Psr\Log\LoggerInterface;
use App\Repository\ConfigWorkerRepository;
use App\Entity\ConfigWorker;
use App\Entity\Device;

class WorkerManager
{
    protected $publicAddress;

    public function __construct(
        string $publicAddress,
        string $workerServer,
        string $workerPort,
        LoggerInterface $logger,
        ClientInterface $client,
        ConfigWorkerRepository $configWorkerRepository
    ) {
        $this->publicAddress = $publicAddress;
        $this->workerServer = $workerServer;
        $this->workerPort = $workerPort;
        $this->logger = $logger;
        $this->client = $client;
        $this->configWorkerRepository = $configWorkerRepository;
    }

    public function checkWorkersAction()
    {
        $client = new Client();
        //$workers = explode(',', $this->workerServer);
        $workers = $this->configWorkerRepository->findBy(["available" => true]);
        $usage = [];
        foreach($workers as $worker) {
            $url = 'http://'.$worker->getIPv4().':'.$this->workerPort.'/stats/hardware';
            try {
                $response = $client->get($url);
                $content = json_decode($response->getBody()->getContents(), true);
                $this->logger->debug('Get '. $url);
                $content['worker'] = $worker->getIPv4();
                array_push($usage, $content);
            } catch (Exception $exception) {
                $this->logger->error('Usage resources error - Web service or Worker is not available');
                $content['disk'] = $content['cpu'] = $content['memory'] = $content['memoryAvailable'] = null;
                $content['worker'] = $worker->getIPv4();
                array_push($usage, $content);
            }
        }
        $this->logger->debug('Usage return from checkWorkersAction :',$usage);
            return $usage;
    }

    public function getFreeWorker($item)
    {
        $usages = $this->checkWorkersAction();
        if ($item instanceof Device) {
            $memory = $item->getFlavor()->getMemory() / 2;
        }
        else {
            $memory = 0;
            foreach($item->getDevices() as $device) {
                $memory += ($device->getFlavor()->getMemory() / 2) ;
            }
            
        }
        $memoryFreeUsages = $this->checkMemory($usages, $memory);
        $worker = $this->checkCPU($memoryFreeUsages);
        $this->logger->debug("worker chosen from getFreeWorker :".$worker);
        return $worker;
    }

    public function checkMemory($usages, $memory) {
        $workers = [];
        $this->logger->debug("checkMemory memory param:".$memory);

        foreach ($usages as $usage) {
            if ($usage['memory'] > $memory) {
                array_push($workers, $usage);
            }
        }
        $this->logger->debug("worker chosen from checkMemory :",$workers);

        return $workers;
    }

    public function checkCPU($usages) {
        $cpu = 100;
        $worker ="";
        foreach($usages as $usage) {
            if ($usage['cpu'] < $cpu) {
                $disk = $usage['cpu'];
                $worker = $usage['worker'];
            }
        }
        $this->logger->debug("worker chosen from checkCPU :".$worker);

        return $worker;
    }
}
