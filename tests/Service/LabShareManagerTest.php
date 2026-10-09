<?php

namespace App\Tests\Service;

use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\LabShare;
use App\Repository\GroupRepository;
use App\Repository\LabRepository;
use App\Repository\LabShareRepository;
use App\Service\LabShareManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

/**
 * Unit tests of the share rule validations (shared-labs-front-plan.md §2.3 and §9.1).
 */
class LabShareManagerTest extends TestCase
{
    private Lab $lab;

    private Lab $target;

    private Group $group;

    /** @var array<string, Group> */
    private array $groups = [];

    private LabShareManager $manager;

    protected function setUp(): void
    {
        $this->lab = $this->createLab(1, 'Shared lab');
        $this->target = $this->createLab(42, 'Target lab');
        $this->group = $this->createGroup(7, '3f2b9c1e-0000-0000-0000-000000000000');

        $this->lab->addGroup($this->group);
        $this->target->addGroup($this->group);

        $labRepository = $this->createMock(LabRepository::class);
        $labRepository->method('find')->willReturnCallback(function ($id) {
            return in_array($id, [1, 42], true) ? ($id === 1 ? $this->lab : $this->target) : null;
        });

        $groupRepository = $this->createMock(GroupRepository::class);
        $groupRepository->method('findOneBy')->willReturnCallback(function (array $criteria) {
            return $this->groups[$criteria['uuid'] ?? ''] ?? null;
        });

        $this->manager = new LabShareManager(
            $labRepository,
            $groupRepository,
            $this->createMock(LabShareRepository::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testValidRuleIsApplied()
    {
        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 42, 'group' => $this->group->getUuid()],
        ]);

        $this->assertEquals([], $errors);
        $this->assertCount(1, $this->lab->getShares());

        $share = $this->lab->getShares()->first();
        $this->assertInstanceOf(LabShare::class, $share);
        $this->assertSame($this->lab, $share->getLab());
        $this->assertSame($this->target, $share->getSharedWith());
        $this->assertSame($this->group, $share->getGroup());
        $this->assertNotNull($share->getCreatedAt());
    }

    public function testSelfShareIsRejected()
    {
        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 1, 'group' => $this->group->getUuid()],
        ]);

        $this->assertContains('A lab cannot be shared with itself.', $errors);
        $this->assertCount(0, $this->lab->getShares());
    }

    public function testTargetLabOutsideOfTheGroupIsRejected()
    {
        $otherGroup = $this->createGroup(8, 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        // The source lab is available in the group, the target lab is not
        $this->lab->addGroup($otherGroup);

        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 42, 'group' => $otherGroup->getUuid()],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Target lab is not available in group', $errors[0]);
        $this->assertCount(0, $this->lab->getShares());
    }

    public function testSourceLabOutsideOfTheGroupIsRejected()
    {
        $otherGroup = $this->createGroup(8, 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        // The target is available in the group, the source lab is not
        $this->target->addGroup($otherGroup);

        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 42, 'group' => $otherGroup->getUuid()],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Shared lab is not available in group', $errors[0]);
    }

    public function testUnknownGroupIsRejected()
    {
        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 42, 'group' => 'does-not-exist'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('does not exist', $errors[0]);
    }

    public function testUnknownTargetLabIsRejected()
    {
        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 999, 'group' => $this->group->getUuid()],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('does not exist', $errors[0]);
    }

    public function testMissingGroupAndTargetAreRejected()
    {
        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 42],
            ['group' => $this->group->getUuid()],
        ]);

        $this->assertCount(2, $errors);
    }

    public function testDuplicateRulesAreRejected()
    {
        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 42, 'group' => $this->group->getUuid()],
            ['sharedWith' => 42, 'group' => $this->group->getUuid()],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplicated', $errors[0]);
        $this->assertCount(0, $this->lab->getShares());
    }

    public function testInvalidRuleLeavesTheCollectionUntouched()
    {
        $existing = new LabShare();
        $existing->setLab($this->lab)->setSharedWith($this->target)->setGroup($this->group);
        $this->lab->addShare($existing);

        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 42, 'group' => $this->group->getUuid()],
            ['sharedWith' => 1, 'group' => $this->group->getUuid()], // self share: invalid
        ]);

        $this->assertNotEmpty($errors);
        $this->assertCount(1, $this->lab->getShares());
        $this->assertSame($existing, $this->lab->getShares()->first());
    }

    public function testReplacementIsAtomic()
    {
        $errors = $this->manager->replaceShares($this->lab, [
            ['sharedWith' => 42, 'group' => $this->group->getUuid()],
            ['sharedWith' => 42, 'group' => 'does-not-exist'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertCount(0, $this->lab->getShares());
    }

    public function testPurgeGroupShareRules()
    {
        $ownShare = new LabShare();
        $ownShare->setLab($this->lab)->setSharedWith($this->target)->setGroup($this->group);
        $this->lab->addShare($ownShare);

        $incomingShare = new LabShare();
        $incomingShare->setLab($this->target)->setSharedWith($this->lab)->setGroup($this->group);
        $this->target->addShare($incomingShare);

        $labShareRepository = $this->createMock(LabShareRepository::class);
        $labShareRepository->method('findForLabInGroup')->willReturn([$ownShare, $incomingShare]);

        $manager = new LabShareManager(
            $this->createMock(LabRepository::class),
            $this->createMock(GroupRepository::class),
            $labShareRepository,
            $this->createMock(LoggerInterface::class)
        );

        $removed = $manager->purgeGroupShareRules($this->lab, $this->group);

        $this->assertEquals(2, $removed);
        $this->assertCount(0, $this->lab->getShares());
        $this->assertCount(0, $this->target->getShares());
    }

    private function createLab(int $id, string $name): Lab
    {
        $lab = new Lab();
        $lab->setName($name);
        $this->setId($lab, $id);

        return $lab;
    }

    private function createGroup(int $id, string $uuid): Group
    {
        $group = new Group();
        $group->setName('Group '.$id);
        $group->setSlug('group-'.$id);
        $group->setUuid($uuid);
        $this->setId($group, $id);
        $this->groups[$uuid] = $group;

        return $group;
    }

    private function setId(object $object, int $id): void
    {
        $property = new ReflectionProperty(Lab::class, 'id');
        if ($object instanceof Group) {
            $property = new ReflectionProperty(Group::class, 'id');
        }
        $property->setValue($object, $id);
    }
}
