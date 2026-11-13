<?php

namespace App\Services\Notifications;

use App\Models\Exam;
use App\Models\NotificationLog;
use App\Models\StudentNotification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * In-app channel: writes `student_notifications` rows (the student bell and
 * /student/notifications) for a chunk of recipients, with placeholders filled
 * per student. Pure database work, so it runs inside the send request and needs
 * no queue worker. Read state stays on student_notifications (is_read/read_at).
 */
class InAppNotificationChannel
{
    public function __construct(private readonly NotificationPlaceholderService $placeholders) {}

    /** @return array<int, array<string, mixed>> delivery rows for the chunk */
    public function deliverChunk(NotificationLog $log, Collection $users, ?Exam $exam): array
    {
        $now = now();
        $notifications = [];
        $deliveries = [];

        foreach ($users as $user) {
            /** @var User $user */
            $values = $this->placeholders->valuesFor($user, $exam);

            $notifications[] = [
                'user_id' => $user->id,
                'notification_log_id' => $log->id,
                'title' => mb_substr($this->placeholders->renderText((string) $log->title, $values), 0, 255),
                'message' => $this->placeholders->renderText((string) $log->message, $values),
                'link' => $log->link,
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $deliveries[] = [
                'notification_log_id' => $log->id,
                'user_id' => $user->id,
                'channel' => 'in_app',
                'recipient_name' => $user->name,
                'destination' => null,
                'status' => 'sent',
                'attempts' => 1,
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        StudentNotification::insert($notifications);

        return $deliveries;
    }
}
