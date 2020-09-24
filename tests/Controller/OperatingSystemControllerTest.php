<?php

namespace App\Tests\Controller;

class OperatingSystemControllerTest extends AuthenticatedWebTestCase
{
    private function getOperatingSystemByName($name)
    {
        $this->client->request('GET', '/api/operating-systems?search='.$name);
        $data = json_decode($this->client->getResponse()->getContent(), true);

        return $data[0]['id'];
    }

    public function testCreateOperatingSystem()
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/new');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('operating_system[submit]')->form();

        $form['operating_system[name]'] = 'OS - Test';
        $form['operating_system[imageUrl]'] = 'http://urlto.img';

        $crawler = $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        return $this->getOperatingSystemByName('OS - Test');
    }

    /**
     * @depends testCreateOperatingSystem
     */
    public function testShowOperatingSystem($id)
    {
        $this->client->request('GET', '/admin/operating-systems/'.$id);
        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testCreateOperatingSystem
     */
    public function testEditOperatingSystem($id)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/'.$id.'/edit');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('operating_system[submit]')->form();

        $form['operating_system[name]'] = 'OS - Test Edited';
        $form['operating_system[imageUrl]'] = 'http://new-urlto.img';

        $crawler = $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    /**
     * @depends testCreateOperatingSystem
     */
    public function testDeleteOperatingSystem($id)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/'.$id.'/delete');
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}
