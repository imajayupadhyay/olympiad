<?php

namespace App\Services\Notifications;

use App\Models\Exam;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\ManagedEmailService;

/**
 * Email channel: one Brevo send per delivery through ManagedEmailService (same
 * branded shell, same `email_logs`, same BREVO_* config as every other email).
 * Runs inside DeliverNotificationBatch, never in a web request.
 */
class EmailNotificationChannel
{
    public function __construct(
        private readonly ManagedEmailService $emails,
        private readonly NotificationPlaceholderService $placeholders,
    ) {}

    public function deliver(NotificationDelivery $delivery, NotificationLog $log, User $user, ?Exam $exam): void
    {
        if (blank($user->email)) {
            $delivery->markSkipped('Student has no email address.', 'no_email');

            return;
        }

        $values = $this->placeholders->valuesFor($user, $exam);
        $subject = $this->placeholders->renderText((string) ($log->subject ?: $log->title), $values);
        $html = $this->placeholders->renderHtml((string) $log->body_html, $values);

        [$emailLog, $rendered] = $this->emails->prepareBroadcast($user, $subject, $html, [
            'related_type' => NotificationLog::class,
            'related_id' => $log->id,
            'notification_log_id' => $log->id,
            'notification_delivery_id' => $delivery->id,
        ]);

        $delivery->update([
            'email_log_id' => $emailLog->id,
            'destination' => $user->email,
            'attempts' => $delivery->attempts + 1,
        ]);

        $this->emails->sendRendered($emailLog, $rendered);
        $emailLog->refresh();

        if ($emailLog->status === 'sent') {
            $delivery->update([
                'status' => 'sent',
                'provider_message_id' => $emailLog->brevo_message_id,
                'sent_at' => $emailLog->sent_at ?? now(),
                'error' => null,
                'error_code' => null,
                'claim_token' => null,
            ]);

            return;
        }

        $delivery->markFailed($emailLog->error_message ?: 'Email could not be sent.', 'brevo');
    }
}
