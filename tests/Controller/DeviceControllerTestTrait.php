<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait DeviceControllerTestTrait
{
    protected function createDevice($name, $brand, $model, $osId, $flavorId, $isTemplate)
    {
        $form['name'] = $name;
        $form['brand'] = $brand;
        $form['model'] = $model;
        $form['operatingSystem'] = $osId;
        $form['flavor'] = $flavorId;
        $form['isTemplate'] = $isTemplate;

        $data = json_encode($form);

        $this->client->request('POST', 
            '/api/devices',
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            $data
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $device = json_decode($this->client->getResponse()->getContent(), true);
        return $device;
    }

    protected function getDeviceByName($name)
    {
        $this->client->request('GET', '/api/devices?search=' . $name . '&template=true');
        
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $device = json_decode($this->client->getResponse()->getContent(), true);
        return $device[0];
    }

    protected function editDevice($deviceId, $name, $brand, $model, $osId, $flavorId, $isTemplate)
    {
        $form['name'] = $name;
        $form['brand'] = $brand;
        $form['model'] = $model;
        $form['operatingSystem'] = $osId;
        $form['flavor'] = $flavorId;
        $form['isTemplate'] = $isTemplate;

        $data = json_encode($form);

        $this->client->request('PUT', 
            '/api/devices/' . $deviceId,
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            $data
        );
        
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->client->request('GET', '/admin/devices/' . $deviceId . '/edit');
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    protected function deleteDevice($deviceId)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/devices/' . $deviceId . '/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}