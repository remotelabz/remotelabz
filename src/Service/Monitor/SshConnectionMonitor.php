<?php

namespace App\Service\Monitor;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use App\Repository\LabInstanceRepository;

class SshConnectionMonitor implements ServiceMonitorInterface
{
    private $logger;
    private $labInstanceRepository;
    private $sshUser;
    private $sshPassword;
    private $publicKeyFile;
    private $privateKeyFile;
    private $sshPort;

    public function __construct(
        string $sshUser,
        string $sshPassword,
        string $publicKeyFile,
        string $privateKeyFile,
        string $sshPort,
        LabInstanceRepository $labInstanceRepository,
        LoggerInterface $logger = null
    ) {
        $this->sshUser = $sshUser;
        $this->sshPassword = $sshPassword;
        $this->publicKeyFile = $publicKeyFile;
        $this->privateKeyFile = $privateKeyFile;
        $this->sshPort = $sshPort;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->logger = $logger ?: new NullLogger();
    }

    public static function getServiceName(): string
    {
        return 'ssh-connection-check';
    }

    /**
     * Check SSH connectivity to all workers
     * Returns an array with worker IPs as keys and connection status as values
     */
    public function isStarted()
    {
        $results = [];
        
        // Get all unique worker IPs from lab instances
        $labInstances = $this->labInstanceRepository->findAll();
        $workerIps = [];
        
        foreach ($labInstances as $labInstance) {
            $workerIp = $labInstance->getWorkerIp();
            if ($workerIp && !in_array($workerIp, $workerIps)) {
                $workerIps[] = $workerIp;
            }
        }

        // Test SSH connection to each worker
        foreach ($workerIps as $workerIp) {
            $results[$workerIp] = $this->testSshConnection($workerIp);
        }

        return $results;
    }

    /**
     * Test SSH connection to a specific worker
     */
    private function testSshConnection(string $host): array
    {
        try {
            $connection = ssh2_connect($host, $this->sshPort, null, ['timeout' => 5]);
            
            if (!$connection) {
                $this->logger->warning("SSH connection failed to {$host}");
                return [
                    'status' => false,
                    'method' => null,
                    'error' => 'Connection failed'
                ];
            }

            // Try public key authentication first
            if (file_exists($this->publicKeyFile) && file_exists($this->privateKeyFile)) {
                try {
                    if (ssh2_auth_pubkey_file($connection, $this->sshUser, $this->publicKeyFile, $this->privateKeyFile)) {
                        $this->logger->info("SSH connection successful to {$host} using public key");
                        ssh2_disconnect($connection);
                        return [
                            'status' => true,
                            'method' => 'pubkey',
                            'error' => null
                        ];
                    }
                } catch (Exception $e) {
                    $this->logger->debug("[SshConnectionMonitor:testSshConnection]::Public key authentication failed for {$host}: " . $e->getMessage());
                }
            }

            // Try password authentication as fallback
            try {
                if (ssh2_auth_password($connection, $this->sshUser, $this->sshPassword)) {
                    $this->logger->info("SSH connection successful to {$host} using password");
                    ssh2_disconnect($connection);
                    return [
                        'status' => true,
                        'method' => 'password',
                        'error' => null
                    ];
                }
            } catch (Exception $e) {
                $this->logger->warning("Password authentication failed for {$host}: " . $e->getMessage());
            }

            ssh2_disconnect($connection);
            return [
                'status' => false,
                'method' => null,
                'error' => 'Authentication failed'
            ];

        } catch (Exception $e) {
            $this->logger->error("SSH connection error to {$host}: " . $e->getMessage());
            return [
                'status' => false,
                'method' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Not applicable for this monitor
     */
    public function start()
    {
        $this->logger->info("SSH Connection Monitor: start() is not applicable");
        return true;
    }

    /**
     * Not applicable for this monitor
     */
    public function stop()
    {
        $this->logger->info("SSH Connection Monitor: stop() is not applicable");
        return true;
    }

    /**
     * Get detailed SSH status for a specific worker
     */
    public function getWorkerStatus(string $workerIp): array
    {
        return $this->testSshConnection($workerIp);
    }
}