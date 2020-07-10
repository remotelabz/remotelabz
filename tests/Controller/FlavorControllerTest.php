<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FlavorControllerTest extends WebTestCase
{
    use ControllerTestTrait;

    public function testCreateFlavor()
    {
        $this->logIn();

        $form['name'] = 'x-test';
        $form['memory'] = '8192';
        $form['disk'] = '50';

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

    /**
     * @depends testCreateFlavor
     */
    public function testEditFlavor($id)
    {
        $this->logIn();
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/flavors/' . $id . '/edit');

        $form = $crawler->selectButton('flavor[submit]')->form();

        $form['flavor[name]'] = 'x-test-edited';
        $form['flavor[memory]'] = '2048';
        $form['flavor[disk]'] = '35';

        $crawler = $this->client->submit($form);

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        // Check that value changed
        $crawler = $this->client->request('GET', '/admin/flavors/' . $id . '/edit');
        $form = $crawler->selectButton('flavor[submit]')->form();

        $this->assertSame('x-test-edited', $form['flavor[name]']->getValue());
        $this->assertSame('2048', $form['flavor[memory]']->getValue());
        $this->assertSame('35', $form['flavor[disk]']->getValue());
    }

    /**
     * @depends testCreateFlavor
     */
    public function testDeleteFlavor($id)
    {
        $this->logIn();
        $crawler = $this->client->request('DELETE', '/api/flavors/' . $id);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }
}