<?php

namespace App\Tests;

use App\Tests\Controller\AuthenticatedWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ApplicationAvailabilityFunctionalTest extends AuthenticatedWebTestCase
{
    /**
     * @dataProvider urlProvider
     */
    public function testPageIsSuccessful($url)
    {
        $this->client->request('GET', $url);
        $this->assertResponseIsSuccessful();
    }

    public function testPageIsError()
    {
        // The application converts every 404 into a redirect to the home page
        $this->client->followRedirects(false);

        $this->client->request('GET', '/nonexistentpage');
        $this->assertResponseRedirects('/');

        $this->client->followRedirects();
    }

    public function urlProvider()
    {
        yield ['/login'];
        yield ['/password/reset'];

        yield ['/admin/users'];
        yield ['/admin/flavors'];
        yield ['/admin/flavors/new'];
        yield ['/admin/network-settings'];
        yield ['/admin/network-settings/new'];
        yield ['/admin/network-interfaces'];
        yield ['/admin/operating-systems'];
        yield ['/admin/instances'];
        yield ['/admin/devices'];
        yield ['/admin/devices/new'];
        yield ['/admin/isos'];
        yield ['/admin/directories'];

        yield ['/profile'];
        yield ['/groups'];
    }
}
