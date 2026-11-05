<?php

namespace App\Tests\Controller;

class SiteMessageFormPageTest extends AuthenticatedWebTestCase
{
    public function testMarkdownEditorAssetsAreLoaded()
    {
        $crawler = $this->client->request('GET', '/admin/messages/new');

        $this->assertResponseIsSuccessful();

        $html = $crawler->html();

        // The EasyMDE editor and its layout stylesheet are on the page
        $this->assertStringContainsString('/build/site-message-form.css', $html);
        $this->assertStringContainsString('/build/site-message-form.js', $html);
        $this->assertStringContainsString('id="site_message_message"', $html);
        $this->assertStringContainsString('site-message-required-text', $html);
    }
}
