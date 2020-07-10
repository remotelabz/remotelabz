<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait NetworkSettingsControllerTestTrait
{
    protected function createNetworkSettings($name, $ip = '', $ipv6 = '', $prefix4 = '', $prefix6 = '', $gateway = '', $protocol = '', $port = '')
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/network-settings/new');
        $form = $crawler->selectButton('network_settings[submit]')->form();

        $form['network_settings[name]'] = $name;
        $form['network_settings[ip]'] = $ip;
        $form['network_settings[ipv6]'] = $ipv6;
        $form['network_settings[prefix4]'] = $prefix4;
        $form['network_settings[prefix6]'] = $prefix6;
        $form['network_settings[gateway]'] = $gateway;
        $form['network_settings[protocol]'] = $protocol;
        $form['network_settings[port]'] = $port;

        $crawler = $this->client->submit($form);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    protected function editNetworkSettings($id, $name = '', $ip = '', $ipv6 = '', $prefix4 = '', $prefix6 = '', $gateway = '', $protocol = '', $port = '')
    {
        $crawler = $this->client->request('GET', '/admin/network-settings/' . $id . '/edit');
        $form = $crawler->selectButton('network_settings[submit]')->form();

        $form['network_settings[name]'] = $name;
        $form['network_settings[ip]'] = $ip;
        $form['network_settings[ipv6]'] = $ipv6;
        $form['network_settings[prefix4]'] = $prefix4;
        $form['network_settings[prefix6]'] = $prefix6;
        $form['network_settings[gateway]'] = $gateway;
        $form['network_settings[protocol]'] = $protocol;
        $form['network_settings[port]'] = $port;

        $crawler = $this->client->submit($form);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    protected function deleteNetworkSettings($id)
    {
        $this->client->request('DELETE', '/admin/network-settings/' . $id);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }
}