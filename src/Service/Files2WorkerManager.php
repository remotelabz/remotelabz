<?php

namespace App\Service;

use App\Repository\ConfigWorkerRepository;
use Psr\Log\LoggerInterface;
use Exception;

class Files2WorkerManager
{
    private LoggerInterface $logger;
    private SshService $sshService;
    private ConfigWorkerRepository $configWorkerRepository;
    private string $sshUser;
    private string $sshPasswd;
    private string $sshPrivateKey;
    private string $sshPublicKey;
    private string $sshPort;
    private string $sshWorkerDirectory;

    public function __construct(
        LoggerInterface $logger,
        SshService $sshService,
        ConfigWorkerRepository $configWorkerRepository,
        string $sshUser,
        string $sshPasswd,
        string $sshPrivateKey,
        string $sshPublicKey,
        string $sshPort,
        string $sshWorkerDirectory
    ) {
        $this->logger = $logger;
        $this->sshService = $sshService;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->sshUser = $sshUser;
        $this->sshPasswd = $sshPasswd;
        $this->sshPrivateKey = $sshPrivateKey;
        $this->sshPublicKey = $sshPublicKey;
        $this->sshPort = $sshPort;
        $this->sshWorkerDirectory = $sshWorkerDirectory;
    }

    
    /**
     * Find if an available worker 
     * Return true if an available worker exist
     */
    public function AvailableWorkerExist(): bool
    {
        $available=false;
        $workers = $this->configWorkerRepository->findAll();
        foreach ($workers as $worker) {
            if ($worker->getAvailable()) {
                $available=($available || true);
            }
        }
        return $available;
    }

    /**
     * Copie un fichier ISO sur tous les workers disponibles
     */
    public function copyFileToAllWorkers(string $localFilePath, string $remoteFilePath): array
    {
        $results = [];
        $workers = $this->configWorkerRepository->findAll();
        $remoteFilePath = rtrim($this->sshWorkerDirectory, '/') . '/' . ltrim($remoteFilePath, '/');
        foreach ($workers as $worker) {
            if ($worker->getAvailable()) {
                try {
                    $this->logger->debug('[Files2WorkerManager:copyFileToAllWorkers]::Copying ' . $localFilePath . ' to worker: ' . $worker->getIPv4());
                    
                    $sshConnection = $this->sshService->connect(
                        $worker->getIPv4(),
                        $this->sshPort,
                        $this->sshUser,
                        $this->sshPasswd,
                        $this->sshPublicKey,
                        $this->sshPrivateKey
                    );
                    
                    if ($sshConnection) {
                        $this->sshService->copyFile($sshConnection, $localFilePath, $remoteFilePath, $worker->getIPv4());
                        $this->sshService->disconnect($sshConnection);
                        
                        $results[$worker->getIPv4()] = [
                            'success' => true,
                            'message' => 'ISO copied successfully'
                        ];
                        
                        $this->logger->debug('[Files2WorkerManager:copyFileToAllWorkers]::Copying ' . $localFilePath . ' to worker: ' . $worker->getIPv4() . ' done.');
                    } else {
                        $results[$worker->getIPv4()] = [
                            'success' => false,
                            'message' => 'Failed to establish SSH connection'
                        ];
                    }
                } catch (Exception $e) {
                    $this->logger->error('Failed to copy file to worker: ' . $worker->getIPv4() . ' - ' . $e->getMessage());
                    $results[$worker->getIPv4()] = [
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                }
            } else {
                $this->logger->debug('[Files2WorkerManager:copyFileToAllWorkers]::Skipping unavailable worker: ' . $worker->getIPv4());
                $results[$worker->getIPv4()] = [
                    'success' => false,
                    'message' => 'Worker not available'
                ];
            }
        }
        
        return $results;
    }

    /**
     * Supprime un fichier ISO de tous les workers disponibles
     */
    public function deleteFileFromAllWorkers(string $remoteFilePath): array
    {
        $results = [];
        $workers = $this->configWorkerRepository->findAll();
        $remoteFilePath = rtrim($this->sshWorkerDirectory, '/') . '/' . ltrim($remoteFilePath, '/');

        foreach ($workers as $worker) {
            if ($worker->getAvailable()) {
                try {
                    $this->logger->debug('[Files2WorkerManager:deleteFileFromAllWorkers]::Deleting ' . $remoteFilePath . ' from worker: ' . $worker->getIPv4());
                    
                    $command = 'rm -f ' . escapeshellarg($remoteFilePath);
                    $sshConnection = $this->sshService->connect(
                        $worker->getIPv4(),
                        $this->sshPort,
                        $this->sshUser,
                        $this->sshPasswd,
                        $this->sshPublicKey,
                        $this->sshPrivateKey
                    );
                    
                    if ($sshConnection) {
                        $this->sshService->executeCommand($sshConnection, $command);
                        $this->sshService->disconnect($sshConnection);
                        
                        $results[$worker->getIPv4()] = [
                            'success' => true,
                            'message' => 'ISO deleted successfully'
                        ];
                        
                        $this->logger->debug('[Files2WorkerManager:deleteFileFromAllWorkers]::Deleting ' . $remoteFilePath . ' from worker: ' . $worker->getIPv4() . ' done.');
                    } else {
                        $results[$worker->getIPv4()] = [
                            'success' => false,
                            'message' => 'Failed to establish SSH connection'
                        ];
                    }
                } catch (Exception $e) {
                    $this->logger->error('Failed to delete $remoteFilePath from worker: ' . $worker->getIPv4() . ' - ' . $e->getMessage());
                    $results[$worker->getIPv4()] = [
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                }
            } else {
                $this->logger->debug('[Files2WorkerManager:deleteFileFromAllWorkers]::Skipping unavailable worker: ' . $worker->getIPv4());
                $results[$worker->getIPv4()] = [
                    'success' => false,
                    'message' => 'Worker not available'
                ];
            }
        }
        
        return $results;
    }

    /**
     * Copie un ISO vers un worker spécifique
     */
    public function copyFileToWorker(string $localFilePath, string $remoteFilePath, string $workerIp): bool
    {
        try {
            $this->logger->debug('[Files2WorkerManager:copyFileToWorker]::Copying ' . $localFilePath . ' to worker: ' . $workerIp);
            
            $sshConnection = $this->sshService->connect(
                $workerIp,
                $this->sshPort,
                $this->sshUser,
                $this->sshPasswd,
                $this->sshPublicKey,
                $this->sshPrivateKey
            );
            
            if ($sshConnection) {
                $this->sshService->copyFile($sshConnection, $localFilePath, $remoteFilePath, $workerIp);
                $this->sshService->disconnect($sshConnection);
                
                $this->logger->debug('[Files2WorkerManager:copyFileToWorker]::Copying ' . $localFilePath . ' to worker: ' . $workerIp . ' done.');
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            $this->logger->error('Failed to copy to worker: ' . $workerIp . ' - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un ISO d'un worker spécifique
     */
    public function deleteFileFromWorker(string $remoteFilePath, string $workerIp): bool
    {
        try {
            $this->logger->debug('[Files2WorkerManager:deleteFileFromWorker]::Deleting ' . $remoteFilePath . ' from worker: ' . $workerIp);
            
            $command = 'rm -f ' . escapeshellarg($remoteFilePath);
            $sshConnection = $this->sshService->connect(
                $workerIp,
                $this->sshPort,
                $this->sshUser,
                $this->sshPasswd,
                $this->sshPublicKey,
                $this->sshPrivateKey
            );
            
            if ($sshConnection) {
                $this->sshService->executeCommand($sshConnection, $command);
                $this->sshService->disconnect($sshConnection);
                
                $this->logger->debug('[Files2WorkerManager:deleteFileFromWorker]::Deleting ' . $remoteFilePath . ' from worker: ' . $workerIp . ' done.');
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            $this->logger->error('Failed to delete from worker: ' . $workerIp . ' - ' . $e->getMessage());
            return false;
        }
    }
}