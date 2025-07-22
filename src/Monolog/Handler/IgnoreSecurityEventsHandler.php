<?php
namespace App\Monolog\Handler;

use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;

class IgnoreSecurityEventsHandler extends StreamHandler
{
    public function handle(LogRecord $record): bool
    {
        // Filtre les messages "Notified event ..." liés à la sécurité
        if (
            str_starts_with($record->message, 'Notified event "{event}" to listener "{listener}".')
            && (
                (isset($record->context['event']) && str_contains($record->context['event'], 'Symfony\Component\Security\Http\Event'))
                || (isset($record->context['event']) && $record->context['event'] === 'security.authentication.success')
            )
        ) {
            return false;
        }
        return parent::handle($record);
    }
}