<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait NetworkInterfaceControllerTestTrait
{
    protected function createNetworkInterface($name, $macAddress, $accessType = '', $deviceId = '', $isTemplate = '1')
    {
        $form['name'] = $name;
        $form['macAddress'] = $macAddress;
        $form['isTemplate'] = $isTemplate;

        if(!empty($accessType))
            $form['accessType'] = $accessType;
        if(!empty($deviceId))
            $form['device'] = $deviceId;

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

    protected function editNetworkInterface($id, $name, $macAddress, $accessType, $deviceId, $isTemplate)
    {
        $form['name'] = $name;
        $form['macAddress'] = $macAddress;
        $form['accessType'] = $accessType;
        $form['device'] = $deviceId;
        $form['isTemplate'] = $isTemplate;

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

    protected function deleteNetworkInterface($id)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/network-interfaces/' . $id . '/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}