<?php

namespace App\Services\Notifications;

use App\Jobs\DeliverNotificationBatch;
use App\Models\Exam;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\StudentNotification;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the broadcast lifecycle for every channel (workflow.md §13.2):
 *
 *   create()  idempotency + duplicate guard → NotificationLog → recipient snapshot
 *             (one notification_deliveries row per student) → in-app written inline,
 *             email/WhatsApp queued as DeliverNotificationBatch chains.
 *   claim()   atomically hands a batch of pending deliveries to one job.
 *   retry()   failed, non-permanent deliveries → pending → re-queued.
 *   refreshCounts()  the only writer of the log's *_count columns and status.
 *   maintain()  releases stale claims, resumes stalled sends, syncs WhatsApp status.
 *
 * Channels never touch each other: a WhatsApp outage can't fail an email broadcast.
 */
class NotificationBroadcastService
{
    public const DUPLICATE_WINDOW_MINUTES = 10;

    public const STALE_CLAIM_MINUTES = 10;

    public function __construct(
        private readonly NotificationAudienceService $audience,
        private readonly NotificationPlaceholderService $placeholders,
        private readonly InAppNotificationChannel $inApp,
    ) {}

    /**
     * @param  array{channel: string, audience: array, exam_context_id?: ?int, idempotency_key: string, allow_duplicate?: bool, content: array}  $data
     * @return array{0: NotificationLog, 1: bool} [log, created now?]
     */
    public function create(array $data, User $admin): array
    {
        if ($existing = NotificationLog::where('idempotency_key', $data['idempotency_key'])->first()) {
            return [$existing, false];
        }

        $channel = $data['channel'];
        $audience = $this->audience->normalise($data['audience']);
        $exam = ! empty($data['exam_context_id']) ? Exam::find($data['exam_context_id']) : null;
        $content = $data['content'];

        $fingerprint = hash('sha256', json_encode([$channel, $content, $audience, $exam?->id]));
        if (empty($data['allow_duplicate'])) {
            $recent = NotificationLog::where('fingerprint', $fingerprint)
                ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
                ->latest('id')->first();
            if ($recent) {
                throw new DuplicateBroadcastException($recent);
            }
        }

        try {
            $log = DB::transaction(function () use ($channel, $audience, $exam, $content, $admin, $data, $fingerprint) {
                $log = NotificationLog::create($this->logAttributes($channel, $audience, $exam, $content) + [
                    'audience_filters' => $audience + ['chips' => $this->audience->describe($audience), 'exam_context_id' => $exam?->id],
                    'status' => 'queued',
                    'sent_by' => $admin->id,
                    'sent_at' => now(),
                    'idempotency_key' => $data['idempotency_key'],
                    'fingerprint' => $fingerprint,
                ]);

                $this->snapshot($log, $audience, $exam);

                return $log;
            });
        } catch (UniqueConstraintViolationException) {
            // Two identical submits raced; the first one won.
            return [NotificationLog::where('idempotency_key', $data['idempotency_key'])->firstOrFail(), false];
        }

        $this->refreshCounts($log);
        $this->dispatch($log);

        return [$log->fresh(), true];
    }

    /** Writes one delivery row per recipient (and the in-app rows themselves). */
    private function snapshot(NotificationLog $log, array $audience, ?Exam $exam): void
    {
        $channel = $log->channel;
        $query = $this->audience->recipients($audience, $channel)
            ->select(['users.id', 'name', 'email', 'phone', 'phone_e164', 'school', 'city', 'state', 'class_level_id'])
            ->with($this->placeholders->eagerLoads($exam));

        $query->chunkById(500, function (Collection $users) use ($log, $channel, $exam) {
            $now = now();

            $rows = $channel === 'in_app'
                ? $this->inApp->deliverChunk($log, $users, $exam)
                : $users->map(fn (User $u) => [
                    'notification_log_id' => $log->id,
                    'user_id' => $u->id,
                    'channel' => $channel,
                    'recipient_name' => $u->name,
                    'destination' => $channel === 'email' ? $u->email : $u->phone_e164,
                    'status' => 'pending',
                    'attempts' => 0,
                    'sent_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

            NotificationDelivery::insertOrIgnore($rows);
        }, 'users.id', 'id');
    }

    private function logAttributes(string $channel, array $audience, ?Exam $exam, array $content): array
    {
        $singleExam = count($audience['exam_ids']) === 1 ? $audience['exam_ids'][0] : null;
        $singleClass = count($audience['class_level_ids']) === 1 ? $audience['class_level_ids'][0] : null;

        $base = [
            'channel' => $channel,
            'audience' => $audience['mode'] === 'selected' ? 'selected' : ($singleExam ? 'exam' : ($singleClass ? 'class' : 'all')),
            'exam_id' => $exam?->id ?? $singleExam,
            'class_level_id' => $singleClass,
        ];

        return $base + match ($channel) {
            'email' => [
                'title' => Str::limit($content['subject'], 250, ''),
                'subject' => $content['subject'],
                'body_html' => $content['body_html'],
                'message' => Str::limit(trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $content['body_html'])))), 5000, ''),
            ],
            'in_app' => [
                'title' => $content['title'],
                'message' => $content['message'],
                'link' => $content['link'] ?? null,
            ],
            'whatsapp' => [
                'title' => $content['template']['name'],
                'message' => (string) ($content['template']['body'] ?? ''),
                'whatsapp_template' => $content['template'],
            ],
        };
    }

    public function dispatch(NotificationLog $log): void
    {
        if (in_array($log->channel, ['email', 'whatsapp'], true)
            && $log->deliveries()->where('status', 'pending')->exists()) {
            DeliverNotificationBatch::dispatch($log->id);
        }
    }

    /**
     * Atomically claims up to $limit pending deliveries for one job.
     *
     * @return Collection<int, NotificationDelivery>
     */
    public function claim(NotificationLog $log, int $limit): Collection
    {
        $token = (string) Str::uuid();
        $ids = $log->deliveries()->where('status', 'pending')->orderBy('id')->limit($limit)->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        NotificationDelivery::whereIn('id', $ids)->where('status', 'pending')
            ->update(['status' => 'sending', 'claim_token' => $token, 'claimed_at' => now()]);

        return NotificationDelivery::where('claim_token', $token)->orderBy('id')->get();
    }

    /** Puts claimed-but-unsent deliveries back (rate limit, job failure). */
    public function release(Collection|array $deliveryIds): void
    {
        NotificationDelivery::whereIn('id', collect($deliveryIds)->all())->where('status', 'sending')
            ->update(['status' => 'pending', 'claim_token' => null, 'claimed_at' => null]);
    }

    /**
     * Re-queues failed deliveries that a retry can fix.
     *
     * @return int how many were re-queued
     */
    public function retry(NotificationLog $log, ?NotificationDelivery $only = null): int
    {
        if ($log->channel === 'in_app' || $log->channel === 'both') {
            return 0;
        }

        $query = $log->deliveries()->retryable();
        if ($only) {
            $query->whereKey($only->id);
        }

        $count = $query->update([
            'status' => 'pending', 'error' => null, 'error_code' => null,
            'failed_at' => null, 'claim_token' => null, 'claimed_at' => null,
            'provider_message_id' => null, 'delivered_at' => null, 'read_at' => null,
        ]);

        if ($count > 0) {
            $log->update(['status' => 'sending', 'completed_at' => null]);
            $this->refreshCounts($log);
            $this->dispatch($log);
        }

        return $count;
    }

    /** Recomputes counters and status from the delivery rows. */
    public function refreshCounts(NotificationLog $log): NotificationLog
    {
        $counts = $log->deliveries()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($c) => (int) $c);
        $get = fn (string ...$s) => collect($s)->sum(fn ($k) => $counts[$k] ?? 0);

        $read = $log->channel === 'in_app'
            ? StudentNotification::where('notification_log_id', $log->id)->where('is_read', true)->count()
            : $get('read');

        $total = $counts->sum();
        $pending = $get('pending', 'sending');
        $success = $get('sent', 'delivered', 'read');
        $failed = $get('failed');

        $status = match (true) {
            $pending > 0 => $success + $failed > 0 ? 'sending' : 'queued',
            $total === 0 => 'completed',
            $success === 0 => 'failed',
            $failed > 0 => 'partial',
            default => 'completed',
        };

        $log->update([
            'recipient_count' => $total,
            'pending_count' => $pending,
            'sent_count' => $success,
            'delivered_count' => $get('delivered', 'read') + ($log->channel === 'in_app' ? $success : 0),
            'read_count' => $read,
            'failed_count' => $failed,
            'skipped_count' => $get('skipped'),
            'status' => $status,
            'completed_at' => $pending === 0 ? ($log->completed_at ?? now()) : null,
        ]);

        return $log;
    }

    /**
     * Scheduler entry point (`notifications:maintain`, every 5 min).
     *
     * @return array{released: int, resumed: int, synced: int}
     */
    public function maintain(WhatsAppNotificationChannel $whatsapp, int $syncLimit = 300): array
    {
        $released = NotificationDelivery::where('status', 'sending')
            ->where('claimed_at', '<', now()->subMinutes(self::STALE_CLAIM_MINUTES))
            ->update(['status' => 'pending', 'claim_token' => null, 'claimed_at' => null]);

        $resumed = 0;
        NotificationLog::whereIn('status', ['queued', 'sending'])
            ->where('updated_at', '<', now()->subMinutes(5))
            ->get()
            ->each(function (NotificationLog $log) use (&$resumed) {
                $this->refreshCounts($log);
                if ($log->pending_count > 0 && ! $log->deliveries()->where('status', 'sending')->exists()) {
                    $this->dispatch($log);
                    $resumed++;
                }
            });

        $synced = $this->syncWhatsAppStatuses($whatsapp, null, $syncLimit);

        return ['released' => $released, 'resumed' => $resumed, 'synced' => $synced];
    }

    /** Polls Bird for accepted WhatsApp messages from the last 7 days. */
    public function syncWhatsAppStatuses(WhatsAppNotificationChannel $whatsapp, ?NotificationLog $log = null, int $limit = 300): int
    {
        $query = NotificationDelivery::query()
            ->where('channel', 'whatsapp')
            ->whereIn('status', ['sent', 'delivered'])
            ->whereNotNull('provider_message_id')
            ->where('sent_at', '>=', now()->subDays(7))
            ->where(fn ($q) => $q->whereNull('status_checked_at')->orWhere('status_checked_at', '<', now()->subMinutes($log ? 1 : 10)))
            ->orderBy('status_checked_at')->orderBy('id')
            ->limit($limit);

        if ($log) {
            $query->where('notification_log_id', $log->id);
        }

        $synced = 0;
        $logIds = [];
        foreach ($query->get() as $delivery) {
            try {
                $whatsapp->syncStatus($delivery);
                $synced++;
                $logIds[$delivery->notification_log_id] = true;
            } catch (BirdException $e) {
                if ($e->isRateLimited()) {
                    break;
                }
                $delivery->update(['status_checked_at' => now()]);
            }
        }

        NotificationLog::whereIn('id', array_keys($logIds))->get()->each(fn ($l) => $this->refreshCounts($l));

        return $synced;
    }
}
