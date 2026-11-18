<?php

namespace App\Tests\Service\Network;

use App\Repository\NetworkRepository;
use App\Service\Network\NetworkManager;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Remotelabz\NetworkBundle\Entity\Network;
use Remotelabz\NetworkBundle\Exception\NoNetworkAvailableException;

/**
 * The subnet allocated to a new lab instance must never be allocated to two
 * instances at the same time: with two concurrent InstanceManager::create()
 * calls, the allocation used to be a plain table read followed by a flush,
 * so both processes could pick the very same /28 (the kernel route
 * `<CIDR> via <worker>` is keyed by the CIDR only, so one of the two labs
 * would end up routed to the other's worker).
 *
 * The fix: getAvailableSubnet() persists+flushes the chosen /28 right away
 * (immediate, committed reservation) and retries once the DBAL unique
 * constraint violation is raised by a concurrent allocation.
 */
class NetworkManagerTest extends TestCase
{
    private const BASE_NETWORK = '10.11.0.0';
    private const BASE_NETMASK = '255.255.0.0';
    private const LAB_NETMASK = '255.255.255.240';

    private function createManager(
        array &$reserved,
        EntityManagerInterface $em,
        string $baseNetwork = self::BASE_NETWORK,
        string $baseNetmask = self::BASE_NETMASK,
        string $labNetmask = self::LAB_NETMASK
    ): NetworkManager {
        $repository = $this->createMock(NetworkRepository::class);
        $repository->method('findAll')->willReturnCallback(
            static function () use (&$reserved): array {
                return $reserved;
            }
        );

        return new NetworkManager(
            $baseNetwork,
            $baseNetmask,
            $labNetmask,
            $repository,
            $em
        );
    }

    private function createUniqueViolation(): UniqueConstraintViolationException
    {
        $driverException = new class(
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry'
        ) extends \Exception implements DriverException {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };

        return new UniqueConstraintViolationException($driverException, null);
    }

    public function testAllocatesFirstFreeSubnetAndReservesItInDatabase(): void
    {
        $reserved = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(Network::class));
        $em->expects($this->once())->method('flush');

        $manager = $this->createManager($reserved, $em);
        $network = $manager->getAvailableSubnet();

        $this->assertSame('10.11.0.0/28', (string) $network);
    }

    public function testAllocatesNextFreeSubnetWhenTheFirstOneIsReserved(): void
    {
        $reserved = [new Network('10.11.0.0', self::LAB_NETMASK)];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $manager = $this->createManager($reserved, $em);
        $network = $manager->getAvailableSubnet();

        $this->assertSame('10.11.0.0/28', (string) $reserved[0]);
        $this->assertSame('10.11.0.16/28', (string) $network);
    }

    public function testReturnsNullWithoutPersistingWhenTheBaseNetworkIsExhausted(): void
    {
        // Base 10.11.0.0/29 holds exactly two /30 subnets; both are reserved.
        $reserved = [
            new Network('10.11.0.0', '255.255.255.252'),
            new Network('10.11.0.4', '255.255.255.252'),
        ];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $manager = $this->createManager($reserved, $em, '10.11.0.0', '255.255.255.248', '255.255.255.252');

        $this->assertNull($manager->getAvailableSubnet());
    }

    public function testScanStopsAtTheBaseNetworkBoundary(): void
    {
        // Base 10.11.0.0/29 holds exactly two /30 subnets. The first is
        // reserved, so only the last /30 of the base is free. The scan must
        // return it and stop, not walk on to 10.11.0.8/30 (outside the base
        // network) the way the old unbounded loop used to.
        $reserved = [new Network('10.11.0.0', '255.255.255.252')];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $manager = $this->createManager($reserved, $em, '10.11.0.0', '255.255.255.248', '255.255.255.252');
        $network = $manager->getAvailableSubnet();

        $this->assertSame('10.11.0.4/30', (string) $network);
    }

    public function testConcurrentAllocationConflictIsRetriedOnTheNextSubnet(): void
    {
        // Process A and this manager read the table at the same time and
        // both picked 10.11.0.0/28. Process A wins the race: its INSERT is
        // committed, this manager's INSERT hits the unique index. The
        // manager must un-schedule the failed entity, re-read the table
        // (process A's row is now visible) and allocate the next /28.
        $reserved = [];
        $conflict = $this->createUniqueViolation();
        $flushCount = 0;

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('flush')->willReturnCallback(
            static function () use (&$reserved, &$flushCount, $conflict): void {
                $flushCount++;

                if ($flushCount === 1) {
                    // The winner's INSERT is committed before the loser's
                    // one fails.
                    $reserved[] = new Network('10.11.0.0', self::LAB_NETMASK);

                    throw $conflict;
                }
            }
        );
        $em->expects($this->once())->method('remove')
            ->with($this->isInstanceOf(Network::class));

        $manager = $this->createManager($reserved, $em);
        $network = $manager->getAvailableSubnet();

        $this->assertSame('10.11.0.16/28', (string) $network);
    }

    public function testFailsAfterMaxAttemptsWhenConflictsKeepHappening(): void
    {
        $reserved = [];
        $conflict = $this->createUniqueViolation();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')->willThrowException($conflict);
        $em->expects($this->exactly(3))->method('remove')
            ->with($this->isInstanceOf(Network::class));

        $manager = $this->createManager($reserved, $em);

        $this->expectException(NoNetworkAvailableException::class);

        $manager->getAvailableSubnet();
    }
}
