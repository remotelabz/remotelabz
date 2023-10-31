<?php

namespace App\Service\Proxy;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class ProxyManager
{
    protected $logger;
    protected $workerServer;
    protected $remotelabzProxyServer;
    protected $remotelabzProxyServerAPI;
    protected $remotelabzProxyPort;
    protected $remotelabzProxyApiPort;
    protected $remotelabzProxyUseHttps;
    protected $remotelabzProxyUseWss;

    public function __construct(
        string $workerServer,
        string $remotelabzProxyServer,
        string $remotelabzProxyServerAPI,
        int $remotelabzProxyPort,
        int $remotelabzProxyApiPort,
        bool $remotelabzProxyUseHttps,
        bool $remotelabzProxyUseWss,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->workerServer = $workerServer;
        $this->remotelabzProxyServer = $remotelabzProxyServer;
        $this->remotelabzProxyServerAPI = $remotelabzProxyServerAPI;
        $this->remotelabzProxyPort = $remotelabzProxyPort;
        $this->remotelabzProxyApiPort = $remotelabzProxyApiPort;
        $this->remotelabzProxyUseHttps = $remotelabzProxyUseHttps;
        $this->remotelabzProxyUseWss = $remotelabzProxyUseWss;
    }

    public function getRemotelabzProxyServer(): string
    {
        return $this->remotelabzProxyServer;
    }

    public function getRemotelabzProxyServerAPI(): string
    {
        return $this->remotelabzProxyServerAPI;
    }

    public function getRemotelabzProxyPort(): int
    {
        return $this->remotelabzProxyPort;
    }

    public function getRemotelabzProxyUseHttps(): bool
    {
        return $this->remotelabzProxyUseHttps;
    }

    public function getRemotelabzProxyUseWss(): bool
    {
        return $this->remotelabzProxyUseWss;
    }

    /**
     * Create a new route in remotelabz-proxy service.
     * 
     * @param string $uuid UUID of the device instance
     * @param int $remotePort Port used by websockify
     */
    public function createDeviceInstanceProxyRoute(string $uuid, int $remotePort, string $worker)
    {
        $client = new Client();

        $url = ($this->remotelabzProxyUseHttps ? 'https' : 'http').'://'.$this->remotelabzProxyServerAPI.':'.$this->remotelabzProxyApiPort.'/api/routes/device/'.$uuid;
        $this->logger->debug('Create route in proxy', [
            'url' => $url
        ]);

        $client->post($url, [
            'body' => json_encode([
                'target' => ($this->remotelabzProxyUseWss ? 'wss' : 'ws').'://'.$worker.':'.($remotePort + 1000).'',
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Create a new route in remotelabz-proxy service.
     * 
     * @param string $uuid UUID of the device instance
     * @param int $remotePort Port used by websockify
     */
    public function createContainerInstanceProxyRoute(string $uuid, int $remotePort, string $worker)
    {
        $client = new Client();

        $url = ($this->remotelabzProxyUseHttps ? 'https' : 'http').'://'.$this->remotelabzProxyServerAPI.':'.$this->remotelabzProxyApiPort.'/api/routes/device/'.$uuid;
        $this->logger->debug('Create route in proxy', [
            'url' => $url
        ]);

        $client->post($url, [
            'body' => json_encode([
                'target' => ($this->remotelabzProxyUseWss ? 'http' : 'http').'://'.$worker.':'.($remotePort).'',
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

        $url = ($this->remotelabzProxyUseHttps ? 'https' : 'http').'://'.$this->remotelabzProxyServerAPI.':'.$this->remotelabzProxyApiPort.'/api/routes';
        $response=$client->get($url);
        if (array_key_exists('/device/'.$uuid ,json_decode($response->getBody(),true))){
            //$this->logger->debug('device found in proxy:'.$response->getBody());
            $url = $url.'/device/'.$uuid;
            $this->logger->info('Delete route in proxy', [
                'url' => $url
            ]);
            $client->delete($url);
        }
    }
}