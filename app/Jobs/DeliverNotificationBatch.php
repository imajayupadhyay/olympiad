<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\BirdException;
use App\Services\Notifications\EmailNotificationChannel;
use App\Services\Notifications\NotificationBroadcastService;
use App\Services\Notifications\NotificationPlaceholderService;
use App\Services\Notifications\WhatsAppNotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one batch of an email or WhatsApp broadcast, then queues the next batch
 * (a chain per broadcast). Carries only the log id. Claimed rows are released on a
 * Bird 429 (the next batch waits Retry-After) or when the job dies; the
 * `notifications:maintain` scheduler resumes anything left behind.
 */
class DeliverNotificationBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> deliveries claimed by this run, for failed() */
    private array $claimed = [];

    public function __construct(public int $notificationLogId) {}

    public function handle(
        NotificationBroadcastService $broadcasts,
        NotificationPlaceholderService $placeholders,
        EmailNotificationChannel $email,
        WhatsAppNotificationChannel $whatsapp,
    ): void {
        $log = NotificationLog::find($this->notificationLogId);

        if (! $log || ! in_array($log->channel, ['email', 'whatsapp'], true)) {
            return;
        }

        $size = $log->channel === 'whatsapp'
            ? max(1, (int) config('services.bird.batch_size', 40))
            : 50;

        $batch = $broadcasts->claim($log, $size);
        if ($batch->isEmpty()) {
            $broadcasts->refreshCounts($log);

            return;
        }

        $this->claimed = $batch->pluck('id')->all();
        $log->update(['status' => 'sending']);

        $exam = ($id = $log->audience_filters['exam_context_id'] ?? null) ? Exam::find($id) : null;
        $users = User::whereIn('id', $batch->pluck('user_id')->filter())
            ->with($placeholders->eagerLoads($exam))
            ->get()->keyBy('id');

        foreach ($batch as $index => $delivery) {
            /** @var NotificationDelivery $delivery */
            $user = $users->get($delivery->user_id);

            if (! $user) {
                $delivery->markSkipped('Student account no longer exists.', 'no_user');

                continue;
            }

            try {
                $log->channel === 'email'
                    ? $email->deliver($delivery, $log, $user, $exam)
                    : $whatsapp->deliver($delivery, $log, $user, $exam);
            } catch (BirdException $e) {
                // Rate limited: hand the rest of the batch back and wait.
                $broadcasts->release($batch->slice($index)->pluck('id'));
                $hits = (int) data_get($log->meta, 'rate_limit_hits', 0) + 1;
                $log->update(['meta' => array_merge((array) $log->meta, ['rate_limit_hits' => $hits, 'last_rate_limit_at' => now()->toIso8601String()])]);
                $broadcasts->refreshCounts($log);

                if ($hits <= (int) config('services.bird.max_rate_limit_retries', 8) && config('queue.default') !== 'sync') {
                    self::dispatch($log->id)->delay(now()->addSeconds(max(5, $e->retryAfter ?? 60)));
                }

                return;
            } catch (Throwable $e) {
                report($e);
                $delivery->markFailed('Unexpected error: '.$e->getMessage(), 'exception');
            }
        }

        $this->claimed = [];
        if (data_get($log->meta, 'rate_limit_hits')) {
            $log->update(['meta' => array_merge((array) $log->meta, ['rate_limit_hits' => 0])]);
        }
        $broadcasts->refreshCounts($log);

        if ($log->deliveries()->where('status', 'pending')->exists()) {
            self::dispatch($log->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->claimed) {
            app(NotificationBroadcastService::class)->release($this->claimed);
        }
    }
}
