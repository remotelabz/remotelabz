<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DeviceControllerTest extends WebTestCase
{
    use ControllerTestTrait;

    public function testAddNewDevice()
    {
        $this->logIn();

        $form['name'] = 'test-device';
        $form['brand'] = 'test';
        $form['model'] = 'test model';
        $form['operatingSystem'] = '2';
        $form['flavor'] = '1';

        $data = json_encode($form);

        $this->client->request('POST', 
            '/api/devices',
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            $data);

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $device = json_decode($this->client->getResponse()->getContent());

        return $device['id'];
    }

    /**
     * @depends testAddNewDevice
     */
    public function testEditDevice($id)
    {
        $this->logIn();
        
        $form['name'] = 'test-device-edited';
        $form['brand'] = 'test-edited';
        $form['model'] = 'test model edited';
        $form['operatingSystem'] = '1';
        $form['flavor'] = '2';

        $data = json_encode($form);

        $this->client->request('PUT', 
            '/api/devices/' . $id,
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            $data);
        
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->client->request('GET', '/admin/devices/' . $id . '/edit');
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testAddNewDevice
     */
    public function testShowDevice($id)
    {
        $this->logIn();
        $this->client->request('GET', '/admin/devices/' . $id);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testAddNewDevice
     */
    public function testShowDevicePublic($id)
    {
        $this->logIn();
        $this->client->request('GET', '/devices/' . $id);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testAddNewDevice
     */
    public function testDeleteDevice($id)
    {
        $this->logIn();
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/devices/' . $id . '/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}