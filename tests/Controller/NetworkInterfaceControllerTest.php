<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NetworkInterfaceControllerTest extends WebTestCase
{
    use ControllerTestTrait;

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

    public function testAddNewNetworkInterface()
    {
        $this->logIn();

        $form['name'] = 'NetworkInterface Test';
        $form['macAddress'] = '52:54:00:FF:FF:FF';
        $form['isTemplate'] = '1';

        $data = json_encode($form);

        $this->client->request('POST', 
            '/api/network-interfaces',
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            $data
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $networkInterface = json_decode($this->client->getResponse()->getContent(), true);

        return $networkInterface['id'];
    }

    /**
     * @depends testAddNewNetworkInterface
     */
    public function testEditNetworkInterface($id)
    {
        $this->logIn();

        $form['name'] = 'NetworkInterface Test Edited';
        $form['macAddress'] = '52:54:00:FF:0F:FF';
        $form['accessType'] = 'VNC';
        $form['device'] = '1';
        $form['isTemplate'] = '0';

        $data = json_encode($form);

        $this->client->request('PUT',
            '/api/network-interfaces/' . $id,
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            $data
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->client->request('GET', '/admin/network-interfaces/' . $id . '/edit');
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testAddNewNetworkInterface
     */
    public function testDeleteNetworkInterface($id)
    {
        $this->login();
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/network-interfaces/' . $id . '/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}