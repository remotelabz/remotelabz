<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    use ControllerTestTrait;

    public function testPasswordResetGetAction()
    {
        $this->client->request('GET', '/password/reset');

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testPasswordResetPostAction()
    {
        $crawler = $this->client->request('GET', '/password/reset');

        $this->client->enableProfiler();

        $form = $crawler->selectButton('form[submit]')->form();
        $form['form[email]'] = 'root@localhost';
        $crawler = $this->client->submit($form);
        
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-info')->count());

        $mailCollector = $this->client->getProfile()->getCollector('mailer');

        // checks that an email was sent
        $this->assertSame(1, $mailCollector->getMessageCount());

        $collectedMessages = $mailCollector->getMessages();
        $message = $collectedMessages[0];

        // Asserting email data
        $this->assertInstanceOf('Swift_Message', $message);
        $this->assertSame('root@localhost', key($message->getTo()));
    }

    public function testPasswordResetErrorIfNoMatch()
    {
        $crawler = $this->client->request('GET', '/password/reset');

        $form = $crawler->selectButton('form[submit]')->form();
        $form['form[email]'] = 'thisaddressdoesnotexist@pokemon.joy';
        $crawler = $this->client->submit($form);

        $this->assertSame(1, $crawler->filter('.flash-notice.alert-danger')->count());
    }
}