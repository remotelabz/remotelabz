<?php

namespace App\Service\System;

use Psr\Log\LoggerInterface;

/**
 * Provides the health status of the front server (the machine running this
 * application): status of the systemd units and hardware usage.
 *
 * Monitored units are all the unit files of the application (bin/systemd/)
 * plus the system units the application depends on. Units that do not exist
 * on the host (e.g. mercure not installed) are skipped.
 *
 * Returns the same data shape as the worker "api/systemd/status" and
 * "stats/hardware" endpoints, so the admin page can render both with the
 * same template code.
 */
class FrontendStatus
{
    /**
     * System units (not part of the application) the front server depends on.
     */
    private const SYSTEM_UNITS = [
        'apache2',
        'mysql',
    ];

    private const CPU_SAMPLE_DELAY_MICROSECONDS = 200000;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SystemdUnitClassifier $systemdUnitClassifier
    ) {
    }

    /**
     * Systemd status of the front server, same shape as a worker entry of
     * WorkerManager::checkWorkersSystemdStatusAction().
     */
    public function getStatus(): array
    {
        $services = [];
        $units = array_values(array_unique(array_merge(
            self::SYSTEM_UNITS,
            $this->systemdUnitClassifier->discoverUnits()
        )));
        foreach ($units as $unit) {
            $service = $this->getUnitStatus($unit);
            if (null === $service) {
                continue;
            }
            $services[] = $this->systemdUnitClassifier->classify($service);
        }
        $services = $this->systemdUnitClassifier->resolveOneshotPairs($services);
        $summary = $this->systemdUnitClassifier->summarize($services);

        return [
            'worker' => 'front',
            'label' => sprintf('Front (%s)', gethostname() ?: 'localhost'),
            'available' => true,
            'reachable' => true,
            'error' => null,
            'generated_at' => date('Y-m-d H:i:s'),
            'total' => $summary['total'],
            'running' => $summary['running'],
            'has_error' => $summary['has_error'],
            'services' => $services,
        ];
    }

    /**
     * Hardware usage of the front server, same shape as a worker entry of
     * WorkerManager::checkWorkersAction().
     */
    public function getUsage(): array
    {
        return [
            'worker' => 'front',
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'lxcfs' => null,
            'openedfiles' => null,
            'lxclsrun' => null,
            'qemurun' => null,
            'disk' => $this->getDiskUsage(),
        ];
    }

    private function getUnitStatus(string $unit): ?array
    {
        $output = @shell_exec(sprintf(
            'systemctl show --no-legend %s -p LoadState -p Description -p ActiveState -p SubState -p UnitFileState -p MainPID -p ActiveEnterTimestamp -p Result -p ExecMainStartTimestamp 2>/dev/null',
            escapeshellarg($unit)
        ));
        if (false === $output) {
            $this->logger->error('[FrontendStatus]::systemctl show failed for unit '.$unit);
            return null;
        }

        $props = [];
        foreach (explode("\n", trim((string) $output)) as $line) {
            $pos = strpos($line, '=');
            if (false !== $pos) {
                $props[substr($line, 0, $pos)] = substr($line, $pos + 1);
            }
        }

        // Unit does not exist on this host (e.g. optional service not installed)
        if ('not-found' === ($props['LoadState'] ?? 'not-found')) {
            return null;
        }

        $activeState = $props['ActiveState'] ?? 'inactive';

        return [
            'name' => $unit,
            'description' => $props['Description'] ?? $unit,
            'is_running' => 'active' === $activeState,
            'active_state' => $activeState,
            'sub_state' => $props['SubState'] ?? '',
            'unit_file_state' => $props['UnitFileState'] ?? 'unknown',
            'main_pid' => isset($props['MainPID']) && '0' !== $props['MainPID'] ? (int) $props['MainPID'] : null,
            'active_since' => ($props['ActiveEnterTimestamp'] ?? '') !== '' ? $props['ActiveEnterTimestamp'] : null,
            // Last run of the service ("Result" is "success" by default for
            // a unit that never ran: "last_run" is the execution proof)
            'last_result' => $props['Result'] ?? null,
            'last_run' => ($props['ExecMainStartTimestamp'] ?? '') !== '' ? $props['ExecMainStartTimestamp'] : null,
        ];
    }

    private function getCpuUsage(): ?int
    {
        [$idle1, $total1] = $this->readCpuTimes();
        if (null === $idle1 || null === $total1) {
            return null;
        }

        usleep(self::CPU_SAMPLE_DELAY_MICROSECONDS);

        [$idle2, $total2] = $this->readCpuTimes();
        $deltaTotal = $total2 - $total1;
        if ($deltaTotal <= 0) {
            return null;
        }

        return (int) round((($deltaTotal - ($idle2 - $idle1)) / $deltaTotal) * 100);
    }

    /**
     * @return array{0: int|null, 1: int|null} [idle, total] jiffies from /proc/stat
     */
    private function readCpuTimes(): array
    {
        $content = @file_get_contents('/proc/stat');
        if (false === $content) {
            return [null, null];
        }

        foreach (explode("\n", $content) as $line) {
            if (str_starts_with($line, 'cpu ')) {
                $fields = array_slice(preg_split('/\s+/', trim($line)), 1);
                $total = array_sum(array_map('intval', $fields));
                // idle + iowait
                $idle = (int) ($fields[3] ?? 0) + (int) ($fields[4] ?? 0);
                return [$idle, $total];
            }
        }

        return [null, null];
    }

    private function getMemoryUsage(): ?int
    {
        $content = @file_get_contents('/proc/meminfo');
        if (false === $content) {
            return null;
        }

        if (
            !preg_match('/^MemTotal:\s+(\d+)/m', $content, $totalMatch)
            || (int) $totalMatch[1] <= 0
            || !preg_match('/^MemAvailable:\s+(\d+)/m', $content, $availableMatch)
        ) {
            return null;
        }

        $total = (int) $totalMatch[1];
        $available = (int) $availableMatch[1];

        return (int) round(($total - $available) / $total * 100);
    }

    private function getDiskUsage(): array
    {
        $output = @shell_exec('df -P / 2>/dev/null');
        if (false === $output) {
            return [];
        }

        $lines = array_reverse(explode("\n", trim((string) $output)));
        $fields = preg_split('/\s+/', trim($lines[0]));
        $percent = $fields[4] ?? '';
        if (!is_numeric($percent = rtrim($percent, '%'))) {
            return [];
        }

        return ['/' => (int) $percent];
    }
}
