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

    public function setUp()
    {
        $this->client = WebTestCase::createClient();
    }

    protected function logIn()
    {
        $crawler = $this->client->request('GET', '/login');

        // Start by testing if login page sucessfully loaded
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->selectButton('submit')->form();

        $form['email'] = 'root@localhost';
        $form['password'] = 'admin';

        $crawler = $this->client->submit($form);
    }
}