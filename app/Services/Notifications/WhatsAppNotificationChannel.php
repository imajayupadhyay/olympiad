<?php

namespace App\Services\Notifications;

use App\Models\Exam;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * WhatsApp channel: one Bird template message per delivery.
 *
 * The broadcast stores an immutable template snapshot (slug, language, body and the
 * admin's variable mapping). Each variable is either a placeholder or text that may
 * contain {{placeholders}}, with an optional fallback. WhatsApp rejects newlines/tabs
 * and long space runs inside parameters, so values are collapsed to one line. A
 * required variable that is still blank skips that student (never sent half-filled).
 *
 * Sends are paced across every worker (services.bird.send_interval_ms) with a shared
 * cache slot; a 429 is re-thrown so DeliverNotificationBatch can back off.
 */
class WhatsAppNotificationChannel
{
    /** Meta error codes a resend can't fix (e.g. 131026 = number not on WhatsApp). */
    public const PERMANENT_META_ERRORS = ['131026', '131021', '131009'];

    public function __construct(
        private readonly BirdWhatsAppGateway $bird,
        private readonly NotificationPlaceholderService $placeholders,
    ) {}

    public function deliver(NotificationDelivery $delivery, NotificationLog $log, User $user, ?Exam $exam): void
    {
        $template = (array) $log->whatsapp_template;

        if (blank($user->phone_e164)) {
            $delivery->markSkipped('Student has no WhatsApp number on file.', 'no_phone');

            return;
        }

        [$variables, $missing] = $this->variables($template, $this->placeholders->valuesFor($user, $exam));

        if ($missing) {
            $delivery->update(['payload' => ['variables' => $variables]]);
            $delivery->markSkipped('No value for '.implode(', ', $missing).' and no fallback set.', 'blank_variable');

            return;
        }

        $delivery->update([
            'destination' => $user->phone_e164,
            'payload' => ['variables' => $variables],
            'attempts' => $delivery->attempts + 1,
        ]);

        try {
            $message = $this->paced(fn () => $this->bird->send(
                $user->phone_e164,
                (string) $template['slug'],
                (string) ($template['language'] ?? 'en'),
                $variables,
            ));
        } catch (BirdException $e) {
            if ($e->isRateLimited()) {
                throw $e;
            }

            $delivery->markFailed($e->getMessage(), 'http_'.$e->status);

            return;
        }

        $delivery->update([
            'status' => 'sent',
            'provider_message_id' => $message['id'] ?? null,
            'sent_at' => now(),
            'error' => null,
            'error_code' => null,
            'claim_token' => null,
        ]);
    }

    /**
     * Final values for one student.
     *
     * @return array{0: array<string, string>, 1: array<int, string>} [variables, missing keys]
     */
    public function variables(array $template, array $values): array
    {
        $result = [];
        $missing = [];

        foreach ((array) ($template['variables'] ?? []) as $variable) {
            $key = (string) $variable['key'];
            $raw = ($variable['source'] ?? 'text') === 'placeholder'
                ? (string) ($values[$variable['value'] ?? ''] ?? '')
                : $this->placeholders->renderText((string) ($variable['value'] ?? ''), $values);

            $value = $this->clean($raw);
            if ($value === '' && filled($variable['fallback'] ?? null)) {
                $value = $this->clean($this->placeholders->renderText((string) $variable['fallback'], $values));
            }

            if ($value === '' && ($variable['required'] ?? true)) {
                $missing[] = '{{'.$key.'}}'.(filled($variable['label'] ?? null) ? ' ('.$variable['label'].')' : '');
            }

            $result[$key] = $value;
        }

        return [$result, $missing];
    }

    /** The template body as one student will see it. */
    public function renderBody(array $template, array $variables): string
    {
        return (string) preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', fn ($m) => $variables[$m[1]] ?? $m[0], (string) ($template['body'] ?? ''));
    }

    /**
     * Pulls Bird's current status for an accepted message.
     * accepted/pending/sent → sent; delivered; read (read_at); failed (+ last_error).
     */
    public function syncStatus(NotificationDelivery $delivery): void
    {
        if (blank($delivery->provider_message_id)) {
            return;
        }

        $message = $this->bird->message($delivery->provider_message_id);
        $status = (string) ($message['status'] ?? '');
        $updates = ['status_checked_at' => now()];

        if (filled($message['delivered_at'] ?? null)) {
            $updates['delivered_at'] = Carbon::parse($message['delivered_at']);
        }
        if (filled($message['read_at'] ?? null)) {
            $updates['read_at'] = Carbon::parse($message['read_at']);
        }

        if ($status === 'failed') {
            $code = (string) data_get($message, 'last_error.meta_error_code', data_get($message, 'last_error.code', ''));
            $updates += [
                'status' => 'failed',
                'error' => (string) (data_get($message, 'last_error.description') ?: 'WhatsApp could not deliver this message.'),
                'error_code' => $code ?: 'failed',
                'permanent_failure' => in_array($code, self::PERMANENT_META_ERRORS, true),
                'failed_at' => filled(data_get($message, 'last_error.occurred_at')) ? Carbon::parse(data_get($message, 'last_error.occurred_at')) : now(),
            ];
        } elseif (isset($updates['read_at'])) {
            $updates['status'] = 'read';
        } elseif (isset($updates['delivered_at']) || $status === 'delivered') {
            $updates['status'] = 'delivered';
        }

        $delivery->update($updates);
    }

    private function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** One send at a time per Bird account, at least send_interval_ms apart, across all workers. */
    private function paced(callable $send): array
    {
        $key = 'bird:wa-send:'.substr(hash('sha256', (string) config('services.bird.api_key')), 0, 16);
        $interval = max(0, (int) config('services.bird.send_interval_ms', 500));

        return Cache::lock($key.':lock', 30)->block(25, function () use ($key, $interval, $send) {
            $wait = (int) Cache::get($key.':next', 0) - (int) floor(microtime(true) * 1000);
            if ($wait > 0) {
                usleep(min($wait, 5000) * 1000);
            }

            try {
                return $send();
            } finally {
                Cache::put($key.':next', (int) floor(microtime(true) * 1000) + $interval, 60);
            }
        });
    }
}
