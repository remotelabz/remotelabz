<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use ErrorException;
use Exception;

class SshService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Connects to a remote server via SSH.
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string|null $publicKeyFile
     * @param string|null $privateKeyFile
     * @return resource|bool
     * @throws Exception
     */
    public function connect($host, $port, $username, $password, $publicKeyFile, $privateKeyFile)
    {
        // ... (body of the ssh function from SSHCopy.php)
        // Ensure to remove the `this->logger` references and use `$this->logger` instead.
        $connection = ssh2_connect($host, $port);
        if (!$connection) {
            throw new Exception('Échec de la connexion au serveur distant.');
        }
        $this->logger->debug("[SshService:connect]::Starting ssh connection");

        try {
            $this->logger->debug("[SshService:connect]::Authentication with pubkey test; username:".$username." publicKeyFile:".$publicKeyFile." privateKeyFile:".$privateKeyFile);
            if (ssh2_auth_pubkey_file($connection, $username, $publicKeyFile, $privateKeyFile)) {
                $this->logger->debug("[SshService:connect]::Authentication with pubkey successfull");
                return $connection;
            } else {
                $this->logger->debug("[SshService:connect]::Authentication with pubkey failed");
                throw new ErrorException('Authentication with pubkey failed');
            }
        } catch (ErrorException $e) {
            $this->logger->debug("[SshService:connect]::Test with authentication password");
            if (!ssh2_auth_password($connection, $username, $password)) {
                throw new ErrorException('SSH authentication with password failed');
            } else {
                $this->logger->debug("[SshService:connect]::SSH authentication with password successfull");
                return $connection;
            }
        }
    }

    /**
     * Copies a file to a remote server via SCP.
     */
    public function copyFile($connection, $localFile, $remoteFile, $workerDestIp)
    {
        // ... (body of the scp function from SSHCopy.php)
        // Ensure to remove the `this->logger` references and use `$this->logger` instead.
        $this->logger->debug("[SshService:copyFile]::Send file " . $localFile . " -> " . $remoteFile);
        $this->logger->info("Send " . $localFile . " file via scp to " . $workerDestIp . ":" . $remoteFile);

        try {
            $success = ssh2_scp_send($connection, $localFile, $remoteFile, 0660);
   
            if (!$success) {
                throw new ErrorException('Send file impossible');
            }
        } catch (ErrorException $e) {
            $this->logger->debug("[SshService:copyFile]::Send failed for file " . $localFile . " -> " . $remoteFile);
            $this->logger->error("Failed to scp " . $localFile);
            $this->logger->error($e->getMessage());
            return $e->getMessage();
        }

        return false;
    }

    /**
     * Executes a command on a remote server via SSH.
     */
    public function executeCommand($connection, $command)
    {
       
        $stream = ssh2_exec($connection, $command);
        if (!$stream) {
            throw new Exception('Command execution failed');
        }

        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        $this->logger->debug("[SshService:executeCommand]::Remote execution of " . $command);

        return false;
    }

    public function disconnect($connection)
    {
        // SSH2 connections are closed when the script ends, but you can also unset the connection resource.
        unset($connection);
        $this->logger->debug("[SshService:disconnect]::SSH connection closed");
    }
}