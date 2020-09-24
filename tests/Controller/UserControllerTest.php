<?php

namespace App\Tests\Controller;

class UserControllerTest extends AuthenticatedWebTestCase
{
    protected function getGuestInfo()
    {
        $this->logOut();
        $this->logInGuest();

        return $this->getUserInfo();
    }

    protected function getUserInfo()
    {
        $this->client->request('GET', '/api/users/me');
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->logOut();

        return $data['id'];
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

    public function testCreateUser()
    {
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
