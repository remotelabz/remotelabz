<?php

namespace App\Tests\Service\Worker;

use App\Entity\Device;
use App\Entity\Flavor;
use App\Entity\Lab;
use App\Repository\ConfigWorkerRepository;
use App\Service\System\SystemdUnitClassifier;
use App\Service\Worker\LabPlacementCache;
use App\Service\Worker\WorkerManager;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Reproduces the placement of a batch of lab instances to check that they are
 * spread over every worker instead of piling up on the same one.
 *
 * The sequence replayed here is the one used in production:
 *
 *   InstanceManager::create()            -> LabPlacementCache::add(uuid, memory)
 *   LabLaunchRequestMessageHandler       -> WorkerManager::getFreeWorker(lab)
 *                                           LabPlacementCache::assign(uuid, worker)
 *   InstanceStateMessageHandler          -> LabPlacementCache::remove(uuid)
 *                                           (instance reached its final state)
 *
 * Worker stats are identical for every worker (and stale, as they are in
 * production: they never reflect the instances that are created but not yet
 * running), so the only thing that can make getFreeWorker() return a
 * different worker for each call is the reservation held in the cache.
 */
class WorkerManagerPlacementTest extends TestCase
{
    /** Real value returned by /stats/hardwarelight on a worker (MB). */
    private const MEMORY_TOTAL = 3955.328;

    private const WORKERS = ['10.0.0.1', '10.0.0.2', '10.0.0.3'];

    private LabPlacementCache $placementCache;

    private Lab $lab;

    protected function setUp(): void
    {
        $this->placementCache = new LabPlacementCache(new ArrayAdapter(), new NullLogger());
        $this->lab = $this->createLab(1000);
    }

    public function testWithoutReservationsEveryLabGoesToTheSameWorker(): void
    {
        $workerManager = $this->createWorkerManager($this->usages());

        $placements = [];
        for ($i = 0; $i < 6; ++$i) {
            $placements[] = $workerManager->getFreeWorker($this->lab);
        }

        // Race condition as it happened before LabPlacementCache: the stats
        // returned by each worker are the same for every call, so the very
        // same (first) worker wins every time.
        $this->assertSame(array_fill(0, 6, '10.0.0.1'), $placements);
    }

    public function testBatchOfLabsIsSpreadOverAllWorkers(): void
    {
        $workerManager = $this->createWorkerManager($this->usages());

        $placements = [];
        for ($i = 0; $i < 6; ++$i) {
            $placements[] = $this->place($workerManager, 'batch-'.$i);
        }

        $counts = array_count_values($placements);

        // Every worker gets its share of the batch...
        foreach (self::WORKERS as $workerIp) {
            $this->assertArrayHasKey($workerIp, $counts, 'Worker '.$workerIp.' never received any lab');
        }

        // ...and the batch is evenly balanced (here 2 labs per worker).
        $this->assertSame([2, 2, 2], $this->sorted($counts));

        // No worker is over-subscribed: what is reserved on it fits in its RAM.
        foreach (self::WORKERS as $workerIp) {
            $this->assertLessThanOrEqual(
                self::MEMORY_TOTAL,
                $this->placementCache->memoryAssignedTo($workerIp),
                'Worker '.$workerIp.' has more memory reserved than it owns'
            );
        }
    }

    public function testReservationExcludesAWorkerThatStillLooksIdle(): void
    {
        // 10.0.0.1 looks idle (0% of memory used) but two instances of 2000 MB
        // are already placed on it and are not running yet: their memory is
        // only known through the placement cache.
        $this->placementCache->add('already-1', 2000);
        $this->placementCache->assign('already-1', '10.0.0.1');
        $this->placementCache->add('already-2', 2000);
        $this->placementCache->assign('already-2', '10.0.0.1');

        $workerManager = $this->createWorkerManager([
            $this->usage('10.0.0.1', memory: 0),
            $this->usage('10.0.0.2', memory: 60),
        ]);

        // Without the reservations, 10.0.0.1 (0% used) would win easily.
        $this->assertSame('10.0.0.2', $workerManager->getFreeWorker($this->lab));
    }

    public function testNoWorkerIsSelectedWhenEveryWorkerIsSaturated(): void
    {
        $this->placementCache->add('sat-1', 4000);
        $this->placementCache->assign('sat-1', '10.0.0.1');
        $this->placementCache->add('sat-2', 400);
        $this->placementCache->assign('sat-2', '10.0.0.2');

        $workerManager = $this->createWorkerManager([
            // 4000 MB reserved = 101% of MEMORY_TOTAL
            $this->usage('10.0.0.1', memory: 0),
            // 99% used + 400 MB reserved = 109% of MEMORY_TOTAL
            $this->usage('10.0.0.2', memory: 99),
        ]);

        // getFreeWorker() returns '' and LabLaunchRequestMessageHandler puts
        // the instance in error instead of overloading a worker.
        $this->assertSame('', $workerManager->getFreeWorker($this->lab));
    }

    public function testBatchStopsOnceEveryWorkerIsFull(): void
    {
        $workerManager = $this->createWorkerManager($this->usages());

        $placements = [];
        for ($i = 0; $i < 13; ++$i) {
            $uuid = 'full-'.$i;
            $this->placementCache->add($uuid, 1000);
            $worker = $workerManager->getFreeWorker($this->lab);
            if ('' === $worker) {
                $placements[] = $worker;
                break;
            }
            $this->placementCache->assign($uuid, $worker);
            $placements[] = $worker;
        }

        // 3 workers of 3955 MB: the 13th lab cannot be placed anymore because
        // every worker is saturated (real usage + reservations). getFreeWorker()
        // returns '' and LabLaunchRequestMessageHandler puts the instance in
        // error instead of piling it up on an overloaded worker.
        $this->assertCount(13, $placements);
        $this->assertSame('', $placements[12]);
        $this->assertSame([4, 4, 4], $this->sorted(array_count_values(array_slice($placements, 0, 12))));
    }

    public function testReservationIsReleasedWhenTheInstanceReachesItsFinalState(): void
    {
        $workerManager = $this->createWorkerManager($this->usages());

        foreach (self::WORKERS as $index => $workerIp) {
            $this->assertSame($workerIp, $this->place($workerManager, 'final-'.$index));
        }

        // InstanceStateMessageHandler::remove() on STATE_CREATED/DELETED/ERROR
        foreach (self::WORKERS as $index => $workerIp) {
            $this->placementCache->remove('final-'.$index);
            $this->assertSame(0, $this->placementCache->memoryAssignedTo($workerIp));
        }

        // The first worker is the best candidate again.
        $this->assertSame('10.0.0.1', $workerManager->getFreeWorker($this->lab));
    }

    public function testOnlyAssignedReservationsAreCountedByGetFreeWorker(): void
    {
        // add() alone: the instance exists but no worker was chosen yet, so
        // the reservation cannot be attributed to any worker.
        $this->placementCache->add('pending', 1000);
        $this->assertSame(0, $this->placementCache->memoryAssignedTo('10.0.0.1'));

        $this->placementCache->assign('pending', '10.0.0.1');
        $this->assertSame(1000, $this->placementCache->memoryAssignedTo('10.0.0.1'));
    }

    /**
     * Runs the production sequence: reserve, place, attribute.
     */
    private function place(WorkerManager $workerManager, string $uuid): string
    {
        $memory = $workerManager->computeMemoryUsage($this->lab);

        $this->placementCache->add($uuid, $memory);
        $worker = $workerManager->getFreeWorker($this->lab);
        $this->placementCache->assign($uuid, $worker);

        return $worker;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function usages(): array
    {
        return array_map(fn (string $ip) => $this->usage($ip), self::WORKERS);
    }

    /**
     * Stats as returned by GET /stats/hardwarelight on a worker.
     *
     * @return array<string, mixed>
     */
    private function usage(string $workerIp, float $memory = 10, float $cpu = 2, float $disk = 1, float $lxcfs = 0): array
    {
        return [
            'cpu' => $cpu,
            'disk' => ['rlz-vg' => $disk, 'ubuntu-vg' => 75],
            'lxcfs' => $lxcfs,
            'memory' => $memory,
            'memory_total' => self::MEMORY_TOTAL,
            'worker' => $workerIp,
        ];
    }

    private function createLab(int $memory): Lab
    {
        $device = (new Device())->setFlavor((new Flavor())->setMemory($memory));

        $lab = new Lab();
        $lab->addDevice($device);

        return $lab;
    }

    /**
     * WorkerManager whose worker stats are the given ones: only the placement
     * decision (getFreeWorker) is under test, not the HTTP polling of the
     * workers.
     *
     * @param array<int, array<string, mixed>> $usages
     */
    private function createWorkerManager(array $usages): WorkerManager
    {
        $logger = new NullLogger();

        return new class($usages, $this->placementCache, $logger, $this->createMock(ClientInterface::class), $this->createMock(ConfigWorkerRepository::class), $this->createMock(ManagerRegistry::class)) extends WorkerManager {
            /**
             * @param array<int, array<string, mixed>> $usages
             */
            public function __construct(
                private readonly array $usages,
                LabPlacementCache $placementCache,
                LoggerInterface $logger,
                ClientInterface $client,
                ConfigWorkerRepository $configWorkerRepository,
                ManagerRegistry $doctrine,
            ) {
                parent::__construct(
                    '127.0.0.1',
                    '127.0.0.1',
                    '5000',
                    $logger,
                    $client,
                    $configWorkerRepository,
                    $doctrine,
                    $placementCache,
                    new SystemdUnitClassifier(__DIR__)
                );
            }

            public function checkWorkersLightAction()
            {
                return $this->usages;
            }
        };
    }

    /**
     * @param array<string, int> $counts
     *
     * @return int[]
     */
    private function sorted(array $counts): array
    {
        $values = array_values($counts);
        sort($values);

        return $values;
    }
}
