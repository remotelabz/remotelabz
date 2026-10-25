<?php

namespace App\Service\Instance;

use App\Entity\DeviceInstance;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Sends an alert e-mail to the contact address (CONTACT_MAIL) whenever a
 * device instance reaches the error state on a worker.
 *
 * The state feedback (InstanceStateMessage::STATE_ERROR) is received by
 * {@see \App\MessageHandler\InstanceStateMessageHandler}; this service only
 * takes care of notifying the platform administrators by e-mail so a failing
 * device does not go unnoticed.
 */
class DeviceErrorMailService
{
    private MailerInterface $mailer;
    private \Twig\Environment $twig;
    private LoggerInterface $logger;
    private string $contactMail;
    private string $mailSubject;

    public function __construct(
        MailerInterface $mailer,
        \Twig\Environment $twig,
        LoggerInterface $logger,
        string $contactMail,
        string $mailSubject,
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->contactMail = $contactMail;
        $this->mailSubject = $mailSubject;
    }

    /**
     * Notifies the contact address that a device instance is in error.
     *
     * Never throws: a mail failure must not break the state message handling.
     */
    public function sendDeviceErrorNotification(DeviceInstance $instance, array $options = []): void
    {
        if (empty($this->contactMail)) {
            $this->logger->warning('[DeviceErrorMailService]::No CONTACT_MAIL configured, no alert sent for device instance '.$instance->getUuid());

            return;
        }

        $device = $instance->getDevice();
        $deviceName = null !== $device ? $device->getName() : null;
        $labInstance = $instance->getLabInstance();
        $lab = null !== $labInstance ? $labInstance->getLab() : null;
        $labName = null !== $lab ? $lab->getName() : null;

        $errorMessage = $options['error_message'] ?? null;
        $action = $options['state'] ?? null;
        $errorCode = $options['error_code'] ?? null;
        $workerIp = $options['worker_ip'] ?? ($options['worker_dest_ip'] ?? null);

        $email = (new Email())
            ->from($this->contactMail)
            ->to($this->contactMail)
            ->subject(sprintf(
                '%s — Device "%s" in error',
                $this->mailSubject,
                $deviceName ?: $instance->getUuid()
            ))
            ->html(
                $this->twig->render('emails/device_error.html.twig', [
                    'deviceName' => $deviceName,
                    'deviceUuid' => $instance->getUuid(),
                    'labName' => $labName,
                    'owner' => $this->getOwnerLabel($instance),
                    'previousState' => $instance->getState(),
                    'errorMessage' => $errorMessage,
                    'action' => $action,
                    'errorCode' => $errorCode,
                    'workerIp' => $workerIp,
                    'receivedAt' => (new \DateTime())->format('d/m/Y H:i:s'),
                ])
            );

        try {
            $this->mailer->send($email);
            $this->logger->info('[DeviceErrorMailService]::Device error alert sent to '.$this->contactMail.' for device instance '.$instance->getUuid());
        } catch (\Throwable $e) {
            $this->logger->error('[DeviceErrorMailService]::Failed to send device error alert for device instance '.$instance->getUuid().': '.$e->getMessage());
        }
    }

    /**
     * Human readable label of the instance owner (user, group or guest).
     */
    private function getOwnerLabel(DeviceInstance $instance): ?string
    {
        try {
            $user = $instance->getUser();
            if (null !== $user) {
                $name = $user->getName() ?: $user->getEmail();

                return null !== $name && '' !== $name ? $name : 'user #'.$user->getId();
            }

            $group = $instance->getGroup();
            if (null !== $group) {
                return $group->getName() ?: 'group #'.$group->getId();
            }

            $guest = $instance->getGuest();
            if (null !== $guest) {
                return 'guest '.$guest->getUuid();
            }
        } catch (\Throwable $e) {
            $this->logger->debug('[DeviceErrorMailService]::Could not resolve owner of device instance '.$instance->getUuid().': '.$e->getMessage());
        }

        return null;
    }
}
