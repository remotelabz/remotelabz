<?php

namespace App\Service\Instance;

use App\Entity\ScheduledAction;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Sends an e-mail to the user who created a scheduled action
 * (start / stop / reset / leave) once the action has been executed
 * by the scheduled actions runner.
 */
class ScheduledActionMailService
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
        string $mailSubject
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->contactMail = $contactMail;
        $this->mailSubject = $mailSubject;
    }

    /**
     * Notifies by e-mail the user who created the scheduled action.
     * No-op when the action has no creator or the creator has no e-mail.
     */
    public function sendExecutionNotification(ScheduledAction $sa): void
    {
        $user = $sa->getCreatedBy();
        if (is_null($user)) {
            $this->logger->debug('[ScheduledActionMailService]::No creator for scheduled action ' . $sa->getUuid() . ', no e-mail sent.');
            return;
        }

        if (empty($user->getEmail())) {
            $this->logger->debug('[ScheduledActionMailService]::Creator of scheduled action ' . $sa->getUuid() . ' has no e-mail, no e-mail sent.');
            return;
        }

        $report = $sa->getExecutionReport() ?? [];
        $reportEntries = $report['report'] ?? [];
        $errors = $report['errors'] ?? [];

        $errorLines = [];
        foreach (array_slice($errors, 0, 5) as $error) {
            $message = $error['error'] ?? 'unknown error';
            $errorLines[] = isset($error['user']) ? $error['user'] . ': ' . $message : $message;
        }

        $result = $sa->isDone() ? 'Success' : 'Failure';

        $email = (new Email())
            ->from($this->contactMail)
            ->to($user->getEmail())
            ->subject(sprintf(
                '%s — Scheduled %s on lab "%s": %s',
                $this->mailSubject,
                $sa->getAction(),
                $sa->getLab()->getName(),
                $result
            ))
            ->html(
                $this->twig->render('emails/scheduled_action_executed.html.twig', [
                    'firstName'   => $user->getFirstName() ?: $user->getName(),
                    'action'      => $sa->getAction(),
                    'labName'     => $sa->getLab()->getName(),
                    'groupName'   => $sa->getGroup() ? $sa->getGroup()->getName() : 'all instances',
                    'scheduledAt' => $sa->getScheduledAt()->format('d/m/Y H:i'),
                    'executedAt'  => $sa->getExecutedAt() ? $sa->getExecutedAt()->format('d/m/Y H:i') : '-',
                    'result'      => $result,
                    'reportCount' => count($reportEntries),
                    'errorCount'  => count($errors),
                    'errors'      => $errorLines,
                    'errorMessage' => $sa->getErrorMessage(),
                ])
            );

        try {
            $this->mailer->send($email);
            $this->logger->info('[ScheduledActionMailService]::Execution notification sent to ' . $user->getEmail() . ' for scheduled action ' . $sa->getUuid());
        } catch (\Exception $e) {
            $this->logger->error('[ScheduledActionMailService]::Failed to send execution notification for scheduled action ' . $sa->getUuid() . ': ' . $e->getMessage());
        }
    }
}
