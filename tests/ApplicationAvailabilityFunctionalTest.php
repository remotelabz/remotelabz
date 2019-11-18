<?php

namespace App\Tests;

use App\Entity\User;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

class ApplicationAvailabilityFunctionalTest extends WebTestCase
{
    private $client = null;

    public function setUp()
    {
        $this->client = static::createClient();
    }

    /**
     * @dataProvider urlProvider
     */
    public function testPageIsSuccessful($url)
    {
        $this->logIn();
        $this->client->request('GET', $url);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function urlProvider()
    {
        yield ['/login'];
        yield ['/password/reset'];

        yield ['/admin/users'];
        yield ['/admin/courses'];
        yield ['/admin/flavors'];
        yield ['/admin/network-settings'];
        yield ['/admin/network-interfaces'];
        yield ['/admin/operating-systems'];
        yield ['/admin/instances'];

        yield ['/activities'];
        yield ['/devices'];
        yield ['/profile'];
    }

    private function logIn()
    {
        $crawler = $this->client->request('GET', '/login');

        // Start by testing if login page sucessfully loaded
        // echo $this->client->getResponse();
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->selectButton('submit')->form();

        $form['email'] = 'root@localhost';
        $form['password'] = 'admin';

        $crawler = $this->client->submit($form);
    }
}
