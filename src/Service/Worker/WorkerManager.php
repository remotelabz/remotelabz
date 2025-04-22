<?php

namespace App\Service\Worker;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Exception;
use Psr\Log\LoggerInterface;
use App\Repository\ConfigWorkerRepository;
use App\Entity\ConfigWorker;
use App\Entity\Device;
use Doctrine\Persistence\ManagerRegistry;

class WorkerManager
{
    protected $publicAddress;

    public function __construct(
        string $publicAddress,
        string $workerServer,
        string $workerPort,
        LoggerInterface $logger,
        ClientInterface $client,
        ConfigWorkerRepository $configWorkerRepository,
        ManagerRegistry $doctrine
    ) {
        $this->publicAddress = $publicAddress;
        $this->workerServer = $workerServer;
        $this->workerPort = $workerPort;
        $this->logger = $logger;
        $this->client = $client;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->doctrine=$doctrine;
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
                $this->logger->error("Usage resources error - Web service or Worker ".$worker->getIPv4()." is not available");               
                /*
                $worker->setAvailable(0);
                $entityManager = $this->doctrine->getManager();
                $entityManager->persist($worker);
                $entityManager->flush();
                $this->logger->info("Worker ".$worker->getIPv4()." is disable");
                */
            }
        }
        $this->logger->info('Usage of each worker:',$usage);
            return $usage;
    }

    public function checkWorkersLightAction()
    {
        $client = new Client();
        //$workers = explode(',', $this->workerServer);
        $workers = $this->configWorkerRepository->findBy(["available" => true]);
        $usage = [];
        foreach($workers as $worker) {
            $url = 'http://'.$worker->getIPv4().':'.$this->workerPort.'/stats/hardwarelight';
            try {
                $response = $client->get($url);
                $content = json_decode($response->getBody()->getContents(), true);
                $this->logger->debug('Get '. $url);
                $content['worker'] = $worker->getIPv4();
                array_push($usage, $content);
            } catch (Exception $exception) {
                $this->logger->error("Light Usage resources error - Web service or Worker ".$worker->getIPv4()." is not available");               
                $worker->setAvailable(0);
                $entityManager = $this->doctrine->getManager();
                $entityManager->persist($worker);
                $entityManager->flush();
                $this->logger->info("Worker ".$worker->getIPv4()." is disable");
            }
        }
        $this->logger->info('Usage of each worker:',$usage);
            return $usage;
    }

    /*
    $item : the device or the lab we want to execute
    return The value of needed memory for all devices
    */
    private function Memory_Usage($item)
    {
        $memory=0;
        if ($item instanceof Device) {
            $memory = $item->getFlavor()->getMemory();
        }
        else {
            $memory = 0;
            foreach($item->getDevices() as $device) {
                $memory += ($device->getFlavor()->getMemory()) ;
            }
            
        }
        return $memory;
    }
    
    
    /*
    $item : the device or the lab we want to execute
    */
    public function getFreeWorker($item)
    {
        $min=0;
        $result="";
        $memory=$this->Memory_Usage($item);
        $usages = $this->checkWorkersLightAction();
        
        foreach ($usages as $usage) {
            $val=$this->loadBalancing($usage['memory'], $usage['disk'], $usage['cpu'], $memory, $usage['memory_total'],$usage['worker'], $usage['lxcfs']);
            $this->logger->debug("Score for worker ".$usage["worker"]." is ".$val);
            if ($val>$min) {
                $min=$val;
                $result=$usage['worker'];
            }
        }   

        return $result;
    }

    /*
    public function checkMemory($usages, $memory) {
        $workers = [];
        $this->logger->debug("checkMemory memory param:".$memory);

        foreach ($usages as $usage) {
            if ($usage['memory_total'] > $memory) {
                array_push($workers, $usage);
            }
        }
        $this->logger->debug("worker chosen from checkMemory :",$workers);

        return $workers;
    }

    public function checkCPU($usages) {
        $cpu = 100;
        $worker =null;
        foreach($usages as $usage) {
            if ($usage['cpu'] < $cpu) {
                $cpu = $usage['cpu'];
                $worker = $usage['worker'];
            }
        }
        $this->logger->debug("worker chosen from checkCPU :".$worker);
        return $worker;
    }
    */
    /*
    $memory : % used memory
    $disk : % used disk
    $cpu : % cpu load
    $needmemory : need memory to execute a lab
    $worker : IP of the worker to check
    */
    public function loadBalancing($memory, $disk, $cpu, $needmemory, $max_memory, $worker, $lxcfs) {
        // Limites maximales avant de considérer un serveur surchargé (ajuster selon vos besoins)
        $maxMemory = 85; // en pourcentage
        $maxDisk = 90; // en pourcentage
        $maxCpu = 90; // en pourcentage
        $maxlxcfs= 180; // max load CPU of lxcfs process, in purcent

        // Mémoire disponible : 
        
        $availableMemory = 100 - $memory; // in %
        $availableMemoryKB=$availableMemory*$max_memory;

        // Vérifier si le serveur peut gérer la charge en fonction de la mémoire disponible
        if ( $availableMemory*$max_memory < $needmemory) {
            $this->logger->info("Insufficient memory on worker: ".$worker." It need ".$needmemory." and we have only ".$availableMemoryKB." free");
        }
    
        // Calcul du score pour chaque paramètre
        $memoryScore = ($maxMemory - $memory) / $maxMemory; // Le plus bas sera pénalisant
        $diskScore = ($maxDisk - $disk) / $maxDisk; // Idem pour le disque
        $cpuScore = ($maxCpu - $cpu) / $maxCpu; // Idem pour le CPU
	$lxcfs = (int) $lxcfs;
        $lxcfsScore = ($maxlxcfs - $lxcfs) / $maxlxcfs;

        // Pondérer les scores pour obtenir un score final. On peut donner plus de poids à un paramètre en particulier si besoin.
        $finalScore = ($memoryScore * 0.3) + ($diskScore * 0.1) + ($cpuScore * 0.3) + ($lxcfsScore * 0.3);
    
        // Si le serveur est surchargé dans l'un des domaines, on considère qu'il est inapte.
        if ($memory >= $maxMemory || $disk >= $maxDisk || $cpu >= $maxCpu || $lxcfs >= $maxlxcfs ) {
            $this->logger->info("Worker: ".$worker." is overloaded");
        }
    
        // Retourne le score de santé du serveur
        return $finalScore;
    }
    
}
