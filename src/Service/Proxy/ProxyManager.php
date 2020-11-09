<?php

namespace App\Service\Proxy;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class ProxyManager
{
    protected $logger;
    protected $workerServer;
    protected $remotelabzProxyServer;
    protected $remotelabzProxyPort;
    protected $remotelabzProxyApiPort;
    protected $remotelabzProxyUseHttps;

    public function __construct(
        string $workerServer,
        string $remotelabzProxyServer,
        int $remotelabzProxyPort,
        int $remotelabzProxyApiPort,
        bool $remotelabzProxyUseHttps,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->workerServer = $workerServer;
        $this->remotelabzProxyServer = $remotelabzProxyServer;
        $this->remotelabzProxyPort = $remotelabzProxyPort;
        $this->remotelabzProxyApiPort = $remotelabzProxyApiPort;
        $this->remotelabzProxyUseHttps = $remotelabzProxyUseHttps;
    }

    public function getRemotelabzProxyServer(): string
    {
        return $this->remotelabzProxyServer;
    }

    public function getRemotelabzProxyPort(): int
    {
        return $this->remotelabzProxyPort;
    }

    public function getRemotelabzProxyUseHttps(): bool
    {
        return $this->remotelabzProxyUseHttps;
    }

    /**
     * Create a new route in remotelabz-proxy service.
     * 
     * @param string $uuid UUID of the device instance
     * @param int $remotePort Port used by websockify
     */
    public function createDeviceInstanceProxyRoute(string $uuid, int $remotePort)
    {
        $client = new Client();

        $url = ($this->remotelabzProxyUseHttps ? 'https' : 'http').'://'.$this->remotelabzProxyServer.':'.$this->remotelabzProxyApiPort.'/api/routes/device/'.$uuid;
        $this->logger->debug('Create route in proxy', [
            'url' => $url
        ]);

        $client->post($url, [
            'body' => json_encode([
                'target' => 'ws://'.$this->workerServer.':'.($remotePort + 1000).'',
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Delete a route in remotelabz-proxy service.
     * 
     * @param string $uuid UUID of the device instance
     */
    public function deleteDeviceInstanceProxyRoute(string $uuid)
    {
        $client = new Client();

        $url = ($this->remotelabzProxyUseHttps ? 'https' : 'http').'://'.$this->remotelabzProxyServer.':'.$this->remotelabzProxyApiPort.'/api/routes/device/'.$uuid;
        $this->logger->debug('Delete route in proxy', [
            'url' => $url
        ]);

        $client->delete($url);
    }
}