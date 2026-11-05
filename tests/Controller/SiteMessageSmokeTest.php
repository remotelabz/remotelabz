<?php

namespace App\Tests\Controller;

use App\Entity\SiteMessage;
use App\Form\SiteMessageType;

class SiteMessageSmokeTest extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{
    public function testTypeChoicesMap()
    {
        $kernel = self::bootKernel();
        $formFactory = $kernel->getContainer()->get('test.service_container')->get('form.factory');
        $message = new SiteMessage();
        $form = $formFactory->create(SiteMessageType::class, $message, ['csrf_protection' => false, 'admin' => true]);
        $typeView = $form->createView()->children['type']->vars;

        $options = [];
        foreach ($typeView['choices'] as $choice) {
            $options[$choice->value] = $choice->label;
        }

        $this->assertArrayHasKey(SiteMessage::TYPE_GENERAL, $options);
        $this->assertArrayHasKey(SiteMessage::TYPE_INFORMATION, $options);
        $this->assertSame('General (login page)', $options[SiteMessage::TYPE_GENERAL]);
        $this->assertSame('Information (connected users)', $options[SiteMessage::TYPE_INFORMATION]);

        // Editors only see the information type
        $editorForm = $formFactory->create(SiteMessageType::class, new SiteMessage(), ['csrf_protection' => false]);
        $editorOptions = [];
        foreach ($editorForm->createView()->children['type']->vars['choices'] as $choice) {
            $editorOptions[$choice->value] = $choice->label;
        }
        $this->assertArrayNotHasKey(SiteMessage::TYPE_GENERAL, $editorOptions);
        $this->assertArrayHasKey(SiteMessage::TYPE_INFORMATION, $editorOptions);
    }

    public function testTargetingLogic()
    {
        // No targets: shown to everyone
        $message = new SiteMessage();
        $message->setType(SiteMessage::TYPE_INFORMATION);
        $this->assertTrue($message->targetsUser(1, [2]));

        // Targeted by user id
        $message = new SiteMessage();
        $message->setType(SiteMessage::TYPE_INFORMATION);
        $message->setTargetUserIds([4]);
        $this->assertTrue($message->targetsUser(4, []));
        $this->assertFalse($message->targetsUser(2, []));

        // Targeted by group id (user group ids include parents)
        $message = new SiteMessage();
        $message->setType(SiteMessage::TYPE_INFORMATION);
        $message->setTargetGroupIds([10]);
        $this->assertTrue($message->targetsUser(2, [2, 8, 10]));
        $this->assertFalse($message->targetsUser(2, [2, 8]));

        // General messages are not part of the information banner targeting
        $general = new SiteMessage();
        $general->setType(SiteMessage::TYPE_GENERAL);
        $this->assertTrue($general->targetsUser(1, []));
    }

    public function testSiteMessageToHtmlFilter()
    {
        $kernel = self::bootKernel();
        $extension = $kernel->getContainer()->get('test.service_container')->get('twig')->getExtension(\App\Twig\AppExtension::class);

        // Markdown, emoji shortcodes and single line breaks are rendered
        $html = $extension->siteMessageToHtml("Hello **world** :smile:\nSecond line");
        $this->assertStringContainsString('<strong>world</strong>', $html);
        $this->assertStringContainsString('😄', $html);
        $this->assertStringContainsString('<br>', $html);

        // Raw HTML and unsafe links are neutralized
        $html = $extension->siteMessageToHtml('<script>alert(1)</script>[x](javascript:alert(1))');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        $this->assertSame('', $extension->siteMessageToHtml(null));
    }
}
