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

    /**
     * The shared database does not guarantee that a given user id exists, so
     * the test creates a dedicated user (unique email), adds it to the group
     * and returns its id for the remove step.
     */
    public function testAddUserToGroup()
    {
        $this->client->followRedirects();

        $email = 'group-test-'.bin2hex(random_bytes(3)).'@localhost';

        $crawler = $this->client->request('GET', '/admin/users/new');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('user[submit]')->form();
        $form['user[email]'] = $email;
        $form['user[password]'] = 'P@sSW0rD_Un1t_T3st';
        $form['user[confirmPassword]'] = 'P@sSW0rD_Un1t_T3st';
        $form['user[lastName]'] = 'Group';
        $form['user[firstName]'] = 'Test';
        $form['user[roles]']->select('ROLE_USER');
        $crawler = $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        $this->client->request('GET', '/api/users?search='.rawurlencode($email));
        $this->assertResponseIsSuccessful();
        $users = json_decode($this->client->getResponse()->getContent(), true);
        $userId = null;
        foreach ($users as $user) {
            if ($email === ($user['email'] ?? null)) {
                $userId = $user['id'];
            }
        }
        $this->assertNotNull($userId, 'The dedicated user must exist');

        $slug = 'default-group';
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

        return $userId;
    }

    /**
     * @depends testAddUserToGroup
     */
    public function testRemoveUserFromGroup($userId)
    {
        $this->client->followRedirects();

        $slug = 'default-group';

        $crawler = $this->client->request('GET', '/groups/'.$slug.'/user/'.$userId.'/delete');
        $this->assertResponseIsSuccessful();

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        // Cleanup: remove the dedicated user created by testAddUserToGroup
        $this->client->request('DELETE', '/admin/users/'.$userId);
        $this->assertResponseIsSuccessful();
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
