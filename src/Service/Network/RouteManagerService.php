<?php

namespace App\Service\Network;

use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
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
     * @param bool $dryRun If true, only report which routes would be added without adding them
     * @return array Statistics about the operation
     */
    public function syncAllRoutes(bool $dryRun = false): array
    {
        $stats = [
            'total' => 0,
            'ok' => 0,
            'added' => 0,
            'fixed' => 0,
            'worker_unavailable' => 0,
            'failed' => 0
        ];

        $labInstances = $this->labInstanceRepository->findAll();
        $stats['total'] = count($labInstances);

        foreach ($labInstances as $labInstance) {
            $result = $this->syncLabInstanceRoute($labInstance, $dryRun);
            $stats[$result]++;
        }

        return $stats;
    }

    /**
     * Check and restore route for a single lab instance
     * 
     * @param bool $dryRun If true, report missing or incorrect routes without changing them
     * @return string Result status: 'ok', 'added', 'fixed', 'worker_unavailable', 'failed'
     */
    public function syncLabInstanceRoute($labInstance, bool $dryRun = false): string
    {
        $workerIP = $labInstance->getWorkerIp();
        $network = $labInstance->getNetwork();
        $labName = $labInstance->getLab()->getName();

        // No worker assigned to this lab instance (no active worker yet)
        if ($workerIP === null || $workerIP === '') {
            $this->logger->info(sprintf(
                'Lab %s network %s has no worker assigned, skipping route sync',
                $labName,
                $network
            ));
            return 'worker_unavailable';
        }

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

        // Is the expected route (to $network via the expected worker) already in place?
        if (IPTools::routeExistsVia($network, $workerIP)) {
            $this->logger->debug(sprintf('Route to %s via %s is correct', $network, $workerIP));
            return 'ok';
        }

        // The route is missing, or exists but points to a different (stale) worker.
        $routeIsWrong = IPTools::routeExists($network);

        if ($dryRun) {
            $this->logger->info(sprintf(
                '[DRY-RUN] Route to %s would be %s via %s',
                $network,
                $routeIsWrong ? 'corrected (currently via a different worker)' : 'added',
                $workerIP
            ));
            return $routeIsWrong ? 'fixed' : 'added';
        }

        try {
            IPTools::routeReplace($network, $workerIP);
        } catch (ProcessFailedException $exception) {
            $this->logger->error(sprintf(
                'Failed to set route to %s via %s: %s',
                $network,
                $workerIP,
                $exception->getProcess()->getOutput() ?: $exception->getMessage()
            ));
            return 'failed';
        }

        $this->logger->info(sprintf(
            'Route to %s via %s %s',
            $network,
            $workerIP,
            $routeIsWrong ? 'corrected' : 'added'
        ));
        return $routeIsWrong ? 'fixed' : 'added';
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