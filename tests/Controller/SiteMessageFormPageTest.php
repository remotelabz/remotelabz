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
        // (Encore appends a hash to the asset names)
        $this->assertMatchesRegularExpression('#/build/site-message-form\.[a-f0-9]{8,}\.css#', $html);
        $this->assertMatchesRegularExpression('#/build/site-message-form\.[a-f0-9]{8,}\.js#', $html);
        $this->assertStringContainsString('id="site_message_message"', $html);
        $this->assertStringContainsString('site-message-required-text', $html);
    }
}
