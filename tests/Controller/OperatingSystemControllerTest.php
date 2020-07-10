<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OperatingSystemControllerTest extends WebTestCase
{
    use ControllerTestTrait, OperatingSystemControllerTestTrait;

    public function testCreateOperatingSystem()
    {
        $this->logIn();
        return $this->createOperatingSystem('OS - Test', 'http://urlto.img');
    }

    /**
     * @depends testCreateOperatingSystem
     */
    public function testShowOperatingSystem($id)
    {
        $this->logIn();
        $this->client->request('GET', '/admin/operating-systems/' . $id);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testCreateOperatingSystem
     */
    public function testEditOperatingSystem($id)
    {
        $this->logIn();
        $this->editOperatingSystem($id, 'OS - Test Edited', 'http://new-urlto.img');
    }

    /**
     * @depends testCreateOperatingSystem
     */
    public function testDeleteOperatingSystem($id)
    {
        $this->logIn();
        $this->deleteOperatingSystem($id);
    }
}