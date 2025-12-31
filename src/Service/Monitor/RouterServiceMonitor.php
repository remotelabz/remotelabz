<?php

namespace App\Service\Monitor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use App\Repository\LabInstanceRepository;
use App\Service\Network\RouteManagerService;

class RouterServiceMonitor extends AbstractServiceMonitor
{
    private $routeManager;
    private $logger;

    public function __construct(
        RouteManagerService $routeManager,
        LoggerInterface $logger = null        
    ) {
        $this->routeManager = $routeManager;
        $this->logger = $logger ?: new NullLogger();
    }

    public static function getServiceName(): string
    {
        return 'router';
    }

    public function start()
    {
        $this->logger->info('Starting route synchronization');
        
        $stats = $this->routeManager->syncAllRoutes();
        
        $this->logger->info(sprintf(
            'Route sync completed: %d total, %d ok, %d added, %d worker unavailable, %d failed',
            $stats['total'],
            $stats['ok'],
            $stats['added'],
            $stats['worker_unavailable'],
            $stats['failed']
        ));
        
        return true;      
    }

    public function stop()
    {
        return true;
    }

    public function isStarted() {
        return true;
    }
}