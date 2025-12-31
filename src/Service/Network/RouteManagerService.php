<?php

namespace App\Service\Network;

use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use App\Repository\LabInstanceRepository;
use App\Bridge\Network\IPTools;

class RouteManagerService
{
    private $labInstanceRepository;
    private $workerPort;
    private $logger;

    public function __construct(
        LabInstanceRepository $labInstanceRepository,
        string $workerPort,
        LoggerInterface $logger
    ) {
        $this->labInstanceRepository = $labInstanceRepository;
        $this->workerPort = $workerPort;
        $this->logger = $logger;
    }

    /**
     * Check and restore all routes for lab instances
     * 
     * @return array Statistics about the operation
     */
    public function syncAllRoutes(): array
    {
        $stats = [
            'total' => 0,
            'ok' => 0,
            'added' => 0,
            'worker_unavailable' => 0,
            'failed' => 0
        ];

        $labInstances = $this->labInstanceRepository->findAll();
        $stats['total'] = count($labInstances);

        foreach ($labInstances as $labInstance) {
            $result = $this->syncLabInstanceRoute($labInstance);
            $stats[$result]++;
        }

        return $stats;
    }

    /**
     * Check and restore route for a single lab instance
     * 
     * @return string Result status: 'ok', 'added', 'worker_unavailable', 'failed'
     */
    public function syncLabInstanceRoute($labInstance): string
    {
        $workerIP = $labInstance->getWorkerIp();
        $network = $labInstance->getNetwork();
        $labName = $labInstance->getLab()->getName();

        // Check worker availability
        if (!$this->checkWorkerAvailable($workerIP, $this->workerPort)) {
            $this->logger->info(sprintf(
                'Worker %s is not responding, cannot add route to lab %s network %s',
                $workerIP,
                $labName,
                $network
            ));
            return 'worker_unavailable';
        }

        // Check if route exists
        if (IPTools::routeExists($network)) {
            $this->logger->debug(sprintf('Route to %s exists, via %s', $network, $workerIP));
            return 'ok';
        }

        // Route doesn't exist, add it
        $this->logger->debug(sprintf('Route to %s doesn\'t exist, via %s', $network, $workerIP));
        
        if (IPTools::routeAdd($network, $workerIP)) {
            $this->logger->info(sprintf('Route to %s via %s added', $network, $workerIP));
            return 'added';
        }

        $this->logger->error(sprintf('Failed to add route to %s via %s', $network, $workerIP));
        return 'failed';
    }

    /**
     * Check if a worker is available
     */
    public function checkWorkerAvailable(string $workerIP, string $workerPort): bool
    {
        $client = new Client(['timeout' => 5, 'connect_timeout' => 3]);
        $url = sprintf('http://%s:%s/os', $workerIP, $workerPort);

        try {
            $response = $client->get($url);
            return $response->getStatusCode() === 200;
        } catch (Exception $exception) {
            $this->logger->debug(sprintf(
                'Worker %s health check failed: %s',
                $workerIP,
                $exception->getMessage()
            ));
            return false;
        }
    }
}