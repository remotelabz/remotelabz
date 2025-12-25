<?php

namespace App\Twig;

use App\Service\NotificationService;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension to access notifications in templates
 */
class NotificationExtension extends AbstractExtension
{
    private NotificationService $notificationService;
    private Security $security;

    public function __construct(
        NotificationService $notificationService,
        Security $security
    ) {
        $this->notificationService = $notificationService;
        $this->security = $security;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_unread_notifications', [$this, 'getUnreadNotifications']),
            new TwigFunction('get_notification_count', [$this, 'getNotificationCount']),
        ];
    }

    /**
     * Get unread notifications for current user
     */
    public function getUnreadNotifications(): array
    {
        $user = $this->security->getUser();
        if (!$user) {
            return [];
        }

        return $this->notificationService->getUnreadNotifications((string) $user->getId());
    }

    /**
     * Get count of unread notifications
     */
    public function getNotificationCount(): int
    {
        return count($this->getUnreadNotifications());
    }
}