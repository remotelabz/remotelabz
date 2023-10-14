<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait ControllerTestTrait
{
    /**
     * @var Client $client Client instance for functional tests
     */
    protected $client;

    public function setUp(): void
    {
        $this->client = WebTestCase::createClient();
    }

    protected function logIn()
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/login');

        // Start by testing if login page sucessfully loaded
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->selectButton('submit')->form();

        $form['email'] = 'root@localhost';
        $form['password'] = 'admin';

        $crawler = $this->client->submit($form);

        $this->assertResponseIsSuccessful();
    }

    protected function logOut()
    {
        $this->client->request('GET', '/logout');
    }

    protected function logInGuest()
    {
        $crawler = $this->client->request('GET', '/login');

        // Start by testing if login page sucessfully loaded
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->selectButton('submit')->form();

        $form['email'] = 'unittest@localhost';
        $form['password'] = 'P@sSW0rD_Un1t_T3st';

        $crawler = $this->client->submit($form);
        return $crawler;
    }
}
