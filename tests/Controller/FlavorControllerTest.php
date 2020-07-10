<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FlavorControllerTest extends WebTestCase
{
    use ControllerTestTrait, FlavorControllerTestTrait;

    public function testCreateFlavor()
    {
        $this->logIn();
        return $this->createFlavor('x-test', '8192', '50');
    }

    /**
     * @depends testCreateFlavor
     */
    public function testEditFlavor($id)
    {
        $this->logIn();
        $this->editFlavor($id, 'x-test-edited', '2048', '35');

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
        $this->deleteFlavor($id);
    }
}