<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InstanceControllerTest extends WebTestCase
{
    use ControllerTestTrait;
    private $labUuid;
    private $userUuid;
    
    private function loadData()
    {
        $this->logIn();
        $this->client->request('GET', '/api/users/me'); 
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->userUuid = $data['uuid'];
        
        $this->client->request('GET', '/api/labs/1');
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->labUuid = $data['uuid'];
    }

    public function testCreateLabInstance()
    {
        $this->loadData();
        $this->client->request('POST', '/api/instances/create',
            array(
                "lab" => $this->labUuid,
                "instancier" => $this->userUuid,
                "instancierType" => "user"
            ));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $tmp = json_decode($this->client->getResponse()->getContent(), true);

        $data = array();
        $data['labInstanceUuid'] = $tmp['uuid'];
        $data['deviceUuid'] = $tmp['deviceInstances'][0]['uuid'];
        
        return $data;
    }

    /**
     * @depends testCreateLabInstance
     */
    public function testStartDeviceInstance(array $data)
    {
        // Wait ~ 3 seconds for initialisation of lab instance
        sleep(3);
        
        $deviceUuid = $data['deviceUuid'];

        $this->logIn();
        $this->client->request('GET', '/api/instances/start/by-uuid/' . $deviceUuid);
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        return $deviceUuid;
    }

    /**
     * @depends testStartDeviceInstance
     */
    public function testViewDeviceInstance($deviceUuid)
    {
        // Wait ~ 3 seconds for initialisation of device instance
        sleep(3);

        $this->login();
        $crawler = $this->client->request('GET', '/instances/' . $deviceUuid . '/view');
        $this->assertSame(0, $crawler->filter('.flash-notice.alert-danger')->count());
    }

    /**
     * @depends testStartDeviceInstance
     */
    public function testStopDeviceInstance($deviceUuid)
    {
        $this->logIn();
        $this->client->request('GET', '/api/instances/stop/by-uuid/' . $deviceUuid);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testCreateLabInstance
     */
    public function testDeleteLabInstance(array $data)
    {
        // Resume tests
        $this->logIn();
        $this->client->request('DELETE', '/api/instances/' . $data['labInstanceUuid']);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }
}