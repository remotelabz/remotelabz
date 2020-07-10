<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    use ControllerTestTrait;

    public function testIndexGetAction()
    {
        $this->logIn();
        $this->client->request('GET', '/admin/users');

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testIndexPostAction()
    {
        $this->logIn();
        $this->client->request('POST', '/admin/users');

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testAddNewUser()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/admin/users/new');

        $this->client->enableProfiler();
        $this->client->followRedirects();

        $form = $crawler->selectButton('user[submit]')->form();

        $form['user[email]'] = "unittest@localhost";
        $form['user[password]'] = "P@sSW0rD_Un1t_T3st";
        $form['user[confirmPassword]'] = "P@sSW0rD_Un1t_T3st";
        $form['user[lastName]'] = "LastName";
        $form['user[firstName]'] = "FirstName";
        $form['user[roles]']->select('ROLE_USER');

        $crawler = $this->client->submit($form);

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    /**
     * @depends testAddNewUser
     */
    public function testBlockUser()
    {
        $userId = $this->getGuestId();
        $this->logIn();

        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/users/' . $userId . '/toggle');
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
        $this->logIn();

        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/admin/users/' . $userId . '/toggle');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        $this->logOut();

        $this->logInGuest();
        $this->assertSame(0, $crawler->filter('.alert.alert-danger')->count());
    }

    /**
     * @depends testAddNewUser
     */
    public function testDeleteUser()
    {
        $userId = $this->getGuestId();
        $this->logIn();

        $this->client->followRedirects();

        $crawler = $this->client->request('DELETE', '/admin/users/' . $userId);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

    }

    private function getGuestId()
    {
        $this->logInGuest();
        $this->client->request('GET', '/api/users/me');
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $userId = $data['id'];
        $this->logOut();
        return $userId;
    }
}