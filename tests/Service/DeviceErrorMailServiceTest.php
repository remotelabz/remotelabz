<?php

namespace App\Tests\Service;

use App\Entity\Device;
use App\Entity\DeviceInstance;
use App\Entity\Lab;
use App\Entity\LabInstance;
use App\Entity\User;
use App\Service\Instance\DeviceErrorMailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class DeviceErrorMailServiceTest extends TestCase
{
    private function createTwig(): Environment
    {
        return new Environment(new FilesystemLoader(__DIR__.'/../../templates'));
    }

    private function createDeviceInstance(): DeviceInstance
    {
        $device = (new Device())->setName('Switch');
        $lab = (new Lab())->setName('Networking 101');
        $labInstance = (new LabInstance())->setLab($lab);
        $user = (new User())
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('john@example.org');

        $instance = new DeviceInstance();
        $instance->setDevice($device);
        $instance->setLabInstance($labInstance);
        $instance->setUser($user);
        $instance->setState('starting');

        return $instance;
    }

    public function testDeviceErrorSendsMailToContactAddress(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $sent = null;
        $mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$sent) {
                $sent = $email;
            });

        $service = new DeviceErrorMailService($mailer, $this->createTwig(), new NullLogger(), 'contact@example.org', 'RemoteLabz');

        $service->sendDeviceErrorNotification($this->createDeviceInstance(), [
            'error_message' => 'KVM boot failed',
            'state' => 'start',
            'worker_ip' => '10.0.0.2',
            'error_code' => 1,
        ]);

        $this->assertInstanceOf(Email::class, $sent);
        $this->assertSame(['contact@example.org'], array_map(static fn ($a) => $a->getAddress(), $sent->getTo()));
        $this->assertSame(['contact@example.org'], array_map(static fn ($a) => $a->getAddress(), $sent->getFrom()));
        $this->assertStringContainsString('Switch', $sent->getSubject());

        $html = (string) $sent->getHtmlBody();
        $this->assertStringContainsString('KVM boot failed', $html);
        $this->assertStringContainsString('Networking 101', $html);
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('10.0.0.2', $html);
    }

    public function testNoMailWhenContactAddressIsEmpty(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $service = new DeviceErrorMailService($mailer, $this->createTwig(), new NullLogger(), '', 'RemoteLabz');
        $service->sendDeviceErrorNotification(new DeviceInstance());
    }

    public function testMailFailureIsSwallowed(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('SMTP down'));

        $service = new DeviceErrorMailService($mailer, $this->createTwig(), new NullLogger(), 'contact@example.org', 'RemoteLabz');
        $service->sendDeviceErrorNotification($this->createDeviceInstance());

        $this->addToAssertionCount(1);
    }
}
