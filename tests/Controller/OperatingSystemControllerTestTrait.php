<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait OperatingSystemControllerTestTrait
{
    protected function getOperatingSystemByName($name)
    {
        $this->client->request('GET', '/api/operating-systems?search=' . $name);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        return $data[0]['id'];
    }

    protected function createOperatingSystem($name, $imageUrl)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/new');
        $form = $crawler->selectButton('operating_system[submit]')->form();

        $form['operating_system[name]'] = $name;
        $form['operating_system[imageUrl]'] = $imageUrl;
        
        $crawler = $this->client->submit($form);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        return $this->getOperatingSystemByName($name);
    }

    protected function editOperatingSystem($id, $name, $imageUrl)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/' . $id . '/edit');
        $form = $crawler->selectButton('operating_system[submit]')->form();

        $form['operating_system[name]'] = $name;
        $form['operating_system[imageUrl]'] = $imageUrl;
        
        $crawler = $this->client->submit($form);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    protected function deleteOperatingSystem($id)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/' . $id . '/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}