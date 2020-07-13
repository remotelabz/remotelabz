<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApplicationRunTest extends WebTestCase
{
    use Controller\DeviceControllerTestTrait;
    use Controller\FlavorControllerTestTrait;
    use Controller\GroupControllerTestTrait;
    use Controller\InstanceControllerTestTrait;
    use Controller\LabControllerTestTrait;
    use Controller\NetworkInterfaceControllerTestTrait;
    use Controller\NetworkSettingsControllerTestTrait;
    use Controller\OperatingSystemControllerTestTrait;
    use Controller\UserControllerTestTrait;
    
    /**
     * @var KernelBrowser $client
     */
    private $client;

    public function setUp()
    {
        $this->client = static::createClient();
    }

    public function testRun()
    {
        $this->logIn();
        $osId = $this->createOperatingSystem('Alpine 2', 'http://194.57.105.124/~fnolot/alpinelab1.img');
        $flavorId = $this->createFlavor('x-test', '1024', '10');
        $device = $this->createDevice('Linux Alpine 2', 'brand', 'model', strval($osId), strval($flavorId), '1');
        //$networkInterfaceId = $this->createNetworkInterface('ToAlpine2', '52:54:00:54:54:54', 'VNC', $device['id'], '1');
        
        // Replace value to fit in form
        $device['operatingSystem'] = strval($osId);
        $device['flavor'] = strval($flavorId);
        //$device['networkInterfaces'] = strval($networkInterfaceId);

        $labInfos = $this->createLab();
        $deviceInLab = $this->addDeviceToLab($labInfos['id'], $device);
        
        $this->createUser('unittest@localhost',
            'P@sSW0rD_Un1t_T3st',
            'LastName',
            'FirstName',
            'ROLE_USER'
        );
        $userInfos = $this->getGuestInfo();
        // We need to relog after getGuestInfo
        $this->logIn();
        $groupUuid = $this->createGroup('Test Group', 'test-group', 'This group is for test only', '1');
        $this->addUserToGroup('test-group', $userInfos['id']);

        // Start lab instance by user (admin)
        $adminInfos = $this->getUserInfo();
        $this->logIn();
        
        $networkInterfaceId = $this->createNetworkInterface('ToAlpine2', '52:54:00:54:54:54', 'VNC', $deviceInLab['id'], '1');
        $labInstance = $this->createLabInstance($labInfos['uuid'], $adminInfos['uuid'], 'user');
        $this->startDeviceInstance($labInstance['deviceInstances'][0]['uuid']);

        $this->stopDeviceInstance($labInstance['deviceInstances'][0]['uuid']);
        $this->deleteLabInstance($labInstance['uuid']);

        // Start lab instance for group
        $labInstance = $this->createLabInstance($labInfos['uuid'], $groupUuid, 'group');
        $this->startDeviceInstance($labInstance['deviceInstances'][0]['uuid']);

        // Try to access to VNC Console with a guest
        /*
        $this->logOut();
        $this->logInGuest();
        
        $this->logOut();
        $this->logIn();
        */

        // Stop Device and Lab instance
        $this->stopDeviceInstance($labInstance['deviceInstances'][0]['uuid']);
        $this->deleteLabInstance($labInstance['uuid']);
    }

    private function logIn()
    {
        $crawler = $this->client->request('GET', '/login');

        // Start by testing if login page sucessfully loaded
        // echo $this->client->getResponse();
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->selectButton('submit')->form();

        $form['email'] = 'root@localhost';
        $form['password'] = 'admin';

        $crawler = $this->client->submit($form);
    }

    private function logOut()
    {
        $this->client->request('GET', '/logout');
    }

    private function logInGuest()
    {
        $crawler = $this->client->request('GET', '/login');

        // Start by testing if login page sucessfully loaded
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->selectButton('submit')->form();

        $form['email'] = 'unittest@localhost';
        $form['password'] = 'P@sSW0rD_Un1t_T3st';

        $crawler = $this->client->submit($form);
        return $crawler;
    }
}