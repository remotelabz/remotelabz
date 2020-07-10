<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait UserControllerTestTrait
{
    protected function createUser($email, $password, $lastName, $firstName, $roles)
    {
        $crawler = $this->client->request('GET', '/admin/users/new');

        $this->client->enableProfiler();
        $this->client->followRedirects();

        $form = $crawler->selectButton('user[submit]')->form();

        $form['user[email]'] = $email;
        $form['user[password]'] = $password;
        $form['user[confirmPassword]'] = $password;
        $form['user[lastName]'] = $lastName;
        $form['user[firstName]'] = $firstName;
        $form['user[roles]']->select($roles);

        $crawler = $this->client->submit($form);

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    protected function toggleBlockUser($userId)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/admin/users/' . $userId . '/toggle');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

    protected function deleteUser($userId)
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('DELETE', '/admin/users/' . $userId);
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());
    }

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
        $infos['id'] = $data['id'];
        $infos['uuid'] = $data['uuid'];
        $this->logOut();
        return $infos;
    }
}