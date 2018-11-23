<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use JMS\Serializer\SerializerBuilder;

class UserControllerTest extends WebTestCase
{
    /**
     * Create a client with a default Authorization header.
     *
     * @param string $username
     * @param string $password
     *
     * @return \Symfony\Bundle\FrameworkBundle\Client
     */
    protected function createJWTClient($username = 'root@localhost', $password = 'admin'): \Symfony\Bundle\FrameworkBundle\Client
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth', array(
            '_username' => $username,
            '_password' => $password
        ));

        $data = json_decode($client->getResponse()->getContent(), true);

        $client = static::createClient();
        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));

        return $client;
    }

    public function testListUsers()
    {
        $client = $this->createJWTClient();
        $client->request('GET', '/api/users');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }
}
