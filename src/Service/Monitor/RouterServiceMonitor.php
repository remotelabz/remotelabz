<?php

namespace App\Service\Monitor;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use App\Repository\LabInstanceRepository;
use App\Entity\LabInstance;
use App\Bridge\Network\IPTools;


class RouterServiceMonitor extends AbstractServiceMonitor
{
    private $logger;
    private $labInstanceRepository;
    private $workerPort;

    public function __construct(
        LabInstanceRepository $labInstanceRepository,
        string $workerPort,
        LoggerInterface $logger=null        
    ) {
        $this->LabInstanceRepository = $labInstanceRepository;
        $this->workerPort = $workerPort;
        $this->logger = $logger ?: new NullLogger();
    }

    public static function getServiceName(): string
    {
        return 'router';
    }

    public function start()
    {
        
        $labinstances = $this->LabInstanceRepository->findAll();

        foreach ($labinstances as $labinstance) {
            $workerIP=$labinstance->getWorkerIp();
            
            //$workerPort=$worker->getPort();
            if ($this->checkWorkerAvailable($workerIP,$this->workerPort)){

                $network=$labinstance->getNetwork();
                $this->logger->debug("Network for lab ".$labinstance->getLab()->getName()." is ".$network);
                //route exists ?
                if (IPTools::routeExists($network))
                    $this->logger->debug("Route to ".$network." exists, via ".$workerIP);
                else {
                    $this->logger->debug("Route to ".$network." doesn't exist, via ".$workerIP);
                    if (IPTools::routeAdd($network,$workerIP)) 
                        $this->logger->info("Route to ".$network." via ".$workerIP. " added");
                }
            } else {
                $this->logger->info("Worker ".$workerIP." is not responding, cannot add route to lab ".$labinstance->getLab()->getName()." network ".$labinstance->getNetwork());
            }
        }
        return true;      
    }

    public function stop()
    {
        return true;
    }

    public function isStarted() {
        return true;
    }


    private function checkWorkerAvailable(string $workerIP, $workerPort) {
        $client = new Client();
        $url = "http://".$workerIP.":".$workerPort."/os";

        try {
            $response = $client->get($url);
        } catch (Exception $exception) {
            $this->logger->info("Worker ".$workerIP." is not responding: ".$exception->getMessage());
            return false;
        }
        //$this->logger->debug("OS available on worker ".$workerIP." ".$response->getBody()->getContents());
        return true;
    }
}

