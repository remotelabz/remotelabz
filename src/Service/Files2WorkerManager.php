<?php

namespace App\Service;

use App\Repository\ConfigWorkerRepository;
use Remotelabz\Message\Message\InstanceActionMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Security\Core\Security;



use Psr\Log\LoggerInterface;
use Exception;

class Files2WorkerManager
{
    private LoggerInterface $logger;
    private SshService $sshService;
    private ConfigWorkerRepository $configWorkerRepository;
    private Security $security;
    private string $sshUser;
    private string $sshPasswd;
    private string $sshPrivateKey;
    private string $sshPublicKey;
    private string $sshPort;
    private string $sshWorkerDirectory;
    private $bus;
    
    public function __construct(
        LoggerInterface $logger,
        SshService $sshService,
        ConfigWorkerRepository $configWorkerRepository,
        MessageBusInterface $bus,
        Security $security,
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
        $this->bus = $bus;
        $this->security = $security;
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
     * Copie un fichier sur tous les workers disponibles
     * @param $type can be iso or image
     * @param $localfileName
     */
    public function copyFileToAllWorkers(string $type,string $localFilename): array
    {
        $workers = $this->configWorkerRepository->findAll();
        foreach ($workers as $worker) {
                $content = json_encode([
                    'filename' => $localFilename,
                    'file_type' => $type,
                    'worker_ip' => $worker->getIPv4(),
                    'user_id' => $this->getCurrentUserId()
                ]);
                $this->logger->debug('[Files2WorkerManager:copyFileToAllWorkers]::Send message to ' . $worker->getIPv4() ." ".$localFilename);

                $this->bus->dispatch(
                    new InstanceActionMessage($content, "", InstanceActionMessage::ACTION_COPYFROMFRONT), [
                        new AmqpStamp($worker->getIPv4(), AMQP_NOPARAM, []),
                    ]
                );
        }
    }

    /**
     * Supprime un fichier ISO de tous les workers disponibles
     */
    public function deleteFileFromAllWorkers(string $type,string $remoteFilename): array
    {
        $workers = $this->configWorkerRepository->findAll();
        foreach ($workers as $worker) {
            $content = json_encode([
                'filename' => $localFilename,
                'file_type' => $type,
                'worker_ip' => $worker->getIPv4(),
                'user_id' => $this->getCurrentUserId()
            ]);
            
            $this->bus->dispatch(
                new InstanceActionMessage($content, "", InstanceActionMessage::ACTION_DELETEISO), [
                    new AmqpStamp($worker->getIPv4(), AMQP_NOPARAM, []),
                ]
            ); 

            $this->logger->debug('[Files2WorkerManager:deleteFileFromAllWorkers]::Deleting ' .$type." ".$remoteFilename.' from worker: ' . $worker->getIPv4());
        }
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

    private function getCurrentUserId(): ?string
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return null; // Pas d'utilisateur connecté
        }
        
        // Adapter selon votre entité User
        if (method_exists($user, 'getId')) {
            return (string) $user->getId();
        }
        
        return null;
    }
}