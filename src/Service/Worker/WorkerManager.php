<?php

namespace App\Service\Worker;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Exception;
use Psr\Log\LoggerInterface;
use App\Repository\ConfigWorkerRepository;
use App\Entity\ConfigWorker;
use App\Entity\Device;
use App\Entity\LabInstance;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class WorkerManager
{
    private $publicAddress;
    private $workerPort;
    private $workerServer;

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
                
            }
        }
        $this->logger->info('Usage of each worker:',$usage);
            return $usage;
    }

    /**
     * Flag statique pour signaler au MessageHandler que le cache doit être invalidé
     * car un Worker est tombé pendant la fenêtre de cache.
     */
    public static bool $cacheInvalidated = false;

    public function checkWorkersLightAction()
    {
        // Timeout court (3s) pour détecter rapidement un Worker down
        $client = new Client([
            'connect_timeout' => 3,
            'timeout'         => 5,
        ]);
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
            } catch (ConnectException | RequestException $exception) {
                // Le Worker ne répond pas : on l'exclut IMMÉDIATEMENT du tableau
                // de résultats (il ne sera PAS candidat à ce cycle de placement)
                // ET on le marque indisponible en BDD pour les requêtes suivantes.
                $this->logger->error(
                    "[WorkerManager] Worker ".$worker->getIPv4()." ne répond pas (timeout/connexion refusée). "
                    ."Exclusion immédiate du cycle de placement et marquage indisponible en BDD."
                );
                $worker->setAvailable(0);
                $entityManager = $this->doctrine->getManager();
                $entityManager->persist($worker);
                $entityManager->flush();
                $this->logger->info("[WorkerManager] Worker ".$worker->getIPv4()." marqué indisponible (available=0).");

                // Signaler au MessageHandler que son cache est périmé
                self::$cacheInvalidated = true;
            } catch (Exception $exception) {
                // Toute autre erreur inattendue : même traitement défensif
                $this->logger->error(
                    "[WorkerManager] Erreur inattendue pour le Worker ".$worker->getIPv4().": ".$exception->getMessage().". "
                    ."Exclusion du cycle de placement."
                );
                self::$cacheInvalidated = true;
            }
        }
        $this->logger->info('Usage of each worker:', $usage);
        return $usage;
    }

    /*
    $item : the device or the lab we want to execute
    return The value of needed memory for all devices
    */
    public function Memory_Usage($item)
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
        // Initialiser à -999 (et non 0) pour que le Worker le moins mauvais
        // soit toujours sélectionné, même si tous les scores sont négatifs
        // (i.e. tous les Workers sont au-dessus de leurs seuils max).
        $min = -999.0;
        $result = "";
        $memory=$this->Memory_Usage($item);
        $usages = $this->checkWorkersLightAction();
        
        foreach ($usages as $usage) {
            $val=$this->loadBalancing($usage['memory'], $usage['disk'], $usage['cpu'], $memory, $usage['memory_total'],$usage['worker'], $usage['lxcfs']);
            $this->logger->debug("[WorkerManager:getFreeWorker]::Score for worker ".$usage["worker"]." is ".$val);
            if ($val>$min) {
                $min=$val;
                $result=$usage['worker'];
            }
        }   

        return $result;
    }

    /*
    $memory : % used memory
    $disk : % used disk
    $cpu : % cpu load
    $needmemory : need memory to execute a lab
    $worker : IP of the worker to check
    */
    public function loadBalancing($memory, $disk, $cpu, $needmemory, $max_memory, $worker, $lxcfs) {
        // Maximum limits before considering a server overloaded (adjust according to your needs)
        $maxMemory = 85; // %
        $maxDisk = 90; // %
        $maxCpu = 90; // %
        $maxlxcfs= 180; // max load CPU of lxcfs process, in %

        // free memory in %: 
        
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
    
        // Si le serveur est surchargé dans l'un des domaines, on retourne -1.0
        // pour garantir qu'il ne sera jamais sélectionné tant qu'un Worker sain est disponible.
        if ($memory >= $maxMemory || $disk >= $maxDisk || $cpu >= $maxCpu || $lxcfs >= $maxlxcfs) {
            $this->logger->info("Worker: ".$worker." is overloaded - score forcé à -1.0");
            return -1.0;
        }

        // Retourne le score de santé du serveur
        return $finalScore;
    }
    
    /**
     * Sélectionne le meilleur worker depuis un tableau de métriques (Réservation Virtuelle).
     * @param array $usages Tableau de métriques passé par référence.
     * @param float $memoryNeeded Mémoire nécessaire.
     */
    public function getBestWorkerFromMetrics(array $usages, $memoryNeeded)
    {
        $min = -999.0;
        $result = "";

        $labInstanceRepo = $this->doctrine->getRepository(LabInstance::class);

        foreach ($usages as $index => $usage) {
            // Find creating instances on this worker
            $creatingInstances = $labInstanceRepo->findBy([
                'workerIp' => $usage['worker'],
                'state' => 'creating'
            ]);

            $hiddenMemoryMB = 0;
            foreach ($creatingInstances as $creatingInstance) {
                if ($creatingInstance->getLab()) {
                    $hiddenMemoryMB += $this->Memory_Usage($creatingInstance->getLab());
                }
            }

            // Convert to percentage and add to current memory usage
            $hiddenMemoryPercentage = 0;
            if ($usage['memory_total'] > 0) {
                $hiddenMemoryPercentage = ($hiddenMemoryMB / $usage['memory_total']) * 100;
            }

            $effectiveMemory = $usage['memory'] + $hiddenMemoryPercentage;

            $val = $this->loadBalancing($effectiveMemory, $usage['disk'], $usage['cpu'], $memoryNeeded, $usage['memory_total'], $usage['worker'], $usage['lxcfs']);
            
            $this->logger->info("[WorkerManager:getBestWorkerFromMetrics]::Worker ".$usage['worker']." evaluated. RealMem: ".$usage['memory'].", HiddenMem: ".$hiddenMemoryPercentage.", Score: ".$val);

            if ($val > $min) {
                $min = $val;
                $result = $usage['worker'];
            }
        }

        return $result;
    }
}
