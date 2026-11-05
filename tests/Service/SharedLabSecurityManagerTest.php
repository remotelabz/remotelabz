<?php

namespace App\Tests\Service;

use App\Entity\ConfigWorker;
use App\Entity\DeviceInstance;
use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\LabInstance;
use App\Entity\LabShare;
use App\Entity\User;
use App\Instance\InstanceState;
use App\Repository\ConfigWorkerRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\LabShareRepository;
use App\Service\SharedLabSecurityManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\SecurityMessage;
use Remotelabz\NetworkBundle\Entity\Network;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Unit tests of the shared lab topology (shared-labs-front-plan.md §3 and §9.1).
 */
class SharedLabSecurityManagerTest extends TestCase
{
    private Group $group;

    private Lab $sourceLab;

    private Lab $targetLab;

    /** @var LabInstance[] */
    private array $userInstances = [];

    /** @var Group[] */
    private array $groupsWithLab = [];

    protected function setUp(): void
    {
        $this->group = new Group();
        $this->sourceLab = new Lab();
        $this->sourceLab->setName('Shared lab');
        $this->targetLab = new Lab();
        $this->targetLab->setName('Target lab');
        $this->userInstances = [];
        $this->groupsWithLab = [];
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

    public function testUserOwnedInstancesOfNonMembersAreExcluded()
    {
        // The instance is launched by a user who belongs to no group of the lab
        $this->createUserInstance($this->group, $this->sourceLab, new User(), '192.168.11.132', '10.11.0.0/24');
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group
        );

        $this->assertEquals([], $manager->buildLinks($this->group)['links']);
    }

    public function testUserOwnedInstancesOfMembersAreIncluded()
    {
        // The instance is launched by a member of the group: the lab is shared
        // with the group even though the instance is a sandbox one
        $member = new User();
        $this->group->addUser($member);

        $this->createUserInstance($this->group, $this->sourceLab, $member, '192.168.11.132', '10.11.0.0/24');
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group
        );

        $this->assertCount(1, $manager->buildLinks($this->group)['links']);
    }

    public function testUserOwnedInstanceIsLinkedOnlyInTheGroupsOfItsOwner()
    {
        $memberOfBoth = new User();
        $this->group->addUser($memberOfBoth);
        $otherGroup = new Group();
        $otherGroup->addUser($memberOfBoth);

        $this->createUserInstance($this->group, $this->sourceLab, $memberOfBoth, '192.168.11.132', '10.11.0.0/24');
        $this->createUserInstance($this->group, $this->targetLab, $memberOfBoth, '192.168.11.133', '10.11.2.0/24');

        // The owner is a member of both groups and both groups define the rule:
        // the same sandbox instances take part in both topologies
        $manager = $this->createManager(
            [
                $this->createShare($this->sourceLab, $this->targetLab, $this->group),
                $this->createShare($this->sourceLab, $this->targetLab, $otherGroup),
            ],
            null
        );

        $this->assertCount(1, $manager->buildLinks($this->group)['links']);
        $this->assertCount(1, $manager->buildLinks($otherGroup)['links']);

        // A user who is a member of the first group only never appears in the
        // topology of the second one
        $stranger = new User();
        $this->group->addUser($stranger);
        $this->createUserInstance($this->group, $this->sourceLab, $stranger, '192.168.11.134', '10.11.3.0/24');

        $this->assertCount(2, $manager->buildLinks($this->group)['links']);
        $this->assertCount(1, $manager->buildLinks($otherGroup)['links']);
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

    public function testSyncGroupLogsTheContentOfEveryDispatchedMessage()
    {
        $this->createGroupInstance($this->group, $this->sourceLab, '192.168.11.132', '10.11.0.0/24');
        $this->createGroupInstance($this->group, $this->targetLab, '192.168.11.133', '10.11.2.0/24');

        $handler = new TestHandler();
        $bus = $this->createMock(MessageBusInterface::class);
        $dispatchedContent = null;
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps = []) use (&$dispatchedContent) {
                $this->assertInstanceOf(SecurityMessage::class, $message);
                $this->assertSame($this->group->getUuid(), $message->getGroup());
                $this->assertInstanceOf(AmqpStamp::class, $stamps[0] ?? null);
                $this->assertSame('192.0.2.10', $stamps[0]->getRoutingKey());
                $dispatchedContent = $message->getContent();

                return new Envelope($message);
            });

        $manager = $this->createManager(
            [$this->createShare($this->sourceLab, $this->targetLab, $this->group)],
            $this->group,
            [$this->createWorker('192.0.2.10', true), $this->createWorker('192.0.2.11', false)],
            new Logger('test', [$handler]),
            $bus
        );

        $manager->syncGroup($this->group);

        // One message for the available worker only, plus the summary: both
        // carry the content of the message
        $records = $handler->getRecords();
        $this->assertCount(2, $records);
        $this->assertSame(Logger::INFO, $records[0]['level']);
        $this->assertStringContainsString('SecurityMessage sent to worker 192.0.2.10', $records[0]['message']);
        $this->assertNotNull($dispatchedContent);
        $this->assertStringContainsString($dispatchedContent, $records[0]['message']);
        $this->assertStringContainsString('with 1 link(s)', $records[0]['message']);

        $this->assertSame(Logger::INFO, $records[1]['level']);
        $this->assertStringContainsString('sent to 1 worker(s)', $records[1]['message']);
    }

    public function testSyncGroupWarnsWhenNoWorkerIsAvailable()
    {
        $handler = new TestHandler();
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $manager = $this->createManager(
            [],
            $this->group,
            [$this->createWorker('192.0.2.11', false)],
            new Logger('test', [$handler]),
            $bus
        );

        $manager->syncGroup($this->group);

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(Logger::WARNING, $records[0]['level']);
        $this->assertStringContainsString('No available worker', $records[0]['message']);
    }

    public function testSyncLabInstanceBroadcastsEveryGroupWhereTheLabIsShared()
    {
        $member = new User();
        $this->group->addUser($member);
        $instance = $this->createUserInstance($this->group, $this->sourceLab, $member, '192.168.11.132', '10.11.0.0/24');

        $otherGroup = new Group();
        $this->groupsWithLab = [$this->group, $otherGroup];

        $handler = new TestHandler();
        $manager = $this->createManager([], null, [], new Logger('test', [$handler]));

        $manager->syncLabInstance($instance);

        // One message per group, none of them has an available worker here
        $records = $handler->getRecords();
        $this->assertCount(2, $records);
        foreach ($records as $record) {
            $this->assertSame(Logger::WARNING, $record['level']);
            $this->assertStringContainsString('No available worker', $record['message']);
        }
    }

    public function testSyncLabInstanceDoesNotThrowWhenTheRepositoryFails()
    {
        $instance = $this->createUserInstance($this->group, $this->sourceLab, new User(), '192.168.11.132', '10.11.0.0/24');

        $labShareRepository = $this->createMock(LabShareRepository::class);
        $labShareRepository->method('findBy')->willReturn([]);
        $labShareRepository->method('findGroupsWithLab')->willThrowException(new \RuntimeException('boom'));

        $handler = new TestHandler();
        $manager = new SharedLabSecurityManager(
            $labShareRepository,
            $this->createMock(LabInstanceRepository::class),
            $this->createMock(ConfigWorkerRepository::class),
            $this->createMock(MessageBusInterface::class),
            new Logger('test', [$handler])
        );

        // A failing repository never breaks the device start that triggered it
        $manager->syncLabInstance($instance);

        $this->assertStringContainsString('Could not load the groups sharing lab', $handler->getRecords()[0]['message']);
    }

    public function testSyncLabInstanceDoesNothingWhenTheLabIsSharedInNoGroup()
    {
        $instance = $this->createUserInstance($this->group, $this->sourceLab, new User(), '192.168.11.132', '10.11.0.0/24');

        $handler = new TestHandler();
        $manager = $this->createManager([], null, [], new Logger('test', [$handler]));

        $manager->syncLabInstance($instance);

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(Logger::DEBUG, $records[0]['level']);
        $this->assertStringContainsString('is shared in no group', $records[0]['message']);
    }

    private function createManager(
        array $shares,
        ?Group $group = null,
        array $workers = [],
        ?LoggerInterface $logger = null,
        ?MessageBusInterface $bus = null
    ): SharedLabSecurityManager {
        $labShareRepository = $this->createMock(LabShareRepository::class);
        $labShareRepository->method('findBy')->willReturnCallback(function (array $criteria) use ($shares, $group) {
            $criteriaGroup = $criteria['group'] ?? null;

            return (null === $group || $criteriaGroup === $group) ? $shares : [];
        });
        $labShareRepository->method('findGroupsWithLab')->willReturnCallback(function () {
            return $this->groupsWithLab;
        });

        $labInstanceRepository = $this->createMock(LabInstanceRepository::class);
        $labInstanceRepository->method('findBy')->willReturnCallback(function (array $criteria) {
            if (($criteria['ownedBy'] ?? null) !== LabInstance::OWNED_BY_USER) {
                return [];
            }

            $lab = $criteria['lab'] ?? null;
            $instances = [];
            foreach ($this->userInstances as $candidate) {
                if ($candidate->getLab() === $lab) {
                    $instances[] = $candidate;
                }
            }

            return $instances;
        });

        $configWorkerRepository = $this->createMock(ConfigWorkerRepository::class);
        $configWorkerRepository->method('findAll')->willReturn($workers);

        return new SharedLabSecurityManager(
            $labShareRepository,
            $labInstanceRepository,
            $configWorkerRepository,
            $bus ?? $this->createMock(MessageBusInterface::class),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    private function createWorker(string $ip, bool $available): ConfigWorker
    {
        $worker = $this->createMock(ConfigWorker::class);
        $worker->method('getIPv4')->willReturn($ip);
        $worker->method('getAvailable')->willReturn($available);

        return $worker;
    }

    private function createShare(Lab $lab, Lab $sharedWith, Group $group): LabShare
    {
        $share = new LabShare();
        $share->setLab($lab)->setSharedWith($sharedWith)->setGroup($group);

        return $share;
    }

    private function createGroupInstance(Group $group, Lab $lab, ?string $workerIp, string $network, array $deviceStates = [InstanceState::STARTED]): LabInstance
    {
        $labInstance = $this->createInstance($lab, $workerIp, $network, $deviceStates);
        $labInstance->setOwnedBy(LabInstance::OWNED_BY_GROUP);
        $labInstance->setGroup($group);
        $group->addLabInstance($labInstance);

        return $labInstance;
    }

    /**
     * An instance launched by a user (sandbox): it takes part in the topology of
     * every group where its lab is shared and where its owner is a member.
     */
    private function createUserInstance(Group $group, Lab $lab, User $user, ?string $workerIp, string $network, array $deviceStates = [InstanceState::STARTED]): LabInstance
    {
        $labInstance = $this->createInstance($lab, $workerIp, $network, $deviceStates);
        $labInstance->setOwnedBy(LabInstance::OWNED_BY_USER);
        $labInstance->setUser($user);
        $this->userInstances[] = $labInstance;

        return $labInstance;
    }

    private function createInstance(Lab $lab, ?string $workerIp, string $network, array $deviceStates): LabInstance
    {
        $ip = explode('/', $network)[0];

        $labInstance = new LabInstance();
        $labInstance->setLab($lab);
        $labInstance->setWorkerIp($workerIp);
        $labInstance->setNetwork(new Network($ip, '255.255.255.0'));

        foreach ($deviceStates as $state) {
            $deviceInstance = new DeviceInstance();
            $deviceInstance->setState($state);
            $labInstance->addDeviceInstance($deviceInstance);
        }

        return $labInstance;
    }

    private function sortedPair(array $a, array $b): array
    {
        $uuids = [$a['uuid'], $b['uuid']];
        sort($uuids);

        return $uuids;
    }
}
