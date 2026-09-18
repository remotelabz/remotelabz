<?php

namespace App\Service;

use App\Entity\IpReputation;
use App\Repository\IpReputationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IpReputationService
{
    private EntityManagerInterface $entityManager;
    private IpReputationRepository $repository;
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private int $ttl;
    private string $apiUrl;
    private string $checkUrlBase;

    public function __construct(
        EntityManagerInterface $entityManager,
        IpReputationRepository $repository,
        #[\SensitiveParameter] string $apiKey = '',
        int $ttl = 86400,
        string $apiUrl = 'https://api.abuseipdb.com/api/v2/check',
        string $checkUrlBase = 'https://www.abuseipdb.com/check'
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->apiKey = $apiKey;
        $this->ttl = $ttl;
        $this->apiUrl = $apiUrl;
        $this->checkUrlBase = rtrim($checkUrlBase, '/');

        $this->httpClient = HttpClient::create([
            'timeout' => 5,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'RemoteLabz/1.0',
            ],
        ]);
    }

    /**
     * Retourne la réputation d'une IP, en la (ré)interrogeant si nécessaire.
     * Ne lève jamais d'exception : en cas d'échec, la dernière valeur connue est
     * retournée (ou null si aucune).
     */
    public function getReputation(string $ip): ?IpReputation
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        try {
            $reputation = $this->repository->findOneBy(['ip' => $ip]);

            if ($reputation && !$this->isStale($reputation)) {
                return $reputation;
            }

            $data = $this->fetchFromAbuseipdb($ip);

            if (!$reputation) {
                $reputation = new IpReputation();
                $reputation->setIp($ip);
                $reputation->setCreatedAt(new \DateTime());
                $this->entityManager->persist($reputation);
            }

            $this->applyData($reputation, $data);
            $this->entityManager->flush();

            return $reputation;
        } catch (\Throwable $e) {
            error_log('Failed to update IP reputation for ' . $ip . ': ' . $e->getMessage());
            return $this->repository->findOneBy(['ip' => $ip]);
        }
    }

    public function getCheckUrl(string $ip): string
    {
        return $this->checkUrlBase . '/' . rawurlencode($ip);
    }

    public function getCheckUrlBase(): string
    {
        return $this->checkUrlBase;
    }

    private function isStale(IpReputation $reputation): bool
    {
        $lastChecked = $reputation->getLastCheckedAt();
        if (!$lastChecked) {
            return true;
        }

        return (time() - $lastChecked->getTimestamp()) > $this->ttl;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromAbuseipdb(string $ip): ?array
    {
        if ($this->apiKey === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiUrl, [
                'query' => [
                    'ipAddress' => $ip,
                    'maxAgeInDays' => 90,
                ],
                'headers' => [
                    'Key' => $this->apiKey,
                ],
            ]);

            $payload = $response->toArray(false);
        } catch (\Throwable $e) {
            error_log('AbuseIPDB request failed for ' . $ip . ': ' . $e->getMessage());
            return null;
        }

        if (!isset($payload['data']) || !is_array($payload['data'])) {
            return null;
        }

        return $payload['data'];
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function applyData(IpReputation $reputation, ?array $data): void
    {
        $reputation->setLastCheckedAt(new \DateTime());

        if (!$data) {
            return;
        }

        if (isset($data['abuseConfidenceScore']) && is_numeric($data['abuseConfidenceScore'])) {
            $reputation->setAbuseScore((int) $data['abuseConfidenceScore']);
        }

        if (isset($data['totalReports']) && is_numeric($data['totalReports'])) {
            $reputation->setTotalReports((int) $data['totalReports']);
        }

        if (!empty($data['countryCode'])) {
            $reputation->setCountryCode((string) $data['countryCode']);
        }
    }
}
