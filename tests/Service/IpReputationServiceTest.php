<?php

namespace App\Tests\Service;

use App\Entity\IpReputation;
use App\Entity\User;
use App\Repository\IpReputationRepository;
use App\Repository\UserRepository;
use App\Service\IpReputationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

class IpReputationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private IpReputationRepository&MockObject $reputationRepository;
    private UserRepository&MockObject $userRepository;
    private MailerInterface&MockObject $mailer;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->reputationRepository = $this->createMock(IpReputationRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
    }

    public function testAlertIsSentWhenScoreIsAboveThreshold(): void
    {
        $this->reputationRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('flush');
        $this->userRepository->method('findByRole')->willReturn([$this->createAdministrator('admin@example.org')]);

        $sent = null;
        $this->mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$sent) {
                $sent = $email;
            });

        $service = $this->createService(apiKey: 'key', score: 55);
        $reputation = $service->getReputation('203.0.113.10');

        $this->assertNotNull($reputation);
        $this->assertSame(55, $reputation->getAbuseScore());
        $this->assertNotNull($reputation->getLastAlertedAt());
        $this->assertNotNull($sent);
        $this->assertSame(['admin@example.org'], $sent->getTo() ? array_map(static fn ($a) => $a->getAddress(), $sent->getTo()) : []);
        $this->assertStringContainsString('203.0.113.10', $sent->getSubject());
        $this->assertStringContainsString('55', $sent->getSubject());
    }

    public function testNoAlertWhenScoreIsBelowThreshold(): void
    {
        $this->reputationRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('flush');
        $this->userRepository->method('findByRole')->willReturn([$this->createAdministrator('admin@example.org')]);
        $this->mailer->expects($this->never())->method('send');

        $service = $this->createService(apiKey: 'key', score: 5);
        $reputation = $service->getReputation('203.0.113.10');

        $this->assertNotNull($reputation);
        $this->assertSame(5, $reputation->getAbuseScore());
        $this->assertNull($reputation->getLastAlertedAt());
    }

    public function testNoAlertDuringCooldown(): void
    {
        $reputation = new IpReputation();
        $reputation->setIp('203.0.113.10');
        $reputation->setCreatedAt(new \DateTime('-1 day'));
        $reputation->setLastCheckedAt(new \DateTime('-2 days'));
        $reputation->setAbuseScore(90);
        $alertedAt = new \DateTime('-1 hour');
        $reputation->setLastAlertedAt($alertedAt);

        $this->reputationRepository->method('findOneBy')->willReturn($reputation);
        $this->entityManager->method('flush');
        $this->mailer->expects($this->never())->method('send');

        $service = $this->createService(apiKey: 'key', score: 90);
        $result = $service->getReputation('203.0.113.10');

        $this->assertSame($reputation, $result);
        $this->assertSame($alertedAt, $reputation->getLastAlertedAt());
    }

    public function testNoAlertWithoutFreshData(): void
    {
        $reputation = new IpReputation();
        $reputation->setIp('203.0.113.10');
        $reputation->setCreatedAt(new \DateTime('-1 day'));
        $reputation->setLastCheckedAt(new \DateTime('-2 days'));
        $reputation->setAbuseScore(90);

        $this->reputationRepository->method('findOneBy')->willReturn($reputation);
        $this->entityManager->method('flush');
        $this->mailer->expects($this->never())->method('send');

        $service = $this->createService(apiKey: '', score: 90);
        $result = $service->getReputation('203.0.113.10');

        $this->assertSame($reputation, $result);
        $this->assertNull($reputation->getLastAlertedAt());
    }

    public function testNoAlertWhenThereIsNoAdministratorRecipient(): void
    {
        $this->reputationRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('flush');
        $this->userRepository->method('findByRole')->willReturn([]);
        $this->mailer->expects($this->never())->method('send');

        $service = $this->createService(apiKey: 'key', score: 80);
        $reputation = $service->getReputation('203.0.113.10');

        $this->assertNotNull($reputation);
        $this->assertNull($reputation->getLastAlertedAt());
    }

    private function createService(string $apiKey, int $score): IpReputationService
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'data' => [
                    'ipAddress' => '203.0.113.10',
                    'abuseConfidenceScore' => $score,
                    'totalReports' => 12,
                    'countryCode' => 'FR',
                ],
            ]))
        );

        return new IpReputationService(
            $this->entityManager,
            $this->reputationRepository,
            $this->userRepository,
            $this->mailer,
            $this->createTwig(),
            $apiKey,
            86400,
            'https://api.abuseipdb.com/api/v2/check',
            'https://www.abuseipdb.com/check',
            'noreply@example.org',
            'RemoteLabz',
            20,
            604800,
            $httpClient
        );
    }

    private function createTwig(): Environment
    {
        $twig = new Environment(new ArrayLoader([
            'emails/ip_reputation_alert.html.twig' => 'ip={{ ip }} score={{ score }}',
        ]));
        $twig->addFunction(new TwigFunction('url', static fn (string $route): string => 'https://localhost/' . $route));

        return $twig;
    }

    private function createAdministrator(string $email): User
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMINISTRATOR']);
        $user->setEmail($email);

        return $user;
    }
}
