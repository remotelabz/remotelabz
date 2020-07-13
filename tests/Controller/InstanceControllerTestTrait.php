<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait InstanceControllerTestTrait
{
    protected function createLabInstance($labUuid, $instancierUuid, $instancierType)
    {
        $this->client->request('POST', 
            '/api/instances/create',
            array(
                "lab" => $labUuid,
                "instancier" => $instancierUuid,
                "instancierType" => $instancierType
            )
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $tmp = json_decode($this->client->getResponse()->getContent(), true);

        $data = array();
        $data['uuid'] = $tmp['uuid'];
        $data['deviceInstances'] = $tmp['deviceInstances'];
        
        return $data;
    }

    protected function startDeviceInstance($deviceUuid)
    {
        $this->client->request('GET', '/api/instances/start/by-uuid/' . $deviceUuid);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * False positive due to javascript 
    */
    /*
    protected function viewDeviceInstance($deviceUuid)
    {
        $crawler = $this->client->request('GET', '/instances/' . $deviceUuid . '/view');
        $this->assertSame(0, $crawler->filter('.flash-notice.alert-danger')->count());
    }
    */

    protected function stopDeviceInstance($deviceUuid)
    {
        $this->client->request('GET', '/api/instances/stop/by-uuid/' . $deviceUuid);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    protected function deleteLabInstance($labInstanceUuid)
    {
        $this->client->request('DELETE', '/api/instances/' . $labInstanceUuid);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }
}