<?php

namespace App\Tests\Entity;

use App\Entity\Group;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    public function testOwnerIsNullWhenGroupHasNoOwner(): void
    {
        $group = new Group();

        $this->assertNull($group->getOwner());
    }

    public function testOwnerIsTheMemberWithOwnerRole(): void
    {
        $group = new Group();
        $owner = new User();

        $group->addUser($owner, Group::ROLE_OWNER);

        $this->assertSame($owner, $group->getOwner());
    }
}
