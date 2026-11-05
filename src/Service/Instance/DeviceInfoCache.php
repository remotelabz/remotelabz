<?php

namespace App\Service\Instance;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

/**
 * Cache Redis (best-effort) de la derniere IP connue d'une DeviceInstance.
 *
 * L'IP n'est pas persistee en base : elle est demandee au worker par message
 * (DeviceInfoRequestMessage / DeviceInfoMessage) puis mise en cache ici avec
 * une duree de vie. Une panne de Redis ne doit jamais casser le polling : on
 * journalise un avertissement et on renvoie null.
 *
 * Cles :
 *   - device_info.<uuid>         : ['ips' => [...], 'fetchedAt' => int, 'workerIp' => string]
 *   - device_info_pending.<uuid> : verrou anti-spam (requestId), TTL court
 */
class DeviceInfoCache
{
    private const PREFIX = 'device_info.';
    private const PENDING_PREFIX = 'device_info_pending.';

    /** Duree de vie d'une entree (secondes). */
    private const TTL = 43200; // 12 h

    /** Duree de vie du verrou anti-spam (secondes). */
    private const LOCK_TTL = 10;

    private AdapterInterface $pool;
    private LoggerInterface $logger;

    public function __construct(AdapterInterface $pool, LoggerInterface $logger)
    {
        $this->pool = $pool;
        $this->logger = $logger;
    }

    /**
     * @return array{ips: array, fetchedAt: int, workerIp: string}|null
     */
    public function get(string $deviceInstanceUuid): ?array
    {
        try {
            $item = $this->pool->getItem(self::PREFIX . $deviceInstanceUuid);
            if (!$item->isHit()) {
                return null;
            }

            $value = $item->get();

            return is_array($value) ? $value : null;
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::Cache d\'IP indisponible (best-effort, le polling continue) : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param string[] $deviceInstanceUuids
     *
     * @return array<string, array{ips: array, fetchedAt: int, workerIp: string}>
     */
    public function getMany(array $deviceInstanceUuids): array
    {
        $result = [];
        foreach ($deviceInstanceUuids as $uuid) {
            $value = $this->get($uuid);
            if ($value !== null) {
                $result[$uuid] = $value;
            }
        }

        return $result;
    }

    public function store(string $deviceInstanceUuid, array $info, string $workerIp): void
    {
        try {
            $item = $this->pool->getItem(self::PREFIX . $deviceInstanceUuid);
            $item->set([
                'ips' => $info['ips'] ?? [],
                'fetchedAt' => time(),
                'workerIp' => $workerIp,
            ]);
            $item->expiresAfter(self::TTL);
            $this->pool->save($item);
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::Ecriture du cache d\'IP impossible : ' . $e->getMessage());
        }
    }

    /**
     * Supprime l'entree (arret, suppression, erreur, demarrage).
     */
    public function forget(string $deviceInstanceUuid): void
    {
        try {
            $this->pool->deleteItem(self::PREFIX . $deviceInstanceUuid);
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::Suppression du cache d\'IP impossible : ' . $e->getMessage());
        }
    }

    /**
     * Pose un verrou anti-spam si aucune requete n'est deja en vol.
     *
     * @return string|null le requestId a corriger dans la reponse, ou null si
     *                     une requete est deja en vol (ne pas re-demander).
     */
    public function lockAndGetRequestId(string $deviceInstanceUuid): ?string
    {
        try {
            $key = self::PENDING_PREFIX . $deviceInstanceUuid;
            $item = $this->pool->getItem($key);
            if ($item->isHit()) {
                return null;
            }

            $requestId = bin2hex(random_bytes(16));
            $item->set($requestId);
            $item->expiresAfter(self::LOCK_TTL);
            $this->pool->save($item);

            return $requestId;
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::Pose du verrou d\'IP impossible : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Libere le verrou s'il correspond bien a ce requestId (evite qu'une reponse
     * tardive ne supprime le verrou d'une requete plus recente).
     */
    public function release(string $deviceInstanceUuid, string $requestId): void
    {
        try {
            $key = self::PENDING_PREFIX . $deviceInstanceUuid;
            $item = $this->pool->getItem($key);
            if ($item->isHit() && $item->get() === $requestId) {
                $this->pool->deleteItem($key);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::Liberation du verrou d\'IP impossible : ' . $e->getMessage());
        }
    }

    /**
     * Vrai si l'entree est absente ou plus vieille que $maxAge secondes.
     */
    public function isStale(string $deviceInstanceUuid, int $maxAge = 600): bool
    {
        $info = $this->get($deviceInstanceUuid);
        if ($info === null || !isset($info['fetchedAt'])) {
            return true;
        }

        return (time() - (int) $info['fetchedAt']) > $maxAge;
    }
}
