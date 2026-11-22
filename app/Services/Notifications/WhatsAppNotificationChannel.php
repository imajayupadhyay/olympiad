<?php

namespace App\Services\Notifications;

use App\Models\Exam;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\WhatsApp\WhatsAppProvider;
use App\Services\Notifications\WhatsApp\WhatsAppProviderException;
use App\Services\Notifications\WhatsApp\WhatsAppProviderManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * WhatsApp channel: one template message per delivery, sent through the provider the
 * broadcast was created with (WhatsAppProviderManager::forLog, workflow.md §13.6).
 *
 * The broadcast stores an immutable template snapshot (slug, language, body and the
 * admin's variable mapping). Each variable is either a placeholder or text that may
 * contain {{placeholders}}, with an optional fallback. WhatsApp rejects newlines/tabs
 * and long space runs inside parameters, so values are collapsed to one line. A
 * required variable that is still blank skips that student (never sent half-filled).
 *
 * Sends are paced across every worker (the provider's sendIntervalMs) with a shared
 * cache slot per provider account; a 429 is re-thrown so DeliverNotificationBatch can
 * back off.
 */
class WhatsAppNotificationChannel
{
    /** Meta error codes a resend can't fix (e.g. 131026 = number not on WhatsApp). */
    public const PERMANENT_META_ERRORS = ['131026', '131021', '131009'];

    public function __construct(
        private readonly WhatsAppProviderManager $providers,
        private readonly NotificationPlaceholderService $placeholders,
    ) {}

    public function deliver(NotificationDelivery $delivery, NotificationLog $log, User $user, ?Exam $exam, ?WhatsAppProvider $provider = null): void
    {
        $template = (array) $log->whatsapp_template;
        $provider ??= $this->providers->forLog($log);

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
            'provider' => $provider->key(),
            'payload' => ['variables' => $variables],
            'attempts' => $delivery->attempts + 1,
        ]);

        try {
            $message = $this->paced($provider, fn () => $provider->send($user->phone_e164, $template, $variables));
        } catch (WhatsAppProviderException $e) {
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
     * Pulls the provider's current status for an accepted message (the provider that
     * sent it, not the active one). The provider normalises its payload:
     * sent; delivered (delivered_at); read (read_at); failed (+ error, error_code).
     */
    public function syncStatus(NotificationDelivery $delivery, ?WhatsAppProvider $provider = null): void
    {
        if (blank($delivery->provider_message_id)) {
            return;
        }

        $provider ??= $this->providers->forDelivery($delivery);
        $message = $provider->messageStatus($delivery->provider_message_id);
        $status = (string) ($message['status'] ?? '');
        $updates = ['status_checked_at' => now()];

        if (filled($message['delivered_at'] ?? null)) {
            $updates['delivered_at'] = Carbon::parse($message['delivered_at']);
        }
        if (filled($message['read_at'] ?? null)) {
            $updates['read_at'] = Carbon::parse($message['read_at']);
        }

        if ($status === 'failed') {
            $code = (string) ($message['error_code'] ?? '');
            $updates += [
                'status' => 'failed',
                'error' => (string) (($message['error'] ?? null) ?: 'WhatsApp could not deliver this message.'),
                'error_code' => $code ?: 'failed',
                'permanent_failure' => in_array($code, self::PERMANENT_META_ERRORS, true),
                'failed_at' => filled($message['failed_at'] ?? null) ? Carbon::parse($message['failed_at']) : now(),
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

    /** One send at a time per provider account, at least sendIntervalMs apart, across all workers. */
    private function paced(WhatsAppProvider $provider, callable $send): array
    {
        $key = $provider->paceKey();
        $interval = $provider->sendIntervalMs();

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
