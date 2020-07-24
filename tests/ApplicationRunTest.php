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
        
        $entities = $this->createEntities();

        // Start tests for user
        $this->launchLabAndDeviceTests($entities['labInfos']['uuid'], $entities['adminInfos']['uuid'], 'user');
        
        // Start tests for group
        $this->launchLabAndDeviceTests($entities['labInfos']['uuid'], $entities['groupUuid'], 'group');

        // Change user (guest) from a user to a group admin
        $this->removeUserFromGroup('test-group',  $entities['userInfos']['id']);
        $this->addUserToGroup('test-group', $entities['userInfos']['id'], 'admin');

        $this->logOut();
        $this->logInGuest();
        
        // Test to create a lab instance being a group admin
        $this->launchLabAndDeviceTests($entities['labInfos']['uuid'], $entities['groupUuid'], 'group');

        $this->logOut();
        $this->logIn();
        // Wait Instance to be correctly removed and then clean all
        sleep(5);
        $this->deleteEntities($entities);
    }

    private function createEntities()
    {
        $ret = array();

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
        $this->addUserToGroup('test-group', $userInfos['id'], 'user');

        // Start lab instance by user (admin)
        $adminInfos = $this->getUserInfo();
        $this->logIn();
        
        $networkInterfaceId = $this->createNetworkInterface('ToAlpine2', '52:54:00:54:54:54', 'VNC', $deviceInLab['id'], '1');
    
        $ret['osId'] = $osId;
        $ret['flavorId'] = $flavorId;
        $ret['device'] = $device;
        $ret['labInfos'] = $labInfos;
        $ret['deviceInLab'] = $deviceInLab;
        $ret['userInfos'] = $userInfos;
        $ret['groupUuid'] = $groupUuid;
        $ret['adminInfos'] = $adminInfos;
        $ret['networkInterfaceId'] = $networkInterfaceId;

        return $ret;
    }

    private function launchLabAndDeviceTests($labUuid, $instancierUuid, $instancierType)
    {
        $labInstance = $this->createLabInstance($labUuid, $instancierUuid, $instancierType);
        $this->startDeviceInstance($labInstance['deviceInstances'][0]['uuid']);
        $this->stopDeviceInstance($labInstance['deviceInstances'][0]['uuid']);
        $this->deleteLabInstance($labInstance['uuid']);
    }

    private function deleteEntities(array $entities)
    {
        $this->deleteGroup('test-group');
        $this->deleteUser($entities['userInfos']['id']);
        $this->deleteNetworkInterface($entities['networkInterfaceId']);
        $this->deleteDevice($entities['deviceInLab']['id']);
        $this->deleteLab($entities['labInfos']['id']);
        $this->deleteDevice($entities['device']['id']);
        $this->deleteFlavor($entities['flavorId']);
        $this->deleteOperatingSystem($entities['osId']);
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