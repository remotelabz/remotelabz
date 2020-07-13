<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InstanceControllerTest extends WebTestCase
{
    use ControllerTestTrait, InstanceControllerTestTrait;
    
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
        return $this->createLabInstance($this->labUuid, $this->userUuid, 'user');
    }

    /**
     * @depends testCreateLabInstance
     */
    public function testStartDeviceInstance(array $data)
    {
        // Wait ~ 3 seconds for initialisation of lab instance
        sleep(3);
        
        $deviceUuid = $data['deviceInstances'][0]['uuid'];

        $this->logIn();
        $this->startDeviceInstance($deviceUuid);

        return $deviceUuid;
    }

    /**
     * @depends testStartDeviceInstance
     */
    /*
    public function testViewDeviceInstance($deviceUuid)
    {
        // Wait ~ 3 seconds for initialisation of device instance
        sleep(3);

        $this->login();
        $this->viewDeviceInstance($deviceUuid);
    }
    */

    /**
     * @depends testStartDeviceInstance
     */
    public function testStopDeviceInstance($deviceUuid)
    {
        $this->logIn();
        $this->stopDeviceInstance($deviceUuid);
    }

    /**
     * @depends testCreateLabInstance
     */
    public function testDeleteLabInstance(array $data)
    {
        // Resume tests
        $this->logIn();
        $this->deleteLabInstance($data['uuid']);
    }
}