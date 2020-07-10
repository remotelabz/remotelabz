<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GroupControllerTest extends WebTestCase
{
    use ControllerTestTrait, GroupControllerTestTrait;

    public function testCreateGroup()
    {
        $this->logIn();
        $this->createGroup('Test Group', 'test-group', 'That\'s a description', '1');
    }

    /**
     * @depends testCreateGroup
     */
    public function testDeleteGroup()
    {
        $this->logIn();
        $this->deleteGroup('test-group');
    }
}