<?php

namespace App\Tests\Controller;

class GroupControllerTest extends AuthenticatedWebTestCase
{
    public function testCreateGroup()
    {
        $form['name'] = 'Test Group';
        $form['slug'] = 'test-group';
        $form['description'] = 'That\'s a description';
        $form['visibility'] = 1;

        $data = json_encode($form);

        $this->client->request(
            'POST',
            '/api/groups',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data
        );

        $this->assertResponseIsSuccessful();

        $group = json_decode($this->client->getResponse()->getContent(), true);

        return $group['uuid'];
    }

    public function testAddUserToGroup()
    {
        $this->client->followRedirects();

        $slug = 'default-group';
        $userId = 2;
        $role = 'admin';

        $crawler = $this->client->request(
            'POST',
            '/groups/'.$slug.'/users',
            [
                'users' => [
                    0 => $userId,
                ],
                'role' => $role,
            ]
        );
        $this->assertResponseIsSuccessful();

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    /**
     * @depends testAddUserToGroup
     */
    public function testRemoveUserFromGroup()
    {
        $this->client->followRedirects();

        $slug = 'default-group';
        $userId = 2;

        $crawler = $this->client->request('GET', '/groups/'.$slug.'/user/'.$userId.'/delete');
        $this->assertResponseIsSuccessful();

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    /**
     * @depends testCreateGroup
     */
    public function testDeleteGroup()
    {
        $this->client->followRedirects();
        $slug = 'test-group';
        $crawler = $this->client->request('GET', '/groups/'.$slug.'/delete');
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}
