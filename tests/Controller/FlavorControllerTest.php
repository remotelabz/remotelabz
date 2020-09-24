<?php

namespace App\Tests\Controller;

class FlavorControllerTest extends AuthenticatedWebTestCase
{
    public function testCreateFlavor()
    {
        $form['name'] = 'x-test';
        $form['memory'] = '8192';
        $form['disk'] = '50';

        $data = json_encode($form);

        $this->client->request(
            'POST',
            '/api/flavors',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data
        );

        $this->assertResponseIsSuccessful();

        $flavor = json_decode($this->client->getResponse()->getContent(), true);

        return $flavor['id'];
    }

    /**
     * @depends testCreateFlavor
     */
    public function testEditFlavor($id)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/flavors/' . $id . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('flavor[submit]')->form();

        $form['flavor[name]'] = 'x-test-edited';
        $form['flavor[memory]'] = '2048';
        $form['flavor[disk]'] = '35';

        $crawler = $this->client->submit($form);

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        // Check that value changed
        $crawler = $this->client->request('GET', '/admin/flavors/' . $id . '/edit');
        $this->assertResponseIsSuccessful();
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
        $this->client->request('DELETE', '/api/flavors/' . $id);
        $this->assertResponseIsSuccessful();
    }
}
