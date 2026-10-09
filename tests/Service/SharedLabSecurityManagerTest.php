<?php

namespace App\Tests\Service;

use App\Entity\DeviceInstance;
use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\LabInstance;
use App\Entity\LabShare;
use App\Instance\InstanceState;
use App\Repository\ConfigWorkerRepository;
use App\Repository\LabShareRepository;
use App\Service\SharedLabSecurityManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Remotelabz\NetworkBundle\Entity\Network;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Unit tests of the shared lab topology (shared-labs-front-plan.md §3 and §9.1).
 */
class SharedLabSecurityManagerTest extends TestCase
{
    private Group $group;

    private Lab $sourceLab;

    private Lab $targetLab;

    protected function setUp(): void
    {
        $this->group = new Group();
        $this->sourceLab = new Lab();
        $this->sourceLab->setName('Shared lab');
        $this->targetLab = new Lab();
        $this->targetLab->setName('Target lab');
    }

    public function testBuildLinksIsCartesianProductOfInstances()
    {
        $sourceA = $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.0.0/24');
        $sourceB = $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.1.0/24');
        $target = $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group
        );

        $topology = $manager->buildLinks($this->group);

        $this->assertEquals($this->group->getUuid(), $topology['group']);
        $this->assertCount(2, $topology['links']);

        $linkedPairs = [];
        foreach ($topology['links'] as $link) {
            $this->assertArrayHasKey('a', $link);
            $this->assertArrayHasKey('b', $link);
            $this->assertNotEquals($link['a']['uuid'], $link['b']['uuid']);
            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+\.\d+\/\d+$/', $link['a']['network']);
            $this->assertNotEmpty($link['a']['workerIp']);

            $linkedPairs[] = $this->sortedPair($link['a'], $link['b']);
        }

        $expected = [
            $this->sortedPair(['uuid' => $sourceA->getUuid()], ['uuid' => $target->getUuid()]),
            $this->sortedPair(['uuid' => $sourceB->getUuid()], ['uuid' => $target->getUuid()]),
        ];
        sort($expected);
        sort($linkedPairs);
        $this->assertEquals($expected, $linkedPairs);
    }

    public function testNoRuleNoLink()
    {
        $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.0.0/24');
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager([], $this->group);

        $this->assertEquals([], $manager->buildLinks($this->group)['links']);
    }

    public function testUnplacedInstancesAreExcluded()
    {
        $this->createGroupInstance($this->group, $this->sourceLab, null, '10.11.0.0/24');
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group
        );

        $this->assertEquals([], $manager->buildLinks($this->group)['links']);
    }

    public function testStoppedInstancesAreExcluded()
    {
        $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.0.0/24', [InstanceState::STOPPED]);
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group
        );

        $this->assertEquals([], $manager->buildLinks($this->group)['links']);
    }

    public function testStartingInstancesAreIncluded()
    {
        $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.0.0/24', [InstanceState::STARTING]);
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group
        );

        $this->assertCount(1, $manager->buildLinks($this->group)['links']);
    }

    public function testPhysicalLabsAreExcluded()
    {
        $this->sourceLab->setVirtuality(0);
        $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.0.0/24');
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group
        );

        $this->assertEquals([], $manager->buildLinks($this->group)['links']);
    }

    public function testUserOwnedInstancesAreExcluded()
    {
        $instance = $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.0.0/24');
        $instance->setOwnedBy(LabInstance::OWNED_BY_USER);
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group
        );

        $this->assertEquals([], $manager->buildLinks($this->group)['links']);
    }

    public function testLinksAreDeduplicatedAndNeverLinkAnInstanceToItself()
    {
        $source = $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.0.0/24');
        $target = $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager(
            [
                $this->createShare($this->sourceLab, $this->targetLab, $this->group),
                $this->createShare($this->targetLab, $this->sourceLab, $this->group),
                $this->createShare($this->sourceLab, $this->sourceLab, $this->group),
            ],
            $this->group
        );

        $links = $manager->buildLinks($this->group)['links'];

        $this->assertCount(1, $links);
        $this->assertEquals(
            $this->sortedPair(['uuid' => $source->getUuid()], ['uuid' => $target->getUuid()]),
            $this->sortedPair($links[0]['a'], $links[0]['b'])
        );
    }

    public function testGroupIsolation()
    {
        $otherGroup = new Group();

        $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.0.0/24');
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');
        $this->createGroupInstance($otherGroup, $this->targetLab, '192.168.11.133', '10.11.3.0/24');

        // One rule (source, target, $this->group) only: nothing for $otherGroup
        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group
        );

        $this->assertCount(1, $manager->buildLinks($this->group)['links']);
        $this->assertEquals([], $manager->buildLinks($otherGroup)['links']);
    }

    private function createManager(array $shares, ?Group $group = null): SharedLabSecurityManager
    {
        $labShareRepository = $this->createMock(LabShareRepository::class);
        $labShareRepository->method('findBy')->willReturnCallback(function (array $criteria) use ($shares, $group) {
            $criteriaGroup = $criteria['group'] ?? null;

            return (null === $group || $criteriaGroup === $group) ? $shares : [];
        });

        $configWorkerRepository = $this->createMock(ConfigWorkerRepository::class);

        return new SharedLabSecurityManager(
            $labShareRepository,
            $configWorkerRepository,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    private function createShare(Lab $lab, Lab $sharedWith, Group $group): LabShare
    {
        $share = new LabShare();
        $share->setLab($lab)->setSharedWith($sharedWith)->setGroup($group);

        return $share;
    }

    private function createGroupInstance(Group $group, Lab $lab, ?string $workerIp, string $network, array $deviceStates = [InstanceState::STARTED]): LabInstance
    {
        $ip = explode('/', $network)[0];

        $labInstance = new LabInstance();
        $labInstance->setLab($lab);
        $labInstance->setGroup($group);
        $labInstance->setOwnedBy(LabInstance::OWNED_BY_GROUP);
        $labInstance->setWorkerIp($workerIp);
        $labInstance->setNetwork(new Network($ip, '255.255.255.0'));

        foreach ($deviceStates as $state) {
            $deviceInstance = new DeviceInstance();
            $deviceInstance->setState($state);
            $labInstance->addDeviceInstance($deviceInstance);
        }

        $group->addLabInstance($labInstance);

        return $labInstance;
    }

    private function sortedPair(array $a, array $b): array
    {
        $uuids = [$a['uuid'], $b['uuid']];
        sort($uuids);

        return $uuids;
    }
}
