<?php

namespace App\Tests\Controller;

class DeviceControllerTest extends AuthenticatedWebTestCase
{
    public function testApiCreateDevice()
    {
        // The form requires an existing operating system, resolve a real one
        // from an existing device
        $this->client->request('GET', '/api/devices');
        $this->assertResponseIsSuccessful();
        $devices = json_decode($this->client->getResponse()->getContent(), true);

        $operatingSystem = null;
        foreach ($devices as $device) {
            if (!empty($device['operatingSystem'])) {
                $operatingSystem = $device['operatingSystem'];
                break;
            }
        }
        $this->assertNotNull($operatingSystem, 'A device with an operating system must exist');

        $form['name'] = 'test-device';
        $form['brand'] = 'test';
        $form['model'] = 'test model';
        $form['operatingSystem'] = $operatingSystem['id'];
        $form['flavor'] = 1;
        $form['isTemplate'] = 0;

        $this->client->request(
            'POST',
            '/api/devices',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($form)
        );

        $this->assertResponseIsSuccessful();

        $device = json_decode($this->client->getResponse()->getContent(), true);

        return $device['id'];
    }

    /**
     * @depends testApiCreateDevice
     */
    public function testEditDevice($id)
    {
        $form['name'] = 'test-device-edited';
        $form['brand'] = 'test-edited';
        $form['model'] = 'test model edited';
        $form['operatingSystem'] = 1;
        $form['flavor'] = 2;
        $form['isTemplate'] = 1;
        // The update action expects this key, even when empty
        $form['controlProtocolTypes'] = [];

        $this->client->request(
            'PUT',
            '/api/devices/' . $id,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($form)
        );
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/devices/' . $id . '/edit');
        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testApiCreateDevice
     */
    public function testShowDevice($id)
    {
        $this->client->request('GET', '/admin/devices/' . $id);
        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testApiCreateDevice
     */
    public function testShowDevicePublic($id)
    {
        $this->client->request('GET', '/devices/' . $id);
        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testApiCreateDevice
     */
    public function testDeleteDevice($id)
    {
        // The delete route only accepts the DELETE method
        $this->client->followRedirects();

        $crawler = $this->client->request('DELETE', '/admin/devices/' . $id . '/delete');
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}
