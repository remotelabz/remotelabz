<?php

namespace App\Service;

use App\Entity\UserNotification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to create and manage user notifications
 * Works even without HTTP session (async workers)
 */
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
     * Add a notification for a user
     */
    public function addNotification(
        ?string $userId,
        string $type,
        string $message,
        ?string $relatedUuid = null,
        array $context = []
    ): void {
        try {
            $notification = new UserNotification();
            $notification->setUserId($userId);
            $notification->setType($type);
            $notification->setMessage($message);
            $notification->setRelatedUuid($relatedUuid);
            $notification->setContext($context);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            $this->logger->info('Notification created', [
                'user_id' => $userId,
                'type' => $type,
                'message' => $message,
                'uuid' => $relatedUuid
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create notification: ' . $e->getMessage());
        }
    }

    /**
     * Add an error notification
     */
    public function error(?string $userId, string $message, ?string $relatedUuid = null, array $context = []): void
    {
        $this->addNotification($userId, 'error', $message, $relatedUuid, $context);
    }

    /**
     * Add a success notification
     */
    public function success(?string $userId, string $message, ?string $relatedUuid = null, array $context = []): void
    {
        $this->addNotification($userId, 'success', $message, $relatedUuid, $context);
    }

    /**
     * Add a warning notification
     */
    public function warning(?string $userId, string $message, ?string $relatedUuid = null, array $context = []): void
    {
        $this->addNotification($userId, 'warning', $message, $relatedUuid, $context);
    }

    /**
     * Add an info notification
     */
    public function info(?string $userId, string $message, ?string $relatedUuid = null, array $context = []): void
    {
        $this->addNotification($userId, 'info', $message, $relatedUuid, $context);
    }

    /**
     * Get unread notifications for a user
     */
    public function getUnreadNotifications(?string $userId): array
    {
        if (!$userId) {
            return [];
        }

        return $this->entityManager
            ->getRepository(UserNotification::class)
            ->findBy(
                ['userId' => $userId, 'isRead' => false],
                ['createdAt' => 'DESC']
            );
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId): void
    {
        $notification = $this->entityManager
            ->getRepository(UserNotification::class)
            ->find($notificationId);

        if ($notification) {
            $notification->setIsRead(true);
            $this->entityManager->flush();
        }
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(?string $userId): void
    {
        if (!$userId) {
            return;
        }

        $notifications = $this->entityManager
            ->getRepository(UserNotification::class)
            ->findBy(['userId' => $userId, 'isRead' => false]);

        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $this->entityManager->flush();
    }

    /**
     * Delete old read notifications (cleanup)
     */
    public function deleteOldNotifications(int $daysOld = 30): void
    {
        $date = new \DateTime("-{$daysOld} days");
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(UserNotification::class, 'n')
            ->where('n.isRead = :read')
            ->andWhere('n.createdAt < :date')
            ->setParameter('read', true)
            ->setParameter('date', $date);
        
        $qb->getQuery()->execute();
    }
}