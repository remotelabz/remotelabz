<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthenticatedWebTestCase extends WebTestCase
{
    /**
     * @var Client Client instance for functional tests
     */
    protected $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $this->logIn();
    }

    protected function logIn()
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();

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

        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('submit')->form();

        $form['email'] = 'unittest@localhost';
        $form['password'] = 'P@sSW0rD_Un1t_T3st';

        $crawler = $this->client->submit($form);

        return $crawler;
    }
}
