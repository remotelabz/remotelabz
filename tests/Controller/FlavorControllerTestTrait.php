<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait FlavorControllerTestTrait
{
    protected function createFlavor($name, $memory, $disk)
    {
        $form['name'] = $name;
        $form['memory'] = $memory;
        $form['disk'] = $disk;

        $data = json_encode($form);

        $this->client->request('POST', 
            '/api/flavors',
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            $data
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $flavor = json_decode($this->client->getResponse()->getContent(), true);

        return $flavor['id'];
    }

    protected function editFlavor($flavorId, $name, $memory, $disk)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/flavors/' . $flavorId . '/edit');

        $form = $crawler->selectButton('flavor[submit]')->form();

        $form['flavor[name]'] = $name;
        $form['flavor[memory]'] = $memory;
        $form['flavor[disk]'] = $disk;

        $crawler = $this->client->submit($form);

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    protected function deleteFlavor($flavorId)
    {
        $this->client->request('DELETE', '/api/flavors/' . $flavorId);
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }
}