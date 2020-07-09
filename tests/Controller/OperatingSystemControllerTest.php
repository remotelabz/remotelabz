<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OperatingSystemControllerTest extends WebTestCase
{
    use ControllerTestTrait;

    private function getOperatingSystemTest()
    {
        $this->logIn();
        $this->client->request('GET', '/api/operating-systems?search=OS%20-%20Test');
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        return $data[0]['id'];
    }

    public function testAddNewOperatingSystem()
    {
        $this->logIn();
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/new');
        $form = $crawler->selectButton('operating_system[submit]')->form();

        $form['operating_system[name]'] = 'OS - Test';
        $form['operating_system[imageUrl]'] = 'http://urlto.img';
        
        $crawler = $this->client->submit($form);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        return $this->getOperatingSystemTest();
    }

    /**
     * @depends testAddNewOperatingSystem
     */
    public function testShowOperatingSystem($id)
    {
        $this->logIn();
        $this->client->request('GET', '/admin/operating-systems/' . $id);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testAddNewOperatingSystem
     */
    public function testEditOperatingSystem($id)
    {
        $this->logIn();
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/' . $id . '/edit');
        $form = $crawler->selectButton('operating_system[submit]')->form();

        $form['operating_system[name]'] = 'OS - Test Edited';
        $form['operating_system[imageUrl]'] = 'http://new-urlto.img';
        
        $crawler = $this->client->submit($form);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    /**
     * @depends testAddNewOperatingSystem
     */
    public function testDeleteOperatingSystem($id)
    {
        $this->logIn();
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/' . $id . '/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}