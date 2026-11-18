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
        $collectedMessages = $mailCollector->getEvents()->getMessages();
        $this->assertCount(1, $collectedMessages);

        $message = $collectedMessages[0];

        // Asserting email data
        $this->assertInstanceOf(\Symfony\Component\Mime\Email::class, $message);
        $to = $message->getTo();
        $this->assertCount(1, $to);
        $this->assertSame('root@localhost', $to[0]->getAddress());
    }

    public function testPasswordResetErrorIfNoMatch()
    {
        $crawler = $this->client->request('GET', '/password/reset');

        $form = $crawler->selectButton('form[submit]')->form();
        $form['form[email]'] = 'thisaddressdoesnotexist@pokemon.joy';
        $crawler = $this->client->submit($form);

        // The same message is shown whatever the email exists or not
        // (anti user-enumeration)
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-info')->count());
    }
}