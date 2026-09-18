<?php

namespace App\Service\Worker;

use Symfony\Component\Cache\Adapter\AdapterInterface;

/**
 * Tracks the memory reservations of lab instances that have been created
 * but are not yet fully running on a worker.
 *
 * The cache bridges the gap between:
 *   - the creation of a LabInstance (needed memory is known in advance,
 *     worker not chosen yet), and
 *   - the worker actually running the lab (its real memory usage then
 *     appears in the worker hardware stats).
 *
 * While a lab instance is in that gap, its memory is "reserved" so that
 * WorkerManager::getFreeWorker() does not pile up instances on the same
 * worker (race condition when many instances of a lab are created at once,
 * e.g. during a scheduled group start).
 *
 * Entry format (single aggregate cache key):
 *   [
 *       '<labInstanceUuid>' => ['memory' => int, 'workerIp' => ?string, 'createdAt' => int],
 *       ...
 *   ]
 */
class LabPlacementCache
{
    private const KEY = 'lab_placement.pending';

    private const TTL = 3600;

    private AdapterInterface $pool;

    public function __construct(AdapterInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @return array<string, array{memory: int, workerIp: ?string, createdAt: int}>
     */
    public function getPending(): array
    {
        $item = $this->pool->getItem(self::KEY);
        if (!$item->isHit()) {
            return [];
        }

        return is_array($item->get()) ? $item->get() : [];
    }

    /**
     * Records a new pending lab instance (worker not chosen yet).
     * No-op if the lab instance is already tracked (idempotent).
     */
    public function add(string $labInstanceUuid, int $memory): void
    {
        $pending = $this->getPending();
        if (isset($pending[$labInstanceUuid])) {
            return;
        }

        $pending[$labInstanceUuid] = [
            'memory'    => (int) $memory,
            'workerIp'  => null,
            'createdAt' => time(),
        ];
        $this->save($pending);
    }

    /**
     * Records the worker chosen for a pending lab instance.
     */
    public function assign(string $labInstanceUuid, string $workerIp): void
    {
        $pending = $this->getPending();
        if (!isset($pending[$labInstanceUuid])) {
            return;
        }

        $pending[$labInstanceUuid]['workerIp'] = $workerIp;
        $this->save($pending);
    }

    /**
     * Forgets a lab instance (created on its worker, deleted, or failed).
     */
    public function remove(string $labInstanceUuid): void
    {
        $pending = $this->getPending();
        if (!isset($pending[$labInstanceUuid])) {
            return;
        }

        unset($pending[$labInstanceUuid]);
        $this->save($pending);
    }

    /**
     * Sum (same unit as worker stats "memory_total") of the reserved memory
     * already assigned to the given worker.
     */
    public function memoryAssignedTo(string $workerIp): int
    {
        $total = 0;
        foreach ($this->getPending() as $entry) {
            if (($entry['workerIp'] ?? null) === $workerIp) {
                $total += (int) $entry['memory'];
            }
        }

        return $total;
    }

    private function save(array $pending): void
    {
        $item = $this->pool->getItem(self::KEY);
        $item->set($pending);
        $item->expiresAfter(self::TTL);
        $this->pool->save($item);
    }
}
