<?php

namespace App\Service;

use App\Entity\UserNotification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Create a success notification for one or multiple users
     * 
     * @param string|array $userIds Single user ID or array of user IDs
     * @param string $message Notification message
     * @param string|null $relatedUuid Related instance UUID
     * @param array $context Additional context data
     */
    public function success($userIds, string $message, ?string $relatedUuid = null, array $context = []): void
    {
        $this->createNotifications($userIds, 'success', $message, $relatedUuid, $context);
    }

    /**
     * Create an error notification for one or multiple users
     * 
     * @param string|array $userIds Single user ID or array of user IDs
     * @param string $message Notification message
     * @param string|null $relatedUuid Related instance UUID
     * @param array $context Additional context data
     */
    public function error($userIds, string $message, ?string $relatedUuid = null, array $context = []): void
    {
        $this->createNotifications($userIds, 'error', $message, $relatedUuid, $context);
    }

    /**
     * Create a warning notification for one or multiple users
     * 
     * @param string|array $userIds Single user ID or array of user IDs
     * @param string $message Notification message
     * @param string|null $relatedUuid Related instance UUID
     * @param array $context Additional context data
     */
    public function warning($userIds, string $message, ?string $relatedUuid = null, array $context = []): void
    {
        $this->createNotifications($userIds, 'warning', $message, $relatedUuid, $context);
    }

    /**
     * Create an info notification for one or multiple users
     * 
     * @param string|array $userIds Single user ID or array of user IDs
     * @param string $message Notification message
     * @param string|null $relatedUuid Related instance UUID
     * @param array $context Additional context data
     */
    public function info($userIds, string $message, ?string $relatedUuid = null, array $context = []): void
    {
        $this->createNotifications($userIds, 'info', $message, $relatedUuid, $context);
    }

    /**
     * Internal method to create notifications for single or multiple users
     * 
     * @param string|array $userIds Single user ID or array of user IDs
     * @param string $type Notification type (success, error, warning, info)
     * @param string $message Notification message
     * @param string|null $relatedUuid Related instance UUID
     * @param array $context Additional context data
     */
    private function createNotifications($userIds, string $type, string $message, ?string $relatedUuid = null, array $context = []): void
    {
        // Convert single user ID to array for uniform processing
        if (is_string($userIds)) {
            $userIds = [$userIds];
        }

        // Handle null or empty array
        if (empty($userIds)) {
            $this->logger->warning('[NotificationService:createNotifications]::No user IDs provided for notification', [
                'type' => $type,
                'message' => $message,
                'relatedUuid' => $relatedUuid
            ]);
            return;
        }

        foreach ($userIds as $userId) {
            if (empty($userId)) {
                $this->logger->warning('[NotificationService:createNotifications]::Empty user ID in array, skipping');
                continue;
            }

            try {
                // Le constructeur de UserNotification gère automatiquement createdAt
                $notification = new UserNotification();
                $notification->setUserId($userId);
                $notification->setType($type);
                $notification->setMessage($message);
                $notification->setRelatedUuid($relatedUuid);
                $notification->setContext($context);
                $notification->setIsRead(false);
                
                $this->entityManager->persist($notification);
                
                $this->logger->debug('[NotificationService:createNotifications]::Notification created for user', [
                    'userId' => $userId,
                    'type' => $type,
                    'message' => $message,
                    'relatedUuid' => $relatedUuid
                ]);
            } catch (\Exception $e) {
                $this->logger->error('[NotificationService:createNotifications]::Failed to create notification for user', [
                    'userId' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('[NotificationService:createNotifications]::Failed to flush notifications', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(int $notificationId): void
    {
        $notification = $this->entityManager->getRepository(UserNotification::class)->find($notificationId);
        
        if ($notification) {
            $notification->setIsRead(true);
            $this->entityManager->persist($notification);
            $this->entityManager->flush();
        }
    }

    /**
     * Mark all notifications as read for a specific user and related UUID (instance)
     * This is useful when an instance's state changes and all related notifications should be marked as read
     * 
     * @param string|array $userIds User ID(s)
     * @param string $relatedUuid Instance UUID
     */
    public function markAllAsReadForInstance($userIds, string $relatedUuid): void
    {
        // Convert single user ID to array for uniform processing
        if (is_string($userIds)) {
            $userIds = [$userIds];
        }

        if (empty($userIds)) {
            return;
        }

        foreach ($userIds as $userId) {
            try {
                $notifications = $this->entityManager
                    ->getRepository(UserNotification::class)
                    ->findBy([
                        'userId' => $userId,
                        'relatedUuid' => $relatedUuid,
                        'isRead' => false
                    ]);

                foreach ($notifications as $notification) {
                    $notification->setIsRead(true);
                    $this->entityManager->persist($notification);
                }
                
                $this->logger->debug('[NotificationService:markAllAsReadForInstance]::Marked notifications as read for user and instance', [
                    'userId' => $userId,
                    'relatedUuid' => $relatedUuid,
                    'count' => count($notifications)
                ]);
            } catch (\Exception $e) {
                $this->logger->error('[NotificationService:markAllAsReadForInstance]::Failed to mark notifications as read', [
                    'userId' => $userId,
                    'relatedUuid' => $relatedUuid,
                    'error' => $e->getMessage()
                ]);
            }
        }

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('[NotificationService:markAllAsReadForInstance]::Failed to flush when marking as read', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get unread notifications for a user
     */
    public function getUnreadNotifications(string $userId): array
    {
        return $this->entityManager
            ->getRepository(UserNotification::class)
            ->findBy([
                'userId' => $userId,
                'isRead' => false
            ], ['createdAt' => 'DESC']);
    }

    /**
     * Mark all notifications as read for a specific user
     */
    public function markAllAsRead(string $userId): void
    {
        $notifications = $this->getUnreadNotifications($userId);
        
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
            $this->entityManager->persist($notification);
        }
        
        $this->entityManager->flush();
    }
}