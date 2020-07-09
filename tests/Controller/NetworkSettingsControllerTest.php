<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NetworkSettingsControllerTest extends WebTestCase
{
    use ControllerTestTrait;

    private function getIdOfNetworkSettingTest()
    {
        $this->logIn();
        $this->client->request('GET', '/network-settings');
        $networkSettings = json_decode($this->client->getResponse()->getContent(), true);
        $id = 0;
        foreach($networkSettings as $networkSetting)
        {
            if($networkSetting['name'] === 'testNetworkSettings')
            {
                $id = $networkSetting['id'];
                break;
            }
        }

        return $id;
    }

    public function testAddNewNetworkSettings()
    {
        $this->logIn();
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/network-settings/new');
        $form = $crawler->selectButton('network_settings[submit]')->form();

        $form['network_settings[name]'] = 'testNetworkSettings';
        $form['network_settings[ip]'] = '192.168.56.0';
        $form['network_settings[prefix4]'] = '24';
        $form['network_settings[gateway]'] = '192.168.56.254';

        $crawler = $this->client->submit($form);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    
        return $this->getIdOfNetworkSettingTest();
    }

    /**
     * @depends testAddNewNetworkSettings
     */
    public function testEditNetworkSettings($id)
    {
        $this->logIn();
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/network-settings/' . $id . '/edit');
        $form = $crawler->selectButton('network_settings[submit]')->form();

        $form['network_settings[name]'] = 'testNetworkSettings-edited';
        
        $crawler = $this->client->submit($form);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    /**
     * @depends testAddNewNetworkSettings
     */
    public function testDeleteNetworkSettings($id)
    {
        $this->logIn();

        $this->client->request('DELETE', '/admin/network-settings/' . $id);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }
}