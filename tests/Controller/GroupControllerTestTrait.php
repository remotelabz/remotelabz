<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait GroupControllerTestTrait
{
    protected function createGroup($name, $slug, $description, $visibility)
    {
        $form['name'] = $name;
        $form['slug'] = $slug;
        $form['description'] = $description;
        $form['visibility'] = $visibility;

        $data = json_encode($form);

        $this->client->request('POST', 
            '/api/groups',
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            $data
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $group = json_decode($this->client->getResponse()->getContent(), true);

        return $group['uuid'];
    }

    protected function addUserToGroup($slug, $userId)
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('POST',
            '/groups/' . $slug . '/users',
            array(
                'users' => array(
                    0 => $userId
                )
            )
        );

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    protected function deleteUserFromGroup($slug, $userId)
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/groups/' . $slug . '/user/' . $userId . '/delete');

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    protected function deleteGroup($slug)
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/groups/' . $slug . '/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}