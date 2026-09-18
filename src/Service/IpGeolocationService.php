<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IpGeolocationService
{
    private HttpClientInterface $httpClient;
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl ?? 'http://ip-api.com';
        $this->httpClient = HttpClient::create([
            'timeout' => 3,
        ]);
    }

    public function getLocation(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'Unknown';
        }

        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/json/' . rawurlencode($ip) . '?fields=status,countryCode,country,regionName,city');
            $data = $response->toArray();
        } catch (\Throwable $e) {
            return 'Unknown';
        }

        if (($data['status'] ?? '') !== 'success' || empty($data['country'])) {
            return 'Unknown';
        }

        $parts = [];
        if (!empty($data['city'])) {
            $parts[] = $data['city'];
        }
        if (!empty($data['regionName'])) {
            $parts[] = $data['regionName'];
        }
        $parts[] = $data['country'];

        return implode(', ', array_filter($parts));
    }
}
