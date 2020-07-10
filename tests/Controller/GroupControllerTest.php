<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GroupControllerTest extends WebTestCase
{
    use ControllerTestTrait;

    public function testAddNewGroup()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/groups/new');
        
        $this->client->enableProfiler();
        $this->client->followRedirects();

        $form = $crawler->selectButton('group[submit]')->form();

        $form['group[name]'] = "Test Group";
        $form['group[slug]'] = "test-group";
        $form['group[description]'] = "That's a description";
        $form['group[visibility]']->select('1');

        $crawler = $this->client->submit($form);

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

    }

    // ToDo
    /*
    public function testAddUserToGroup()
    {
    }
    */

    public function testDeleteGroup()
    {
        $this->logIn();
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/groups/test-group/delete');
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

    }
}