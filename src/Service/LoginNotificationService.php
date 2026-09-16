<?php

namespace App\Service;

use App\Entity\LoginLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginNotificationService
{
    private $entityManager;
    private $mailer;
    private $twig;
    private $contactMail;

    public function __construct(
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        \Twig\Environment $twig,
        #[\SensitiveParameter] string $contactMail
    ) {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->contactMail = $contactMail;
    }

    public function logLogin(?User $user, string $email, string $ip, string $userAgent, string $loginType): void
    {
        $log = new LoginLog();
        $log->setUser($user);
        $log->setEmail($email);
        $log->setIp($ip);
        $log->setUserAgent($userAgent);

        $parsed = $this->parseUserAgent($userAgent);
        $log->setBrowser($parsed['browser']);
        $log->setOs($parsed['os']);
        $log->setLoginType($loginType);
        $log->setCreatedAt(new \DateTime());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function sendNotificationEmail(User $user, string $ip, string $userAgent, string $loginType, \DateTime $loginDate): void
    {
        $parsed = $this->parseUserAgent($userAgent);

        $loginTypeLabels = [
            'form' => 'Form login',
            'shibboleth' => 'Shibboleth SSO',
            'code' => 'Invitation code',
            'api' => 'API',
        ];

        $loginTypeLabel = $loginTypeLabels[$loginType] ?? $loginType;

        $email = (new Email())
            ->from($this->contactMail)
            ->to($user->getEmail())
            ->subject('Login detected on your RemoteLabz account')
            ->html(
                $this->twig->render('emails/login_notification.html.twig', [
                    'firstName' => $user->getFirstName(),
                    'email' => $user->getEmail(),
                    'loginDate' => $loginDate->format('d/m/Y H:i'),
                    'loginType' => $loginTypeLabel,
                    'ip' => $ip,
                    'browser' => $parsed['browser'],
                    'os' => $parsed['os'],
                ])
            );

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Failed to send login notification email: ' . $e->getMessage());
        }
    }

    public function shouldSendEmail(User $user, string $loginType, \DateTime $since): bool
    {
        $recentLogs = $this->entityManager->getRepository(LoginLog::class)
            ->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.login_type = :type')
            ->andWhere('l.created_at > :since')
            ->setParameter('user', $user)
            ->setParameter('type', $loginType)
            ->setParameter('since', $since)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return count($recentLogs) === 0;
    }

    private function parseUserAgent(string $userAgent): array
    {
        $browser = 'Unknown';
        $os = 'Unknown';

        if (empty($userAgent)) {
            return ['browser' => $browser, 'os' => $os];
        }

        $browser = $this->detectBrowser($userAgent);
        $os = $this->detectOS($userAgent);

        return ['browser' => $browser, 'os' => $os];
    }

    private function detectBrowser(string $userAgent): string
    {
        if (strpos($userAgent, 'Edg') !== false || strpos($userAgent, 'Edge') !== false) {
            preg_match('/Edg\/([\d.]+)/', $userAgent, $matches);
            return isset($matches[1]) ? 'Edge ' . $matches[1] : 'Edge';
        }

        if (strpos($userAgent, 'OPR') !== false || strpos($userAgent, 'Opera') !== false) {
            preg_match('/(?:OPR|Opera)\/([\d.]+)/', $userAgent, $matches);
            return isset($matches[1]) ? 'Opera ' . $matches[1] : 'Opera';
        }

        if (strpos($userAgent, 'Firefox') !== false) {
            preg_match('/Firefox\/([\d.]+)/', $userAgent, $matches);
            return isset($matches[1]) ? 'Firefox ' . $matches[1] : 'Firefox';
        }

        if (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Chromium') === false) {
            preg_match('/Chrome\/([\d.]+)/', $userAgent, $matches);
            return isset($matches[1]) ? 'Chrome ' . $matches[1] : 'Chrome';
        }

        if (strpos($userAgent, 'Chromium') !== false) {
            preg_match('/Chromium\/([\d.]+)/', $userAgent, $matches);
            return isset($matches[1]) ? 'Chromium ' . $matches[1] : 'Chromium';
        }

        if (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
            preg_match('/Version\/([\d.]+).*Safari/', $userAgent, $matches);
            return isset($matches[1]) ? 'Safari ' . $matches[1] : 'Safari';
        }

        if (strpos($userAgent, 'Android WebView') !== false) {
            preg_match('/Android WebView\/([\d.]+)/', $userAgent, $matches);
            return isset($matches[1]) ? 'Android WebView ' . $matches[1] : 'Android WebView';
        }

        if (strpos($userAgent, 'SamsungBrowser') !== false) {
            preg_match('/SamsungBrowser\/([\d.]+)/', $userAgent, $matches);
            return isset($matches[1]) ? 'Samsung Browser ' . $matches[1] : 'Samsung Browser';
        }

        if (strpos($userAgent, 'Firefox') !== false) {
            preg_match('/Firefox\/([\d.]+)/', $userAgent, $matches);
            return isset($matches[1]) ? 'Firefox ' . $matches[1] : 'Firefox';
        }

        return 'Unknown';
    }

    private function detectOS(string $userAgent): string
    {
        if (strpos($userAgent, 'Windows NT') !== false) {
            preg_match('/Windows NT ([\d.]+)/', $userAgent, $matches);
            $versionMap = [
                '10.0' => '10/11',
                '6.3' => '8.1',
                '6.2' => '8',
                '6.1' => '7',
                '6.0' => 'Vista',
                '5.1' => 'XP',
            ];
            $winVersion = $versionMap[$matches[1]] ?? $matches[1];
            return 'Windows ' . $winVersion;
        }

        if (preg_match('/Mac OS X ([\d_]+)/', $userAgent, $matches)) {
            $version = str_replace('_', '.', $matches[1]);
            $parts = explode('.', $version);
            if (count($parts) >= 2) {
                return 'macOS ' . $parts[0] . '.' . $parts[1];
            }
            return 'macOS ' . $version;
        }

        if (strpos($userAgent, 'iPhone OS') !== false) {
            preg_match('/iPhone OS ([\d_]+)/', $userAgent, $matches);
            $version = str_replace('_', '.', $matches[1]);
            $parts = explode('.', $version);
            if (count($parts) >= 2) {
                return 'iOS ' . $parts[0] . '.' . $parts[1];
            }
            return 'iOS ' . $version;
        }

        if (strpos($userAgent, 'iPad; CPU') !== false || strpos($userAgent, 'iPadOS') !== false) {
            preg_match('/iPad( OS)? ([\d_]+)/', $userAgent, $matches);
            $version = str_replace('_', '.', $matches[2] ?? '');
            $parts = explode('.', $version);
            if (count($parts) >= 2) {
                return 'iPadOS ' . $parts[0] . '.' . $parts[1];
            }
            return 'iPadOS';
        }

        if (strpos($userAgent, 'Android') !== false) {
            preg_match('/Android ([\d.]+)/', $userAgent, $matches);
            return isset($matches[1]) ? 'Android ' . $matches[1] : 'Android';
        }

        if (strpos($userAgent, 'Linux') !== false) {
            if (strpos($userAgent, 'Ubuntu') !== false) {
                return 'Ubuntu Linux';
            }
            if (strpos($userAgent, 'Debian') !== false) {
                return 'Debian Linux';
            }
            if (strpos($userAgent, 'Fedora') !== false) {
                return 'Fedora Linux';
            }
            if (strpos($userAgent, 'Arch') !== false) {
                return 'Arch Linux';
            }
            if (strpos($userAgent, 'CentOS') !== false) {
                return 'CentOS Linux';
            }
            return 'Linux';
        }

        if (strpos($userAgent, 'CrOS') !== false || strpos($userAgent, 'CrOS armv') !== false) {
            return 'ChromeOS';
        }

        if (preg_match('/\((.*?)\)/', $userAgent, $matches)) {
            $platform = trim($matches[1]);
            if (!empty($platform)) {
                return $platform;
            }
        }

        return 'Unknown';
    }
}
