<?php

namespace App\Controller;

use App\Entity\ChatMessage;
use App\Entity\Lab;
use App\Entity\User;
use App\Repository\ChatMessageRepository;
use App\Repository\LabRepository;
use App\Service\ChatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class ChatController extends AbstractController
{
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_PAGE_SIZE = 100;
    // Must match the Mercure hub's authorization cookie name. Since hub v1
    // the default is "__Secure-mercure_access_token" (the pre-1.0 name
    // "mercureAuthorization" is ignored unless the hub runs in compatibility
    // mode), and the "__Secure-" prefix requires HTTPS.
    private const COOKIE_NAME = '__Secure-mercure_access_token';

    #[Route('/api/chat/{labUuid}/room', name: 'api_chat_room', methods: ['GET'])]
    public function room(
        string $labUuid,
        LabRepository $labRepository,
        ChatService $chatService
    ): JsonResponse {
        [$lab, $group, $topic] = $this->getRoomContext($labUuid, $labRepository, $chatService);

        $token = $chatService->createSubscriberToken($topic);
        $expiresAt = time() + 3600;

        $response = $this->json([
            'topic' => $topic,
            'group' => [
                'id' => $group->getId(),
                'uuid' => $group->getUuid(),
                'name' => $group->getName(),
            ],
            'members' => $chatService->getMembers($lab, $group),
        ]);

        // The browser uses this cookie to authenticate the SSE subscription
        // to the Mercure hub (same origin, under /mercure/).
        $response->headers->setCookie(
            Cookie::create(self::COOKIE_NAME, $token, $expiresAt)
                ->withPath('/mercure')
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite('lax')
        );

        return $response;
    }

    #[Route('/api/chat/{labUuid}/messages', name: 'api_chat_messages', methods: ['GET'])]
    public function messages(
        Request $request,
        string $labUuid,
        LabRepository $labRepository,
        ChatService $chatService,
        ChatMessageRepository $chatMessageRepository
    ): JsonResponse {
        [$lab] = $this->getRoomContext($labUuid, $labRepository, $chatService);

        $before = $request->query->get('before') !== null ? (int) $request->query->get('before') : null;
        $limit = min(self::MAX_PAGE_SIZE, max(1, (int) $request->query->get('limit', 50)));

        $messages = array_map(
            static function (ChatMessage $message): array {
                return self::serializeMessage($message);
            },
            $chatMessageRepository->findLatest($lab, $before, $limit)
        );

        return $this->json($messages);
    }

    #[Route('/api/chat/{labUuid}/messages', name: 'api_chat_message_post', methods: ['POST'])]
    public function postMessage(
        Request $request,
        string $labUuid,
        LabRepository $labRepository,
        ChatService $chatService,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        [$lab, $group, $topic] = $this->getRoomContext($labUuid, $labRepository, $chatService);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode((string) $request->getContent(), true) ?? [];
        $message = trim((string) ($data['message'] ?? ''));

        if ('' === $message) {
            return $this->json(['error' => 'Message is empty.'], 422);
        }
        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return $this->json(['error' => 'Message is too long (' . self::MAX_MESSAGE_LENGTH . ' characters max).'], 422);
        }

        $chatMessage = (new ChatMessage())
            ->setLab($lab)
            ->setUser($user)
            ->setMessage($message);

        $entityManager->persist($chatMessage);
        $entityManager->flush();

        $payload = self::serializeMessage($chatMessage);

        $chatService->publish($topic, $payload + ['type' => 'message']);

        return $this->json($payload, 201);
    }

    #[Route('/api/chat/{labUuid}/presence', name: 'api_chat_presence', methods: ['POST'])]
    public function presence(
        Request $request,
        string $labUuid,
        LabRepository $labRepository,
        ChatService $chatService
    ): JsonResponse {
        [$lab, $group, $topic] = $this->getRoomContext($labUuid, $labRepository, $chatService);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode((string) $request->getContent(), true) ?? [];
        $action = in_array($data['action'] ?? null, ['join', 'leave'], true) ? $data['action'] : 'join';

        $chatService->publish($topic, [
            'type' => 'presence',
            'action' => $action,
            'uuid' => $user->getUuid(),
            'name' => $user->getName(),
            'at' => (new \DateTime())->format(DATE_ATOM),
        ]);

        return $this->json(['ok' => true]);
    }

    /**
     * Loads the lab, resolves the user's chat room and returns [Lab, Group, topic].
     *
     * @return array{0: Lab, 1: \App\Entity\Group, 2: string}
     */
    private function getRoomContext(
        string $labUuid,
        LabRepository $labRepository,
        ChatService $chatService
    ): array {
        $lab = $labRepository->findOneBy(['uuid' => $labUuid]);
        if (null === $lab) {
            throw $this->createNotFoundException('Lab not found.');
        }

        $group = $chatService->resolveRoom($lab, $this->getUser());
        if (null === $group) {
            throw $this->createAccessDeniedException('You are not allowed to chat on this lab.');
        }

        return [$lab, $group, $chatService->topicFor($lab, $group)];
    }

    private static function serializeMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'uuid' => $message->getUser()?->getUuid(),
            'name' => $message->getUser()?->getName() ?? 'Unknown',
            'message' => $message->getMessage(),
            'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
