<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait LabControllerTestTrait
{
    protected function createLab()
    {
        $this->client->request('POST', '/api/labs');

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $tmp['id'] = $data['id'];
        $tmp['uuid'] = $data['uuid'];

        return $tmp;
    }

    protected function addDeviceToLab($labId, $device)
    {
        $data = json_encode($device);

        $this->client->request('POST', 
            '/api/labs/' . $labId . '/devices',
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            $data
        );
        //$this->assertSame(1, json_decode($this->client->getResponse()->getContent(), true));
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        return json_decode($this->client->getResponse()->getContent(), true);
    }
    
    protected function deleteLab($labId)
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/admin/labs/' . $labId . '/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}