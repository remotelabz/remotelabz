<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    use ControllerTestTrait;

    public function testIndexGetAction()
    {
        $this->logIn();
        $this->client->request('GET', '/admin/users');

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testIndexPostAction()
    {
        $this->logIn();
        $this->client->request('POST', '/admin/users');

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }
}