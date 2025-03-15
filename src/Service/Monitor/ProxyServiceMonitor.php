<?php

namespace App\Service\Monitor;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use App\Entity\DeviceInstance;
use App\Repository\DeviceInstanceRepository;
use App\Service\Proxy\ProxyManager;


class ProxyServiceMonitor extends AbstractServiceMonitor
{

    //private $apiUrl = 'http://localhost:8001/api/routes';
    //private $authToken = 'votre_token_auth';
    private $remotelabzProxyServerAPI;
    private $remotelabzProxyApiPort;
    protected $deviceInstanceRepository;
    protected $proxyManager;

    public function __construct(
       $remotelabzProxyServerAPI,
       $remotelabzProxyApiPort,
       $deviceInstanceRepository,
       $proxyManager,
       LoggerInterface $logger=null        
    ) {
       $this->remotelabzProxyServerAPI;
       $this->remotelabzProxyApiPort;
       $this->deviceInstanceRepository = $deviceInstanceRepository;
       $this->proxyManager = $proxyManager;
       $this->logger = $logger ?: new NullLogger();
    }

    public static function getServiceName(): string
    {
        return 'remotelabz-proxy';
    }

    /*
    // Get route from the configurable-http-proxy
    public function getRoutes()
    {
         try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: token ' . $this->authToken
            ]);
            $output = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new \Exception('Error to get route:' . curl_error($ch));
            }
            curl_close($ch);
            return json_decode($output, true);
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
            return [];
        }
    }*/
    public function injectRoutes() {
        $this->logger->debug("getRoutes ");

        $deviceInstances = $this->deviceInstanceRepository->findBy(['state' => 'started']);
        foreach ($deviceInstances as $deviceInstance){
            foreach ($deviceInstance->getControlProtocolTypeInstances() as $controlProtocolTypeInstance) {
                $this->logger->debug($deviceInstance->getUuid()." ".$deviceInstance->getDevice()->getName()." port :".$controlProtocolTypeInstance->getPort()." ".$deviceInstance->getLabInstance()->getWorkerIp());
                $this->proxyManager->createContainerInstanceProxyRoute($deviceInstance->getUuid(),$controlProtocolTypeInstance->getPort(),$deviceInstance->getLabInstance()->getWorkerIp());
            }
        }
        //return json_decode($output, true);
    }

    /*
    // Return true when no error
    */
    public function startService()
    {   $result="";
        $this->logger->debug("Start remotelabz-proxy requested");
        try {
            $return=exec('sudo service remotelabz-proxy start');
            if ($return === false) {
                $this->logger->debug("start with error: ".$return);
                $result=false;
            } else {
                $result=true;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return $result;
    }

    /*public function injectRoutes($routes)
    {
        foreach ($routes as $route => $details) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/' . $route);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($details));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: token ' . $this->authToken,
                    'Content-Type: application/json'
                ]);
                $output = curl_exec($ch);
                if (curl_errno($ch)) {
                    throw new \Exception('Error when try to add route' . curl_error($ch));
                }
                curl_close($ch);
            } catch (\Exception $e) {
                $this->logger->info($e->getMessage());
            }
        }
    }*/

    public function start()
    {
        $result="";
        if ($this->startService()) {
            sleep(2);
            $this->injectRoutes();
            $result=true;
        }
            else $result=false;
        return $result;
    }
}
