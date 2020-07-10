<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NetworkInterfaceControllerTest extends WebTestCase
{
    use ControllerTestTrait, NetworkInterfaceControllerTestTrait;

    public function testInvalidDataOnNewAction()
    {
        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();
        $this->logIn();
        $crawler = $this->client->request('GET', '/admin/network-interfaces/new');

        $this->client->enableProfiler();

        $form = $crawler->selectButton('network_interface[submit]')->form();
        $macAddress = $faker->macAddress;
        $form['network_interface[name]'] = $faker->name;
        $form['network_interface[macAddress]'] = 'hello-world';

        $crawler = $this->client->submit($form);
        $this->assertGreaterThan(0, $crawler->filter('.invalid-feedback')->count());

        $form['network_interface[macAddress]'] = '00:22:33:99:99:67:88';

        $crawler = $this->client->submit($form);
        $this->assertGreaterThan(0, $crawler->filter('.invalid-feedback')->count());
    }

    public function testCreateNetworkInterface()
    {
        $this->logIn();
        return $this->createNetworkInterface('NetworkInterface Test',
            '52:54:00:FF:FF:FF'
        );
    }

    /**
     * @depends testCreateNetworkInterface
     */
    public function testEditNetworkInterface($id)
    {
        $this->logIn();
        $this->editNetworkInterface($id,
            'NetworkInterface Test Edited',
            '52:54:00:FF:0F:FF',
            'VNC',
            '1',
            '0'
        );
    }

    /**
     * @depends testCreateNetworkInterface
     */
    public function testDeleteNetworkInterface($id)
    {
        $this->login();
        $this->deleteNetworkInterface($id);
    }
}