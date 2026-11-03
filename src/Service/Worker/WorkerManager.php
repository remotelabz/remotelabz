<?php

namespace App\Service\Worker;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Exception;
use Psr\Log\LoggerInterface;
use App\Repository\ConfigWorkerRepository;
use App\Entity\ConfigWorker;
use App\Entity\Device;
use App\Service\System\SystemdUnitClassifier;
use Doctrine\Persistence\ManagerRegistry;

class WorkerManager
{
    /**
     * Partitions hébergeant les charges utilisateurs, seules pertinentes pour
     * le calcul de l'occupation disque :
     *  - rlz-lxc-pool : pool thin des rootfs des instances LXC (conteneurs) ;
     *  - rlz-lv       : LV monté sur /opt, hébergeant les VMs QEMU.
     *
     * rlz-vg est volontairement ignoré : avec le thin provisioning, l'espace
     * libre du VG ne reflète pas le remplissage réel (cf. guide de
     * partitionnement du worker, §5).
     */
    private const DISK_KEY_LXC_POOL = 'rlz-lxc-pool';
    private const DISK_KEY_VM = 'rlz-lv';

    private $publicAddress;
    private $workerPort;
    private $workerServer;
    private $logger;
    private $client;
    private $configWorkerRepository;
    private $doctrine;
    private LabPlacementCache $placementCache;
    private SystemdUnitClassifier $systemdUnitClassifier;

    public function __construct(
        string $publicAddress,
        string $workerServer,
        string $workerPort,
        LoggerInterface $logger,
        ClientInterface $client,
        ConfigWorkerRepository $configWorkerRepository,
        ManagerRegistry $doctrine,
        LabPlacementCache $placementCache,
        SystemdUnitClassifier $systemdUnitClassifier
    ) {
        $this->publicAddress = $publicAddress;
        $this->workerServer = $workerServer;
        $this->workerPort = $workerPort;
        $this->logger = $logger;
        $this->client = $client;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->doctrine=$doctrine;
        $this->placementCache = $placementCache;
        $this->systemdUnitClassifier = $systemdUnitClassifier;
    }

    public function checkWorkersAction($timeout = null)
    {
        $options = is_numeric($timeout) && $timeout > 0 ? ['timeout' => (float) $timeout] : [];
        $client = new Client($options);
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

    public function checkWorkersSystemdStatusAction()
    {
        $client = new Client(['timeout' => 5]);
        $workers = $this->configWorkerRepository->findAll();
        $statuses = [];
        foreach($workers as $worker) {
            $status = [
                'worker' => $worker->getIPv4(),
                'label' => 'Worker ' . $worker->getIPv4(),
                'available' => (bool) $worker->getAvailable(),
                'reachable' => false,
                'error' => null,
                'generated_at' => null,
                'total' => 0,
                'running' => 0,
                'has_error' => false,
                'services' => [],
            ];
            if (!$status['available']) {
                $status['error'] = 'Worker is disabled, no check performed';
                array_push($statuses, $status);
                continue;
            }
            $url = 'http://'.$worker->getIPv4().':'.$this->workerPort.'/api/systemd/status';
            try {
                $response = $client->get($url);
                $content = json_decode($response->getBody()->getContents(), true);
                $this->logger->debug('Get '. $url);
                if (is_array($content) && isset($content['services'])) {
                    $status['reachable'] = true;
                    $status['generated_at'] = $content['generated_at'] ?? null;
                    $status['services'] = $this->systemdUnitClassifier->resolveOneshotPairs(array_map(
                        fn (array $service) => $this->systemdUnitClassifier->classify($service),
                        $content['services']
                    ));
                    // Recompute the counters with the correct unit semantics:
                    // slices and oneshot services are not expected to stay
                    // active (an empty slice means no lab is running).
                    $summary = $this->systemdUnitClassifier->summarize($status['services']);
                    $status['total'] = $summary['total'];
                    $status['running'] = $summary['running'];
                    $status['has_error'] = $summary['has_error'];
                } else {
                    $status['error'] = 'Invalid response from systemd API';
                }
            } catch (Exception $exception) {
                $status['error'] = 'Web service or worker is not available';
                $this->logger->error("Systemd status error - Web service or Worker ".$worker->getIPv4()." is not available");
            }
            array_push($statuses, $status);
        }
        $this->logger->info('Systemd status of each worker:',$statuses);
        return $statuses;
    }

    /*
    $item : the device or the lab we want to execute
    return The value of needed memory for all devices
    */
    public function computeMemoryUsage($item)
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
    Whether at least one worker is flagged as available in the database.
    Cheap database check, used to fail fast before running the placement
    (which queries the stats of every available worker over HTTP).
    */
    public function hasAvailableWorker(): bool
    {
        return $this->configWorkerRepository->count(['available' => true]) > 0;
    }

    /*
    $item : the device or the lab we want to execute
    */
    public function getFreeWorker($item)
    {
        $min=0;
        $result="";
        $memory=$this->computeMemoryUsage($item);
        $usages = $this->checkWorkersLightAction();

        foreach ($usages as $usage) {
            $workerIp = $usage['worker'];

            // Take into account the memory already reserved (via the placement
            // cache) for lab instances placed on this worker but not yet running.
            $reservedMemory = $this->placementCache->memoryAssignedTo($workerIp);
            $reservedMemoryPct = ($usage['memory_total'] > 0) ? ($reservedMemory / $usage['memory_total']) * 100 : 0;
            $adjustedMemory = $usage['memory'] + $reservedMemoryPct;

            if ($reservedMemory > 0) {
                $this->logger->debug("[WorkerManager:getFreeWorker]::Worker ".$workerIp." has ".$reservedMemory." of reserved memory (".$reservedMemoryPct."%), adjusted memory usage: ".$adjustedMemory."%");
            }

            // Exclude workers that do not have enough free memory (real + reserved) for the lab
            $availableMemory = (100 - $adjustedMemory) * $usage['memory_total'];
            if ($availableMemory < $memory) {
                $this->logger->info("[WorkerManager:getFreeWorker]::Worker ".$workerIp." excluded: insufficient free memory. It needs ".$memory." and has only ".$availableMemory." free (after ".$reservedMemory." reserved).");
                continue;
            }

            $diskUsage = $this->computeDiskUsage($usage['disk'] ?? []);
            $val=$this->loadBalancing($adjustedMemory, $diskUsage, $usage['cpu'], $memory, $usage['memory_total'],$usage['worker'], $usage['lxcfs']);
            $this->logger->debug("[WorkerManager:getFreeWorker]::Score for worker ".$usage["worker"]." is ".$val);
            if ($val>$min) {
                $min=$val;
                $result=$usage['worker'];
            }
        }

        return $result;
    }

    /*
    Calcule le taux d'occupation disque (%) à retenir pour le placement, à
    partir des stats disque remontées par le worker.

    Seules les partitions hébergeant les charges utilisateurs sont prises en
    compte (voir DISK_KEY_LXC_POOL / DISK_KEY_VM) ; rlz-vg et les autres
    entrées sont ignorées. On retient le taux le plus élevé des deux : un
    worker est pénalisé dès que l'une de ses deux partitions utilisateurs
    approche de la saturation.

    Les entrées absentes ou non numériques (worker ne remontant pas la
    mesure) sont ignorées ; si aucune n'est disponible, 0 est retourné.

    $disk : tableau des entrées disque du worker (clé => % d'occupation)
    */
    private function computeDiskUsage(array $disk): float
    {
        $usages = [];
        foreach ([self::DISK_KEY_LXC_POOL, self::DISK_KEY_VM] as $key) {
            if (isset($disk[$key]) && is_numeric($disk[$key])) {
                $usages[] = (float) $disk[$key];
            }
        }

        return empty($usages) ? 0.0 : max($usages);
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
    
        // Si le serveur est surchargé dans l'un des domaines, on considère qu'il est inapte.
        if ($memory >= $maxMemory || $disk >= $maxDisk || $cpu >= $maxCpu || $lxcfs >= $maxlxcfs ) {
            $this->logger->info("Worker: ".$worker." is overloaded");
        }
    
        // Retourne le score de santé du serveur
        return $finalScore;
    }
    
}
