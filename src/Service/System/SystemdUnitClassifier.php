<?php

namespace App\Service\System;

/**
 * Interprets the runtime state of systemd units, using the unit files of a
 * given directory to know the semantics of each unit:
 *
 *   - long-running service (Type=simple, ...): must be active
 *   - oneshot service (Type=oneshot, no RemainAfterExit): runs on demand
 *     (via its timer), inactive is the normal state
 *   - timer: must be active (armed/waiting)
 *   - slice: groups lab processes, "inactive (dead)" just means it is empty
 *     (no lab running in it), "active (running)" means labs are inside
 *
 * Used by FrontendStatus (units of the front server, interpreted with the
 * local unit files of bin/systemd) and by WorkerManager (units of a remote
 * worker: the unit files are not available on the front, so the oneshot
 * nature and the last run come from the "type", "last_result" and "last_run"
 * fields returned by the worker "api/systemd/status" endpoint).
 */
class SystemdUnitClassifier
{
    public const KIND_SERVICE = 'service';
    public const KIND_TIMER = 'timer';
    public const KIND_SLICE = 'slice';
    public const KIND_OTHER = 'other';

    private const UNIT_KINDS = ['service', 'timer', 'slice', 'target'];

    /** @var array<string, array{type: string, oneshot: bool, remain_after_exit: bool}> */
    private array $units = [];

    private bool $loaded = false;

    public function __construct(
        private readonly string $unitFilesDir
    ) {
    }

    /**
     * Unit names (*.service, *.timer, *.slice, *.target) defined in the
     * unit files directory, sorted alphabetically.
     *
     * @return string[]
     */
    public function discoverUnits(): array
    {
        $units = [];
        foreach ($this->unitFiles() as $file) {
            $kind = $this->kindOf(basename($file));
            if (self::KIND_OTHER !== $kind) {
                $units[] = basename($file);
            }
        }
        sort($units);

        return $units;
    }

    /**
     * Adds interpretation keys (kind, expected_active, ok, kind_label) to a
     * unit status entry.
     *
     * The oneshot nature of a service is taken from the local unit files
     * when they are available, otherwise from the "type" field of the
     * entry (as returned by the worker "api/systemd/status" endpoint).
     */
    public function classify(array $service): array
    {
        $name = $service['name'] ?? '';
        $unitName = $this->normalizeUnitName($name);
        $kind = $this->kindOf($unitName);
        $info = $this->unitInfo($unitName);
        $oneshot = self::KIND_SERVICE === $kind
            && ($info['oneshot'] || 'oneshot' === ($service['type'] ?? ''))
            && !$info['remain_after_exit'];

        $activeState = $service['active_state'] ?? 'inactive';
        $active = 'active' === $activeState;
        $failed = 'failed' === $activeState || 'failed' === ($service['sub_state'] ?? '');

        // A slice is "active" only while it contains processes (labs):
        // being inactive is normal and is not an error. Same for oneshot
        // services, which only run on demand (triggered by their timer).
        $expectedActive = match (true) {
            self::KIND_SLICE === $kind => false,
            $oneshot => false,
            default => true,
        };

        $service['kind'] = $kind;
        $service['expected_active'] = $expectedActive;
        $service['ok'] = !$failed && (!$expectedActive || $active);
        $service['kind_label'] = match (true) {
            self::KIND_SLICE === $kind => 'slice',
            self::KIND_TIMER === $kind => 'timer',
            $oneshot => 'oneshot',
            default => null,
        };

        return $service;
    }

    /**
     * Evaluates the service+timer pairs of oneshot services.
     *
     * An oneshot service that is triggered by its own timer is displayed as
     * healthy ("pair_ok") when the timer is armed AND the service has run at
     * least once with a successful last result. Without "last_result"/
     * "last_run" in the data (e.g. remote worker API), the pair cannot be
     * proven and the service keeps its plain state.
     *
     * @return array the same services, each oneshot enriched with "pair_ok"
     */
    public function resolveOneshotPairs(array $services): array
    {
        $timers = [];
        foreach ($services as $service) {
            if (self::KIND_TIMER === ($service['kind'] ?? null)) {
                $timers[substr($service['name'], 0, -strlen('.timer'))] = $service;
            }
        }

        foreach ($services as $index => $service) {
            if ('oneshot' !== ($service['kind_label'] ?? null)) {
                continue;
            }
            $base = preg_replace('/\.(service|timer|slice|target)$/', '', $service['name']);
            $timer = $timers[$base] ?? null;
            if (null === $timer) {
                $services[$index]['pair_ok'] = false;
                continue;
            }
            $timerArmed = 'active' === ($timer['active_state'] ?? '');
            $hasRun = null !== ($service['last_run'] ?? null);
            $lastRunOk = 'success' === ($service['last_result'] ?? '');
            $services[$index]['pair_ok'] = $timerArmed && $hasRun && $lastRunOk;
        }

        return $services;
    }

    /**
     * Aggregates a list of classified unit statuses.
     *
     * total/running only count the units that are expected to stay active
     * (daemons, timers): slices and oneshots do not count, so an idle
     * worker (no lab) is reported as fully running.
     *
     * @return array{total: int, running: int, has_error: bool}
     */
    public function summarize(array $services): array
    {
        $total = 0;
        $running = 0;
        $hasError = false;
        foreach ($services as $service) {
            if (!($service['expected_active'] ?? true)) {
                continue;
            }
            $total++;
            if ('active' === ($service['active_state'] ?? '')) {
                $running++;
            }
        }
        foreach ($services as $service) {
            if (false === ($service['ok'] ?? true)) {
                $hasError = true;
            }
        }

        return ['total' => $total, 'running' => $running, 'has_error' => $hasError];
    }

    private function kindOf(string $unitName): string
    {
        $pos = strrpos($unitName, '.');
        if (false === $pos) {
            return self::KIND_OTHER;
        }
        $kind = substr($unitName, $pos + 1);

        return in_array($kind, self::UNIT_KINDS, true) ? $kind : self::KIND_OTHER;
    }

    private function normalizeUnitName(string $name): string
    {
        if (self::KIND_OTHER !== $this->kindOf($name)) {
            return $name;
        }

        return $name . '.service';
    }

    private function unitInfo(string $unitName): array
    {
        $this->load();

        return $this->units[$unitName] ?? ['type' => 'unknown', 'oneshot' => false, 'remain_after_exit' => false];
    }

    private function load(): void
    {
        $this->loaded = true;
        foreach ($this->unitFiles() as $file) {
            $section = null;
            $type = 'unknown';
            $remainAfterExit = false;
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ('' === $line || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                    continue;
                }
                if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
                    $section = $matches[1];
                    continue;
                }
                $pos = strpos($line, '=');
                if (false === $pos) {
                    continue;
                }
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));
                if ('Service' === $section) {
                    if ('Type' === $key) {
                        $type = $value;
                    } elseif ('RemainAfterExit' === $key) {
                        $remainAfterExit = 'yes' === $value;
                    }
                }
            }
            $this->units[basename($file)] = [
                'type' => $type,
                'oneshot' => 'oneshot' === $type,
                'remain_after_exit' => $remainAfterExit,
            ];
        }
    }

    /**
     * @return string[]
     */
    private function unitFiles(): array
    {
        return glob($this->unitFilesDir . '/*') ?: [];
    }
}
