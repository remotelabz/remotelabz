<?php

namespace App\Controller;

use App\Entity\UserNotification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

class NotificationController extends AbstractController
{
    /**
     * Mark notification as read
     * Modified to allow users to mark their own notifications
     * Administrators can mark any notification
     */
    #[Route('/notifications/mark-read/{id}', name: 'notification_mark_read', methods: ['GET', 'POST'])]
    #[IsGranted("IS_AUTHENTICATED_FULLY")]
    public function markAsRead(
        int $id, 
        NotificationService $notificationService,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        try {
            // Administrators can mark any notification as read
            if (!$this->isGranted('ROLE_ADMINISTRATOR')) {
                // Regular users can only mark their own notifications
                $notification = $em->getRepository(UserNotification::class)->find($id);
                
                if (!$notification) {
                    return new JsonResponse(['success' => false, 'error' => 'Notification not found'], 404);
                }
                
                // Verify notification ownership
                if ($notification->getUserId() !== (string)$user->getId()) {
                    return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
                }
            }
            
            $notificationService->markAsRead($id);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get unread notifications for current user
     */
    #[Route('/api/notifications/unread', name: 'api_notifications_unread', methods: ['GET'])]
    #[IsGranted("IS_AUTHENTICATED_FULLY")]
    public function getUnread(NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['notifications' => []], 401);
        }

        $notifications = $notificationService->getUnreadNotifications((string)$user->getId());
        
        // Serialize notifications
        $data = array_map(function($notification) {
            return [
                'id' => $notification->getId(),
                'type' => $notification->getType(),
                'message' => $notification->getMessage(),
                'relatedUuid' => $notification->getRelatedUuid(),
                'context' => $notification->getContext(),
                'createdAt' => $notification->getCreatedAt()->format('c')
            ];
        }, $notifications);

        return new JsonResponse(['notifications' => $data]);
    }

    /**
     * Mark all notifications as read for current user
     */
    #[Route('/api/notifications/mark-all-read', name: 'api_notifications_mark_all_read', methods: ['POST'])]
    #[IsGranted("IS_AUTHENTICATED_FULLY")]
    public function markAllAsRead(NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $notificationService->markAllAsRead((string)$user->getId());
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to mark all notifications as read'
            ], 500);
        }
    }
}