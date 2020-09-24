<?php

namespace App\Tests\Controller;

class LabControllerTest extends AuthenticatedWebTestCase
{
    public function testCreateLab()
    {
        $this->client->request('POST', '/api/labs');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        return $data['id'];
    }

    /**
     * @depends testCreateLab
     */
    public function testEditLab($labId)
    {
        $tmp['name'] = 'Edited Lab';
        $tmp['description'] = 'This is a new description';
        $data = json_encode($tmp);

        $this->client->request('PUT',
            '/api/labs/'.$labId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data
        );

        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testCreateLab
     */
    public function testAddDeviceToLab($labId)
    {
        $device = [
            'name' => 'test-device',
            'operatingSystem' => 1,
            'networkInterfaces' => [],
            'flavor' => 1,
            'isTemplate' => 0,
        ];

        $this->client->request('POST',
            '/api/labs/'.$labId.'/devices',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($device)
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->client->request('DELETE', '/api/devices/'.$data['id']);

        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testCreateLab
     */
    public function testDeleteLab($labId)
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/admin/labs/'.$labId.'/delete');
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        // api side
        $this->client->request('POST', '/api/labs');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->client->request('DELETE', '/api/labs/'.$data['id']);
        $this->assertResponseIsSuccessful();
    }
}
