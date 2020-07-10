<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DeviceControllerTest extends WebTestCase
{
    use ControllerTestTrait, DeviceControllerTestTrait;

    public function testCreateDevice()
    {
        $this->logIn();
        $device = $this->createDevice('test-device',
            'test',
            'test model',
            '2',
            '1',
            '0'
        );

        return $device['id'];
    }

    /**
     * @depends testCreateDevice
     */
    public function testEditDevice($id)
    {
        $this->logIn();
        $this->editDevice($id,
            'test-device-edited',
            'test-edited',
            'test model edited',
            '1',
            '2',
            '1'
        );
    }

    /**
     * @depends testCreateDevice
     */
    public function testShowDevice($id)
    {
        $this->logIn();
        $this->client->request('GET', '/admin/devices/' . $id);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testCreateDevice
     */
    public function testShowDevicePublic($id)
    {
        $this->logIn();
        $this->client->request('GET', '/devices/' . $id);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testCreateDevice
     */
    public function testDeleteDevice($id)
    {
        $this->logIn();
        $this->deleteDevice($id);
    }
}