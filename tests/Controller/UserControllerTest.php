<?php

namespace App\Tests\Controller;

class UserControllerTest extends AuthenticatedWebTestCase
{
    /**
     * /api/users/me ends up on /api/users/{id}, which is only reachable with
     * a JWT, so the id of the freshly created user is resolved while the
     * administrator session is still active.
     */
    protected function getGuestInfo()
    {
        $userId = $this->getUserIdByEmail('unittest@localhost');

        // Then only check that the created user can log in
        $this->logOut();
        $this->logInGuest();
        $this->logOut();

        return $userId;
    }

    private function getUserIdByEmail($email)
    {
        $this->client->request('GET', '/api/users?search='.rawurlencode($email).'&limit=100');
        $this->assertResponseIsSuccessful();
        // An empty search result is a 204 with an empty body
        $users = json_decode((string)$this->client->getResponse()->getContent(), true);
        $this->assertIsArray($users, 'The user "'.$email.'" must exist');

        foreach ($users as $user) {
            if ($email === ($user['email'] ?? null)) {
                return $user['id'];
            }
        }

        $this->fail('User "'.$email.'" not found');
    }

    public function testIndexGetAction()
    {
        $this->client->request('GET', '/admin/users');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexPostAction()
    {
        $this->client->request('POST', '/admin/users');
        $this->assertResponseIsSuccessful();
    }

    /**
     * The tests share the live database, so a user left over from a previous
     * run (when it failed before the delete step) would block the creation.
     * Removing it first keeps the test idempotent.
     */
    private function deleteUserByEmail($email)
    {
        $this->client->request('GET', '/api/users?search='.rawurlencode($email).'&limit=100');
        $this->assertResponseIsSuccessful();
        // An empty search result is a 204 with an empty body: nothing to delete
        $users = json_decode((string)$this->client->getResponse()->getContent(), true);
        if (!is_array($users)) {
            return;
        }

        foreach ($users as $user) {
            if ($email === ($user['email'] ?? null)) {
                $this->client->request('DELETE', '/admin/users/'.$user['id']);
                $this->assertResponseIsSuccessful();
                return;
            }
        }
    }

    public function testCreateUser()
    {
        $this->deleteUserByEmail('unittest@localhost');

        $crawler = $this->client->request('GET', '/admin/users/new');
        $this->assertResponseIsSuccessful();

        $this->client->enableProfiler();
        $this->client->followRedirects();

        $form = $crawler->selectButton('user[submit]')->form();

        $form['user[email]'] = 'unittest@localhost';
        $form['user[password]'] = 'P@sSW0rD_Un1t_T3st';
        $form['user[confirmPassword]'] = 'P@sSW0rD_Un1t_T3st';
        $form['user[lastName]'] = 'LastName';
        $form['user[firstName]'] = 'FirstName';
        $form['user[roles]']->select('ROLE_USER');

        $crawler = $this->client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        return $this->getGuestInfo();
    }

    /**
     * @depends testCreateUser
     */
    public function testBlockUser($userId)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/users/'.$userId.'/toggle');
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        $this->logOut();
        $crawler = $this->logInGuest();
        $this->assertSame(1, $crawler->filter('.alert.alert-danger')->count());

        return $userId;
    }

    /**
     * @depends testBlockUser
     */
    public function testUnblockUser($userId)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/users/'.$userId.'/toggle');
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        $this->logOut();
        $crawler = $this->logInGuest();
        $this->assertSame(0, $crawler->filter('.alert.alert-danger')->count());
    }

    /**
     * @depends testCreateUser
     */
    public function testDeleteUser($userId)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('DELETE', '/admin/users/'.$userId);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }
}
