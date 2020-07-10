<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    use ControllerTestTrait, UserControllerTestTrait;

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

    public function testCreateUser()
    {
        $this->logIn();
        $this->createUser('unittest@localhost',
            'P@sSW0rD_Un1t_T3st',
            'LastName',
            'FirstName',
            'ROLE_USER'
        );
    }

    /**
     * @depends testCreateUser
     */
    public function testBlockUser()
    {
        $userInfos = $this->getGuestInfo();
        $this->logIn();
        $this->toggleBlockUser($userInfos['id']);

        $crawler = $this->logInGuest();
        $this->assertSame(1, $crawler->filter('.alert.alert-danger')->count());
        
        return $userInfos['id'];
    }

    /**
     * @depends testBlockUser
     */
    public function testUnblockUser($userId)
    {
        $this->logIn();
        $this->toggleBlockUser($userId);

        $crawler = $this->logInGuest();
        $this->assertSame(0, $crawler->filter('.alert.alert-danger')->count());
    }

    /**
     * @depends testCreateUser
     */
    public function testDeleteUser()
    {
        $userInfos = $this->getGuestInfo();
        $this->logIn();

        $this->deleteUser($userInfos['id']);
    }
}