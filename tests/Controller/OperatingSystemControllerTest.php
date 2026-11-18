<?php

namespace App\Tests\Controller;

class OperatingSystemControllerTest extends AuthenticatedWebTestCase
{
    private function getOperatingSystemByName($name)
    {
        // The API truncates its payload (JMS max depth), so the id is resolved
        // from the list page links instead
        $crawler = $this->client->request('GET', '/admin/operating-systems?search='.rawurlencode($name));
        $this->assertResponseIsSuccessful();

        foreach ($crawler->filter('a[href*="/admin/operating-systems/"]') as $link) {
            $href = $link->getAttribute('href');
            if ($name === trim($link->textContent) && preg_match('#/admin/operating-systems/(\d+)(?:$|\?)#', $href, $matches)) {
                return (int) $matches[1];
            }
        }

        $this->fail('Operating system "'.$name.'" not found in the list');
    }

    public function testCreateOperatingSystem()
    {
        $this->client->followRedirects();

        // A unique name per run so that the test stays idempotent even when
        // a previous run was interrupted before the OS could be deleted
        $name = 'OS - Test '.bin2hex(random_bytes(3));

        $crawler = $this->client->request('GET', '/admin/operating-systems/new');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('operating_system[submit]')->form();

        $form['operating_system[name]'] = $name;
        $form['operating_system[imageUrl]'] = 'http://urlto.qcow2';

        // The hypervisor field is required; the options are keyed by id, so
        // resolve the value of the "qemu" option from its label
        $hypervisorValue = null;
        foreach ($crawler->filter('select[name="operating_system[hypervisor]"] option') as $option) {
            if ('qemu' === trim($option->textContent)) {
                $hypervisorValue = $option->getAttribute('value');
                break;
            }
        }
        $this->assertNotNull($hypervisorValue, 'The qemu hypervisor must be proposed by the form');
        $form['operating_system[hypervisor]']->select($hypervisorValue);

        $crawler = $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        return ['id' => $this->getOperatingSystemByName($name), 'name' => $name];
    }

    /**
     * @depends testCreateOperatingSystem
     */
    public function testShowOperatingSystem(array $os)
    {
        $this->client->request('GET', '/admin/operating-systems/'.$os['id']);
        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testCreateOperatingSystem
     */
    public function testEditOperatingSystem(array $os)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/operating-systems/'.$os['id'].'/edit');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('operating_system[submit]')->form();

        $form['operating_system[name]'] = $os['name'].' Edited';
        $form['operating_system[imageUrl]'] = 'http://new-urlto.qcow2';

        $crawler = $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    /**
     * @depends testCreateOperatingSystem
     */
    public function testDeleteOperatingSystem(array $os)
    {
        $this->client->followRedirects();

        // The delete route only accepts a request carrying a valid CSRF
        // token, so the test goes through the show page delete form
        $crawler = $this->client->request('GET', '/admin/operating-systems/'.$os['id']);
        $this->assertResponseIsSuccessful();

        $crawler = $this->client->submit($crawler->selectButton('Delete Operating System')->form());
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}
