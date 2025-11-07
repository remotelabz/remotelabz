<?php

namespace App\Service\Monitor;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use App\Repository\LabInstanceRepository;
use App\Entity\LabInstance;
use App\Bridge\Network\IPTools;

class WorkerServiceMonitor extends AbstractServiceMonitor
{
    protected $workerServer;
    protected $workerPort;
    private $logger;
    private $labInstanceRepository;
    private $publicKeyFile;
    private $privateKeyFile;
    private $sshUser;
    private $sshPassword;

    public function __construct(
        string $workerPort,
        string $workerServer,
        string $publicKeyFile,
        string $privateKeyFile,
        string $sshUser,
        string $sshPassword,
        LabInstanceRepository $labInstanceRepository,
        LoggerInterface $logger = null         
    ) {
        $this->workerPort = $workerPort;
        $this->workerServer = $workerServer;
        $this->sshUser=$sshUser;
        $this->sshPassword=$sshPassword;
        $this->publicKeyFile=$publicKeyFile;
        $this->privateKeyFile=$privateKeyFile;
        $this->labInstanceRepository = $labInstanceRepository; // Correction : c'était $this->LabInstanceRepository
        $this->logger = $logger ?: new NullLogger();
    }

    public static function getServiceName(): string
    {
        return 'remotelabz-worker';
    }

    public function getServiceSubName(): string
    {
        return $this->workerServer;
    }

    public function start()
    {
        $this->logger->info("Try to start remotelabz-worker service worker: ".$this->workerServer. " Port: ".$this->workerPort);
        
        
        $connection = $this->ssh(
            $this->workerServer,
            "22",
            $this->sshUser,
            $this->sshPassword,
            $this->publicKeyFile,
            $this->privateKeyFile,
        );
        if ($connection) {
            $cmd="sudo systemctl restart remotelabz-worker";
            $result=$this->executeRemoteCommand($connection,$cmd);
            $this->logger->error("Service started with ssh");
            ssh2_disconnect($connection);            

            //Looking for all routes to lab on this worker
            $labinstances = $this->labInstanceRepository->findBy(['workerIp' => $this->workerServer]);
            foreach ($labinstances as $labinstance) {
                $network=$labinstance->getNetwork();
                $this->logger->debug("Network for lab ".$labinstance->getLab()->getName()." is ".$network);
                //route exists ?
                if (IPTools::routeExists($network))
                    $this->logger->debug("Route to ".$network." exists, via ".$this->workerServer);
                else {
                    $this->logger->debug("Route to ".$network." doesn't exist, via ".$this->workerServer);
                    if (IPTools::routeAdd($network,$this->workerServer)) 
                        $this->logger->info("Route to ".$network." via ".$this->workerServer. " added");

                }
            
            }
            return true;
        }
        else {
            $this->logger->error("Error to start the service with ssh");
            $this->logger->debug("[WorkerServiceMonitor:start]::Service remotelabz-worker doesn't start :".$exception->getMessage());
            return false;
        }
    }

    public function stop()
    {
        $this->logger->info("Try to stop remotelabz-worker service worker: ".$this->workerServer. " Port: ".$this->workerPort);

        $this->logger->debug("[WorkerServiceMonitor:stop]::Try to use ssh with pubkey ",
            [
                "publicKeyFile" => $this->publicKeyFile,
                "privateKeyFile" => $this->privateKeyFile,
                "sshUser" => $this->sshUser,
                "sshPassword" => $this->sshPassword
            ]);
        $connection = $this->ssh(
                $this->workerServer,
                "22",
                $this->sshUser,
                $this->sshPassword,
                $this->publicKeyFile,
                $this->privateKeyFile,
            );
        if ($connection) {
            $cmd = "sudo systemctl stop remotelabz-worker";
            $this->executeRemoteCommand($connection, $cmd);
            $this->logger->info("Service stopped with ssh");
            ssh2_disconnect($connection);
            return true;
        }
        else {
            $this->logger->error("Failed to stop service via ssh: ".$e->getMessage());
            return false;
        }
        return true;
    }

    public function isStarted(): array
    {
            $client = new Client();
            $url = 'http://'.$this->workerServer.':'.$this->workerPort.'/healthcheck';
            try {
                $response = $client->get($url,['timeout' => 3]);
            }
            catch (Exception $e) {
                    return array(
                        "power" => (bool) false,
                        "error_code" => 1,
                        "service" => $e->getMessage());
            }
           
            $health = json_decode($response->getBody()->getContents(), true);
            //$this->logger->debug("isStarted: ",$health);
         
            return array("power" => (bool) true,
                            "error_code" => 0,
                            "service" => $health['remotelabz-worker']['isStarted']
                        );
    }

/**
     * Fonction pour exécuter une commande sur un serveur distant via SSH en utilisant une clé privée.
     *
     * @param string $host            L'adresse IP ou le nom de domaine du serveur distant.
     * @param int    $port            Le port SSH (par défaut 22).
     * @param string $username        Le nom d'utilisateur SSH.
     * @param string $privateKeyFile  Le chemin vers la clé privée.
     * @param string $publicKeyFile   Le chemin vers la clé publique (facultatif).
     * @return string|bool            Le résultat de la commande, ou false en cas d'échec.
     * @throws Exception              Lève une exception en cas d'échec de connexion ou d'exécution.
    */
    function ssh($host, $port, $username, $password, $publicKeyFile, $privateKeyFile) {
        $connection = ssh2_connect($host, $port);
        if (!$connection) {
            throw new Exception('Échec de la connexion au serveur distant.');
            return false;
        }
        $this->logger->debug("[WorkerServiceMonitor:ssh]::Starting ssh connection");

        try {
            // Authentification avec la clé privée
            $this->logger->debug("[WorkerServiceMonitor:ssh]::Try to authenticate with pubkey ".$publicKeyFile);
            if (ssh2_auth_pubkey_file($connection, $username, $publicKeyFile,$privateKeyFile)) {
                $this->logger->debug("[WorkerServiceMonitor:ssh]::Authentication with pubkey successfull");
                return $connection;
                }
                else {
                    $this->logger->debug("[WorkerServiceMonitor:ssh]::Authentication with pubkey failed");
                    throw new ErrorException('Authentication with pubkey failed');
                    return false;
                }
        }
        catch (ErrorException $e) {
            // Gestion de l'erreur
            // return $e->getMessage();
            $this->logger->debug("[WorkerServiceMonitor:ssh]::Test with authentication password");
            if (!ssh2_auth_password($connection, $username, $password)) {
                throw new ErrorException('Authentication with password failed');
                return false;
            } else {
                $this->logger->debug("[WorkerServiceMonitor:ssh]::Authentication with password successfull");
                return $connection;
            }
        }
    }

    /**
     * Fonction pour exécuter une commande sur un serveur distant via SSH en utilisant une clé privée.
     *
     * @param resource $connection    A connection authenticated
     * @param string $command         La commande à exécuter sur le serveur distant.
     * @return string|bool            Le résultat de la commande, ou false en cas d'échec.
     * @throws Exception              Lève une exception en cas d'échec de connexion ou d'exécution.
     */
    function executeRemoteCommand($connection, $command) {  

            // Exécution de la commande
            $stream = ssh2_exec($connection, $command);
            if (!$stream) {
                throw new Exception('Command execution failed');
            }

            // Gestion des flux de sortie
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            //$this->logger->debug("Exec4 ssh return :".$output);

            fclose($stream); // Fermer le flux après l'exécution
            //$this->logger->debug("Exec5 ssh return :".$command);

            return false;
    }

}
