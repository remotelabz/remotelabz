<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\LabInstance;
use App\Entity\User;
use App\Instance\InstanceState;
use App\Repository\LabInstanceRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ChatService
{
    public const TOPIC_PREFIX = 'chat/lab/';

    /**
     * Must match the `issuer` name configured in the Mercure hub Caddyfile,
     * otherwise the hub rejects the tokens with "untrusted issuer".
     */
    public const JWT_ISSUER = 'remotelabz';

    /**
     * RFC 9396 authorization detail type defined by the Mercure v1 protocol.
     */
    private const AUTHORIZATION_DETAIL_TYPE = 'https://mercure.rocks/authorization-detail';

    /**
     * Lab instance states considered as "currently executing the lab".
     */
    public const ACTIVE_STATES = [
        InstanceState::CREATING,
        InstanceState::CREATED,
        InstanceState::STARTING,
        InstanceState::STARTED,
        InstanceState::STOPPING,
    ];

    private const SUBSCRIBER_TOKEN_TTL = 3600;
    private const PUBLISHER_TOKEN_TTL = 120;

    private $labInstanceRepository;
    private $client;
    private $logger;
    private $hubInternalUrl;
    private $publisherJwtKey;
    private $subscriberJwtKey;

    public function __construct(
        LabInstanceRepository $labInstanceRepository,
        Client $client,
        LoggerInterface $logger,
        string $hubInternalUrl,
        string $publisherJwtKey,
        string $subscriberJwtKey
    ) {
        $this->labInstanceRepository = $labInstanceRepository;
        $this->client = $client;
        $this->logger = $logger;
        $this->hubInternalUrl = $hubInternalUrl;
        $this->publisherJwtKey = $publisherJwtKey;
        $this->subscriberJwtKey = $subscriberJwtKey;
    }

    /**
     * Resolves the chat room (a Group) the user may access for the given lab.
     *
     * Returns null when the lab has no chat, when the user is a guest or when
     * the user is not a member of any group linked to the lab. The author of
     * the lab and administrators may join the room of the first linked group.
     */
    public function resolveRoom(Lab $lab, UserInterface $user): ?Group
    {
        if (!$lab->isChatEnabled()) {
            return null;
        }

        if (!$user instanceof User) {
            return null;
        }

        foreach ($lab->getGroups() as $group) {
            if ($user->isMemberOf($group)) {
                return $group;
            }
        }

        if ($this->isLabManager($user, $lab) && $lab->getGroups()->count() > 0) {
            return $lab->getGroups()->first();
        }

        return null;
    }

    /**
     * Whether the given user can open a chat room for the lab. Used to decide
     * whether the chat UI should be offered at all (avoids a 403 when the lab
     * has no accessible room).
     */
    public function canChat(Lab $lab, UserInterface $user): bool
    {
        return null !== $this->resolveRoom($lab, $user);
    }

    private function isLabManager(User $user, Lab $lab): bool
    {
        return $user->isAdministrator() || (null !== $lab->getAuthor() && $lab->getAuthor() === $user);
    }

    public function topicFor(Lab $lab, Group $group): string
    {
        return self::TOPIC_PREFIX . $lab->getUuid() . '/group/' . $group->getUuid();
    }

    /**
     * Lists the group members currently executing the lab, either with their
     * own LabInstance or through a group-owned LabInstance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMembers(Lab $lab, Group $group): array
    {
        $em = $this->labInstanceRepository->getEntityManager();

        $groupInstanceStateRows = $em->createQuery(
            'SELECT li.state AS state FROM App\Entity\LabInstance li
             WHERE li.lab = :lab AND li._group = :group AND li.state IN (:activeStates)
             ORDER BY li.id DESC'
        )
            ->setParameter('lab', $lab)
            ->setParameter('group', $group)
            ->setParameter('activeStates', self::ACTIVE_STATES)
            ->setMaxResults(1)
            ->getScalarResult();
        $groupInstanceState = $groupInstanceStateRows[0]['state'] ?? null;

        // One GroupUser row per member and at most one active LabInstance per
        // user and lab: no duplicates possible.
        $rows = $em->createQuery(
            'SELECT gu AS member, li.state AS instanceState
             FROM App\Entity\GroupUser gu
             LEFT JOIN App\Entity\LabInstance li WITH li.lab = :lab AND li.user = gu.user AND li.state IN (:activeStates)
             WHERE gu.group = :group'
        )
            ->setParameter('lab', $lab)
            ->setParameter('group', $group)
            ->setParameter('activeStates', self::ACTIVE_STATES)
            ->getResult();

        $members = [];
        foreach ($rows as $row) {
            /** @var User $member */
            $member = $row['member']->getUser();
            $instanceState = $row['instanceState'];

            if (null === $instanceState && null !== $groupInstanceState) {
                $instanceState = $groupInstanceState;
            }

            $members[] = [
                'id' => $member->getId(),
                'uuid' => $member->getUuid(),
                'name' => $member->getName(),
                'email' => $member->getEmail(),
                'hasInstance' => null !== $instanceState,
                'instanceState' => $instanceState,
            ];
        }

        usort($members, static function (array $a, array $b): int {
            if ($a['hasInstance'] !== $b['hasInstance']) {
                return $a['hasInstance'] ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $members;
    }

    /**
     * Creates the subscriber JWT sent to the browser (__Secure-mercure_access_token cookie).
     * It only allows subscribing to the given topic.
     */
    public function createSubscriberToken(string $topic): string
    {
        return $this->encodeToken([
            'exp' => time() + self::SUBSCRIBER_TOKEN_TTL,
            'authorization_details' => [
                [
                    'type' => self::AUTHORIZATION_DETAIL_TYPE,
                    'actions' => ['subscribe'],
                    'topics' => [
                        ['match' => $topic],
                    ],
                ],
            ],
        ], $this->subscriberJwtKey);
    }

    /**
     * Publishes an event to the hub as a private update: only subscribers whose
     * token allows the topic will receive it.
     *
     * @param array<string, mixed> $event
     */
    public function publish(string $topic, array $event): void
    {
        $token = $this->encodeToken([
            'exp' => time() + self::PUBLISHER_TOKEN_TTL,
            'authorization_details' => [
                [
                    'type' => self::AUTHORIZATION_DETAIL_TYPE,
                    'actions' => ['publish'],
                    'topics' => [
                        ['match' => $topic],
                    ],
                ],
            ],
        ], $this->publisherJwtKey);

        try {
            $this->client->post($this->hubInternalUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'X-Forwarded-Proto' => 'https',
                ],
                'form_params' => [
                    'topic' => $topic,
                    'private' => 'on',
                    'type' => 'message',
                    'data' => json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('[ChatService:publish]::Failed to publish to the Mercure hub: ' . $e->getMessage());
        }
    }

    /**
     * Minimal HS256 JWT encoder (Mercure tokens only, no external dependency).
     *
     * @param array<string, mixed> $claims
     */
    private function encodeToken(array $claims, string $secret): string
    {
        // Mercure hub v1 requirements: "iss" must match the hub issuer name,
        // "aud" must be the hub URL, and the "typ" header must be "at+jwt".
        $claims['iss'] = self::JWT_ISSUER;
        $claims['aud'] = $this->hubInternalUrl;

        $base64Url = static function (string $data): string {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        };

        $header = $base64Url((string) json_encode(['alg' => 'HS256', 'typ' => 'at+jwt'], JSON_UNESCAPED_SLASHES));
        $payload = $base64Url((string) json_encode($claims, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $header . '.' . $payload, $secret, true);

        return $header . '.' . $payload . '.' . $base64Url($signature);
    }
}
