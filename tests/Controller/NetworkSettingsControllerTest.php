<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NetworkSettingsControllerTest extends WebTestCase
{
    use ControllerTestTrait, NetworkSettingsControllerTestTrait;

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

    public function testCreateNetworkSettings()
    {
        $this->logIn();
        $this->createNetworkSettings('testNetworkSettings',
            '192.168.56.0',
            '',
            '24',
            '',
            '192.168.56.254'
        );

        return $this->getIdOfNetworkSettingTest();
    }

    /**
     * @depends testCreateNetworkSettings
     */
    public function testEditNetworkSettings($id)
    {
        $this->logIn();
        $this->editNetworkSettings($id, 'testNetworkSettings-edited');
    }

    /**
     * @depends testCreateNetworkSettings
     */
    public function testDeleteNetworkSettings($id)
    {
        $this->logIn();
        $this->deleteNetworkSettings($id);
    }
}