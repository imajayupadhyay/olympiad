<?php

namespace App\Services\Notifications\WhatsApp;

use App\Services\Notifications\BirdTemplateCatalogue;
use App\Services\Notifications\BirdWhatsAppGateway;

/**
 * Bird behind the provider contract (workflow.md §13.5). A thin wrapper: every Bird
 * call still goes through BirdWhatsAppGateway and templates through
 * BirdTemplateCatalogue (live, never stored), exactly as before provider switching.
 */
class BirdWhatsAppProvider implements WhatsAppProvider
{
    public function __construct(
        private readonly BirdWhatsAppGateway $gateway,
        private readonly BirdTemplateCatalogue $catalogue,
    ) {}

    public function key(): string
    {
        return 'bird';
    }

    public function label(): string
    {
        return 'Bird';
    }

    public function isConfigured(): bool
    {
        return $this->gateway->isConfigured();
    }

    public function canSend(): bool
    {
        return $this->isConfigured();
    }

    public function unavailableReason(): ?string
    {
        return $this->isConfigured()
            ? null
            : 'Bird is not configured. Add the API key and base URL in Settings → Messaging, or BIRD_API_KEY and BIRD_BASE_URL in the server .env.';
    }

    public function sender(): ?string
    {
        return filled(config('services.bird.whatsapp_from')) ? (string) config('services.bird.whatsapp_from') : null;
    }

    public function templateSource(): string
    {
        return 'live';
    }

    public function templates(bool $refresh = false): array
    {
        return $this->catalogue->list($refresh);
    }

    public function template(string $id, bool $refresh = false): ?array
    {
        return $this->catalogue->find($id, $refresh);
    }

    public function canSyncTemplates(): bool
    {
        return false; // always live; nothing to store
    }

    public function syncTemplates(?int $adminId = null): int
    {
        return 0;
    }

    public function send(string $to, array $template, array $variables): array
    {
        return $this->gateway->send($to, (string) $template['slug'], (string) ($template['language'] ?? 'en'), $variables);
    }

    public function supportsStatusSync(): bool
    {
        return true;
    }

    /** Bird: accepted/pending/sent; delivered (delivered_at); read (read_at); failed (+ last_error). */
    public function messageStatus(string $messageId): array
    {
        $message = $this->gateway->message($messageId);
        $failed = ($message['status'] ?? '') === 'failed';
        $code = (string) data_get($message, 'last_error.meta_error_code', data_get($message, 'last_error.code', ''));

        return [
            'status' => (string) ($message['status'] ?? ''),
            'delivered_at' => filled($message['delivered_at'] ?? null) ? (string) $message['delivered_at'] : null,
            'read_at' => filled($message['read_at'] ?? null) ? (string) $message['read_at'] : null,
            'error' => $failed ? (string) (data_get($message, 'last_error.description') ?: 'WhatsApp could not deliver this message.') : null,
            'error_code' => $failed ? ($code ?: 'failed') : null,
            'failed_at' => $failed && filled(data_get($message, 'last_error.occurred_at')) ? (string) data_get($message, 'last_error.occurred_at') : null,
        ];
    }

    public function sendIntervalMs(): int
    {
        return max(0, (int) config('services.bird.send_interval_ms', 500));
    }

    public function batchSize(): int
    {
        return max(1, (int) config('services.bird.batch_size', 40));
    }

    public function maxRateLimitRetries(): int
    {
        return (int) config('services.bird.max_rate_limit_retries', 8);
    }

    public function paceKey(): string
    {
        return 'bird:wa-send:'.substr(hash('sha256', (string) config('services.bird.api_key')), 0, 16);
    }

    public function checkConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => (string) $this->unavailableReason()];
        }

        try {
            $templates = $this->catalogue->list(true);
        } catch (WhatsAppProviderException $e) {
            return ['ok' => false, 'message' => 'Could not reach Bird: '.$e->getMessage()];
        }

        $usable = collect($templates)->where('usable', true)->count();

        return ['ok' => true, 'message' => 'Connected to Bird. '.count($templates).' template(s), '.$usable.' usable for broadcasts.'];
    }
}
