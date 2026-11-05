<?php

namespace App\Tests\Controller;

class DeviceNetworkCardTest extends AuthenticatedWebTestCase
{
    public function testDeviceFormRendersNewFields()
    {
        $this->client->request('GET', '/admin/devices/new');
        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('qemu-nic-fields-container', $content);
        $this->assertStringContainsString('network_card_type', $content);
        $this->assertStringContainsString('minimum_network_interfaces', $content);
    }

    public function testApiCreateAndEditDeviceWithNetworkCard()
    {
        $create = [
            'name' => 'temp-nic-device',
            'brand' => 'test',
            'model' => 'test model',
            'icon' => 'Server_Linux.png',
            'operatingSystem' => 5,
            'flavor' => 1,
            'controlProtocolTypes' => [],
            'isTemplate' => 0,
            'network_card_type' => 'virtio-net-pci',
            'minimum_network_interfaces' => '3',
        ];

        $this->client->request(
            'POST',
            '/api/devices',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($create)
        );
        $this->assertResponseIsSuccessful();
        $device = json_decode($this->client->getResponse()->getContent(), true);
        $id = $device['id'];

        $this->client->request('GET', '/api/devices/' . $id);
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('virtio-net-pci', $data['network_card_type']);
        $this->assertSame(3, $data['minimum_network_interfaces']);

        // Edit through the JSON API (same path as the form fields)
        $this->client->request(
            'PUT',
            '/api/devices/' . $id,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'temp-nic-device',
                'brand' => 'test',
                'model' => 'test model',
                'icon' => 'Server_Linux.png',
                'operatingSystem' => 5,
                'flavor' => 1,
                'controlProtocolTypes' => [],
                'network_card_type' => 'e1000e',
                'minimum_network_interfaces' => '5',
            ])
        );
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/devices/' . $id);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('e1000e', $data['network_card_type']);
        $this->assertSame(5, $data['minimum_network_interfaces']);

        $this->client->request('DELETE', '/api/devices/' . $id);
        $this->assertResponseIsSuccessful();
    }

    public function testTemplateOptionsExposeNetworkCardForQemu()
    {
        $this->client->request(
            'POST',
            '/api/list/templates/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['virtuality' => 1])
        );
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('network_card_type', $data['data']['options']);
        $this->assertArrayHasKey('minimum_network_interfaces', $data['data']['options']);
        $this->assertSame('list', $data['data']['options']['network_card_type']['type']);
        $this->assertSame('input', $data['data']['options']['minimum_network_interfaces']['type']);
        $this->assertArrayHasKey('virtio-net-pci', $data['data']['options']['network_card_type']['list']);
    }

    public function testTemplateOptionsHideNetworkCardForContainer()
    {
        $this->client->request(
            'POST',
            '/api/list/templates/3',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['virtuality' => 1])
        );
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('network_card_type', $data['data']['options']);
        $this->assertArrayNotHasKey('minimum_network_interfaces', $data['data']['options']);
    }
}
