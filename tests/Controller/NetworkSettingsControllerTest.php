<?php

namespace App\Tests\Controller;

class NetworkSettingsControllerTest extends AuthenticatedWebTestCase
{
    private function getIdOfNetworkSettingTest()
    {
        $this->logIn();
        $this->client->request('GET', '/network-settings');
        $this->assertResponseIsSuccessful();
        $networkSettings = json_decode($this->client->getResponse()->getContent(), true);
        $id = 0;
        foreach ($networkSettings as $networkSetting) {
            if ('testNetworkSettings' === $networkSetting['name']) {
                $id = $networkSetting['id'];
                break;
            }
        }

        return $id;
    }

    public function testCgetAction()
    {
        $this->client->request('GET', '/network-settings');
        $this->assertResponseIsSuccessful();
    }

    public function testCreateNetworkSettings()
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/network-settings/new');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('network_settings[submit]')->form();

        $form['network_settings[name]'] = 'testNetworkSettings';
        $form['network_settings[ip]'] = '192.168.56.0';
        $form['network_settings[gateway]'] = '192.168.56.254';
        $form['network_settings[protocol]'] = '';
        $form['network_settings[port]'] = 0;

        $crawler = $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        return $this->getIdOfNetworkSettingTest();
    }

    /**
     * @depends testCreateNetworkSettings
     */
    public function testEditNetworkSettings($id)
    {
        $this->client->request('GET', '/admin/network-settings/99999999/edit');
        $this->assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', '/admin/network-settings/'.$id.'/edit');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('network_settings[submit]')->form();

        $form['network_settings[name]'] = 'testNetworkSettings-edited';

        $crawler = $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    /**
     * @depends testCreateNetworkSettings
     */
    public function testDeleteNetworkSettings($id)
    {
        $this->client->request('DELETE', '/admin/network-settings/'.$id);
        $this->assertResponseIsSuccessful();

        $this->client->request('DELETE', '/admin/network-settings/99999999');
        $this->assertResponseStatusCodeSame(404);
    }
}
