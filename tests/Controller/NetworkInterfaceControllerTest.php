<?php

namespace App\Tests\Controller;

class NetworkInterfaceControllerTest extends AuthenticatedWebTestCase
{
    public function testInvalidDataOnNewAction()
    {
        $crawler = $this->client->request('GET', '/admin/network-interfaces/new');
        $this->assertResponseIsSuccessful();

        $this->client->enableProfiler();

        $form = $crawler->selectButton('network_interface[submit]')->form();
        $macAddress = "00:12:45:78:45:65";
        $form['network_interface[name]'] = 'eth';
        $form['network_interface[macAddress]'] = 'hello-world';

        $crawler = $this->client->submit($form);
        $this->assertGreaterThan(0, $crawler->filter('.invalid-feedback')->count());

        $form['network_interface[macAddress]'] = '00:22:33:99:99:67:88';

        $crawler = $this->client->submit($form);
        $this->assertGreaterThan(0, $crawler->filter('.invalid-feedback')->count());
    }

    public function testCreateNetworkInterface()
    {
        $form = [
            'name' => 'NetworkInterface Test',
            'macAddress' => '52:54:00:FF:FF:FF',
            'isTemplate' => 1,
        ];

        $data = json_encode($form);

        $this->client->request('POST',
            '/api/network-interfaces',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data
        );
        $this->assertResponseIsSuccessful();

        $networkInterface = json_decode($this->client->getResponse()->getContent(), true);

        return $networkInterface['id'];
    }

    /**
     * @depends testCreateNetworkInterface
     */
    public function testEditNetworkInterface($id)
    {
        $form = [
            'name' => 'NetworkInterface Test Edited',
            'macAddress' => '52:54:00:FF:0F:FF',
            'accessType' => 'VNC',
            'device' => 1,
            'isTemplate' => 0,
        ];

        $data = json_encode($form);

        $this->client->request('PUT',
            '/api/network-interfaces/'.$id,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data
        );
        $this->assertResponseIsSuccessful();
        $networkInterface = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('NetworkInterface Test Edited', $networkInterface['name']);

        $this->client->request('GET', '/admin/network-interfaces/'.$id.'/edit');
        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testCreateNetworkInterface
     */
    public function testDeleteNetworkInterface($id)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/network-interfaces/'.$id.'/delete');
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}
