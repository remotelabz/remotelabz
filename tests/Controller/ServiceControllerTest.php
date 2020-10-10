<?php

namespace App\Tests\Controller;

class ServiceControllerTest extends AuthenticatedWebTestCase
{
    public function testIndex()
    {
        $this->client->request('GET', '/admin/services');

        $this->assertResponseIsSuccessful();
    }
}