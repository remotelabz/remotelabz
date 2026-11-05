<?php

namespace App\Service;

use App\Entity\IpReputation;
use App\Repository\IpReputationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IpReputationService
{
    private EntityManagerInterface $entityManager;
    private IpReputationRepository $repository;
    private UserRepository $userRepository;
    private MailerInterface $mailer;
    private \Twig\Environment $twig;
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private int $ttl;
    private string $apiUrl;
    private string $checkUrlBase;
    private string $contactMail;
    private string $mailSubject;
    private int $alertThreshold;
    private int $alertCooldown;

    public function __construct(
        EntityManagerInterface $entityManager,
        IpReputationRepository $repository,
        UserRepository $userRepository,
        MailerInterface $mailer,
        \Twig\Environment $twig,
        #[\SensitiveParameter] string $apiKey = '',
        int $ttl = 86400,
        string $apiUrl = 'https://api.abuseipdb.com/api/v2/check',
        string $checkUrlBase = 'https://www.abuseipdb.com/check',
        #[\SensitiveParameter] string $contactMail = '',
        string $mailSubject = 'RemoteLabz',
        int $alertThreshold = 20,
        int $alertCooldown = 604800,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->apiKey = $apiKey;
        $this->ttl = $ttl;
        $this->apiUrl = $apiUrl;
        $this->checkUrlBase = rtrim($checkUrlBase, '/');
        $this->contactMail = $contactMail;
        $this->mailSubject = $mailSubject;
        $this->alertThreshold = $alertThreshold;
        $this->alertCooldown = $alertCooldown;

        $this->httpClient = $httpClient ?? HttpClient::create([
            'timeout' => 5,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'RemoteLabz/1.0',
            ],
        ]);
    }

    /**
     * Retourne la réputation d'une IP, en la (ré)interrogeant si nécessaire.
     * Ne lève jamais d'exception : en cas d'échec, la dernière valeur connue est
     * retournée (ou null si aucune).
     */
    public function getReputation(string $ip): ?IpReputation
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        try {
            $reputation = $this->repository->findOneBy(['ip' => $ip]);

            if ($reputation && !$this->isStale($reputation)) {
                return $reputation;
            }

            $data = $this->fetchFromAbuseipdb($ip);

            if (!$reputation) {
                $reputation = new IpReputation();
                $reputation->setIp($ip);
                $reputation->setCreatedAt(new \DateTime());
                $this->entityManager->persist($reputation);
            }

            $this->applyData($reputation, $data);
            $this->entityManager->flush();

            $this->sendAlertIfNeeded($reputation, $data);

            return $reputation;
        } catch (\Throwable $e) {
            error_log('Failed to update IP reputation for ' . $ip . ': ' . $e->getMessage());
            return $this->repository->findOneBy(['ip' => $ip]);
        }
    }

    public function getCheckUrl(string $ip): string
    {
        return $this->checkUrlBase . '/' . rawurlencode($ip);
    }

    public function getCheckUrlBase(): string
    {
        return $this->checkUrlBase;
    }

    private function isStale(IpReputation $reputation): bool
    {
        $lastChecked = $reputation->getLastCheckedAt();
        if (!$lastChecked) {
            return true;
        }

        return (time() - $lastChecked->getTimestamp()) > $this->ttl;
    }

    /**
     * Envoie une alerte aux administrateurs lorsque l'IP est signalée sur
     * AbuseIPDB (score >= seuil), au plus une fois par IP et par période de
     * cooldown.
     *
     * @param array<string, mixed>|null $data Données fraîches renvoyées par l'API
     */
    private function sendAlertIfNeeded(IpReputation $reputation, ?array $data): void
    {
        $score = $reputation->getAbuseScore();
        if (!$data || $score === null || $score < $this->alertThreshold) {
            return;
        }

        if (!$this->isAlertCooldownExpired($reputation)) {
            return;
        }

        try {
            $recipients = $this->getAdministratorEmails();
            if (!$recipients) {
                error_log('IP reputation alert skipped for ' . $reputation->getIp() . ': no administrator recipient');
                return;
            }

            $ip = $reputation->getIp();
            $email = (new Email())
                ->from($this->contactMail)
                ->to(...$recipients)
                ->subject(sprintf(
                    '%s - Suspicious IP detected: %s (score %d/100)',
                    $this->mailSubject,
                    $ip,
                    $score
                ))
                ->html(
                    $this->twig->render('emails/ip_reputation_alert.html.twig', [
                        'ip' => $ip,
                        'score' => $score,
                        'threshold' => $this->alertThreshold,
                        'totalReports' => $reputation->getTotalReports(),
                        'countryCode' => $reputation->getCountryCode(),
                        'checkedAt' => $reputation->getLastCheckedAt()
                            ? $reputation->getLastCheckedAt()->format('d/m/Y H:i')
                            : '-',
                        'checkUrl' => $this->getCheckUrl($ip),
                        'cooldownDays' => (int) ceil($this->alertCooldown / 86400),
                    ])
                );

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            error_log('Failed to send IP reputation alert for ' . $reputation->getIp() . ': ' . $e->getMessage());
            return;
        }

        $reputation->setLastAlertedAt(new \DateTime());

        try {
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            error_log('Failed to store IP reputation alert date for ' . $reputation->getIp() . ': ' . $e->getMessage());
        }
    }

    private function isAlertCooldownExpired(IpReputation $reputation): bool
    {
        $lastAlerted = $reputation->getLastAlertedAt();
        if (!$lastAlerted) {
            return true;
        }

        return (time() - $lastAlerted->getTimestamp()) >= $this->alertCooldown;
    }

    /**
     * @return array<string>
     */
    private function getAdministratorEmails(): array
    {
        $emails = [];

        foreach ($this->userRepository->findByRole('%ADMIN%') as $user) {
            if (!$user->isAdministrator()) {
                continue;
            }

            $email = $user->getEmail();
            if ($email && !in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromAbuseipdb(string $ip): ?array
    {
        if ($this->apiKey === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiUrl, [
                'query' => [
                    'ipAddress' => $ip,
                    'maxAgeInDays' => 90,
                ],
                'headers' => [
                    'Key' => $this->apiKey,
                ],
            ]);

            $payload = $response->toArray(false);
        } catch (\Throwable $e) {
            error_log('AbuseIPDB request failed for ' . $ip . ': ' . $e->getMessage());
            return null;
        }

        if (!isset($payload['data']) || !is_array($payload['data'])) {
            return null;
        }

        return $payload['data'];
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function applyData(IpReputation $reputation, ?array $data): void
    {
        $reputation->setLastCheckedAt(new \DateTime());

        if (!$data) {
            return;
        }

        if (isset($data['abuseConfidenceScore']) && is_numeric($data['abuseConfidenceScore'])) {
            $reputation->setAbuseScore((int) $data['abuseConfidenceScore']);
        }

        if (isset($data['totalReports']) && is_numeric($data['totalReports'])) {
            $reputation->setTotalReports((int) $data['totalReports']);
        }

        if (!empty($data['countryCode'])) {
            $reputation->setCountryCode((string) $data['countryCode']);
        }
    }
}
