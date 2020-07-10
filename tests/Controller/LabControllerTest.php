<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LabControllerTest extends WebTestCase
{
    use ControllerTestTrait;

    public function testCreateNewLab()
    {
        $this->logIn();
        $this->client->request('POST', '/api/labs');

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        //$this->labUuid = $data['uuid'];
        $labId = $data['id'];

        return $labId;
    }

    /**
     * @depends testCreateNewLab
     */
    public function testDeleteLab($labId)
    {
        $this->logIn();
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/admin/labs/' . $labId . '/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}