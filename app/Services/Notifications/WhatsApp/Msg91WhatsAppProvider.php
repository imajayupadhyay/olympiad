<?php

namespace App\Services\Notifications\WhatsApp;

use App\Models\WhatsAppTemplate;
use Illuminate\Support\Carbon;

/**
 * MSG91 behind the provider contract (workflow.md §13.6). Templates come from the
 * local registry (whatsapp_templates, provider = msg91) and are managed in
 * Settings → Messaging, added by hand or synced from MSG91. Every HTTP call goes
 * through Msg91WhatsAppGateway.
 *
 * MSG91's specs don't document the response fields of the template list or the
 * delivery logs, so both are read defensively: anything that can't be recognised is
 * skipped (sync) or leaves the delivery as it is (status), never guessed.
 */
class Msg91WhatsAppProvider implements WhatsAppProvider
{
    public function __construct(
        private readonly Msg91WhatsAppGateway $gateway,
        private readonly WhatsAppTemplateRegistry $registry,
    ) {}

    public function key(): string
    {
        return 'msg91';
    }

    public function label(): string
    {
        return 'MSG91';
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
            : 'MSG91 is not configured. Add the auth key and WhatsApp integrated number in Settings → Messaging, or MSG91_AUTH_KEY and MSG91_WHATSAPP_NUMBER in the server .env.';
    }

    public function sender(): ?string
    {
        return filled(config('services.msg91.integrated_number')) ? (string) config('services.msg91.integrated_number') : null;
    }

    public function templateSource(): string
    {
        return 'registry';
    }

    public function templates(bool $refresh = false): array
    {
        return $this->registry->list($this->key());
    }

    public function template(string $id, bool $refresh = false): ?array
    {
        return $this->registry->find($this->key(), $id);
    }

    public function canSyncTemplates(): bool
    {
        return $this->canSend();
    }

    /**
     * Adds or updates registry rows from MSG91's template list. Never deletes, and
     * never touches what admins own here (display name, examples, on/off switch).
     * Returns how many rows were added or updated.
     */
    public function syncTemplates(?int $adminId = null): int
    {
        $json = $this->gateway->templates();
        $items = $this->listFrom($json);
        $found = 0;
        $saved = 0;

        foreach ($items as $item) {
            foreach ($this->variantsOf($item) as $variant) {
                $found++;
                if ($this->saveVariant($variant, $adminId)) {
                    $saved++;
                }
            }
        }

        if ($items !== [] && $found > 0 && $saved === 0) {
            throw new Msg91Exception('MSG91 returned '.$found.' template(s), but none were in a format this page can read. Add them by hand instead.', 200, null, 'unrecognised_templates');
        }

        return $saved;
    }

    public function send(string $to, array $template, array $variables): array
    {
        return $this->gateway->send($to, (string) $template['slug'], (string) ($template['language'] ?? 'en'), $variables);
    }

    public function supportsStatusSync(): bool
    {
        return $this->gateway->supportsStatusPolling();
    }

    /** Reads the delivery log for one request_id; an unrecognised log leaves the status as it is. */
    public function messageStatus(string $messageId): array
    {
        $rows = $this->listFrom($this->gateway->message($messageId));
        $row = collect($rows)->first(fn ($r) => is_array($r) && filled($this->first($r, ['status', 'message_status', 'messageStatus'])));

        $raw = strtolower((string) ($row ? $this->first($row, ['status', 'message_status', 'messageStatus']) : ''));
        $status = match (true) {
            in_array($raw, ['read', 'seen'], true) => 'read',
            $raw === 'delivered' => 'delivered',
            in_array($raw, ['failed', 'rejected', 'undelivered', 'error'], true) => 'failed',
            default => '',
        };
        $at = $row ? $this->time($this->first($row, ['updated_at', 'updatedAt', 'status_time', 'timestamp'])) : null;

        return [
            'status' => $status,
            'delivered_at' => in_array($status, ['delivered', 'read'], true) ? ($at ?? now()->toIso8601String()) : null,
            'read_at' => $status === 'read' ? ($at ?? now()->toIso8601String()) : null,
            'error' => $status === 'failed' ? $this->text($this->first($row, ['failure_reason', 'failureReason', 'reason', 'error', 'description'])) : null,
            'error_code' => $status === 'failed' ? $this->text($this->first($row, ['error_code', 'errorCode', 'code'])) : null,
            'failed_at' => $status === 'failed' ? $at : null,
        ];
    }

    public function sendIntervalMs(): int
    {
        return max(0, (int) config('services.msg91.send_interval_ms', 500));
    }

    public function batchSize(): int
    {
        return max(1, (int) config('services.msg91.batch_size', 40));
    }

    public function maxRateLimitRetries(): int
    {
        return (int) config('services.msg91.max_rate_limit_retries', 8);
    }

    public function paceKey(): string
    {
        return 'msg91:wa-send:'.substr(hash('sha256', (string) config('services.msg91.auth_key')), 0, 16);
    }

    /** Validates the auth key with MSG91's read-only "WhatsApp numbers" call. */
    public function checkConnection(): array
    {
        if (! $this->canSend()) {
            return ['ok' => false, 'message' => (string) $this->unavailableReason()];
        }

        try {
            $json = $this->gateway->numbers();
        } catch (WhatsAppProviderException $e) {
            return ['ok' => false, 'message' => 'Could not reach MSG91: '.$e->getMessage()];
        }

        $number = $this->gateway->integratedNumber();
        $listed = collect($json)->flatten()
            ->filter(fn ($v) => is_scalar($v))
            ->contains(fn ($v) => Msg91WhatsAppGateway::digits((string) $v) === $number);

        return $listed
            ? ['ok' => true, 'message' => 'Connected to MSG91. The integrated number is active on this account.']
            : ['ok' => true, 'message' => 'Connected to MSG91 (the auth key was accepted), but the integrated number wasn’t found in the account’s WhatsApp numbers. Check it matches the number in MSG91, with the country code.'];
    }

    /** A list of rows from a response that is either a list or wraps one in data/templates/logs. */
    private function listFrom(array $json): array
    {
        foreach (['data', 'templates', 'logs'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                $inner = $json[$key];

                return array_is_list($inner) ? array_values(array_filter($inner, 'is_array')) : $this->listFrom($inner);
            }
        }

        return array_is_list($json) ? array_values(array_filter($json, 'is_array')) : [];
    }

    /**
     * One template may list several languages (`languages: [...]`) or be one
     * language already (Meta's shape). Each variant is flattened to
     * {name, category, language, status, id, components}.
     */
    private function variantsOf(array $item): array
    {
        $languages = isset($item['languages']) && is_array($item['languages']) && array_is_list($item['languages'])
            ? $item['languages'] : [$item];

        return collect($languages)->filter(fn ($l) => is_array($l))->map(function (array $l) use ($item) {
            $language = $l['language'] ?? (is_string($l['code'] ?? null) ? $l['code'] : null) ?? $item['language'] ?? null;
            if (is_array($language)) {
                $language = $language['code'] ?? null;
            }
            $components = $l['components'] ?? (is_array($l['code'] ?? null) ? $l['code'] : null) ?? $item['components'] ?? [];

            return [
                'name' => $item['name'] ?? $item['template_name'] ?? null,
                'category' => $l['category'] ?? $item['category'] ?? null,
                'language' => $language,
                'status' => ! empty($l['is_disabled']) ? 'DISABLED' : ($l['status'] ?? $item['status'] ?? null),
                'id' => $l['id'] ?? $item['id'] ?? null,
                'components' => is_array($components) ? $components : [],
                'variables' => is_array($l['variables'] ?? null) ? array_values(array_filter($l['variables'], 'is_string')) : null,
            ];
        })->values()->all();
    }

    private function saveVariant(array $v, ?int $adminId): bool
    {
        $name = is_string($v['name']) ? trim($v['name']) : '';
        $language = is_string($v['language']) ? trim($v['language']) : '';
        $components = collect($v['components'])->filter(fn ($c) => is_array($c))->keyBy(fn ($c) => strtoupper((string) ($c['type'] ?? '')));
        $body = $components->get('BODY')['text'] ?? null;

        if (! preg_match(WhatsAppTemplateRegistry::NAME_PATTERN, $name)
            || ! preg_match(WhatsAppTemplateRegistry::LANGUAGE_PATTERN, $language)
            || ! is_string($body) || trim($body) === '') {
            return false;
        }

        $header = $components->get('HEADER');
        $headerFormat = strtolower((string) ($header['format'] ?? ($header ? 'text' : '')));
        $category = strtolower((string) $v['category']);
        $status = strtoupper((string) $v['status']);

        $template = WhatsAppTemplate::forProvider($this->key())->where('name', $name)->where('language', $language)->first()
            ?? new WhatsAppTemplate(['provider' => $this->key(), 'name' => $name, 'language' => $language, 'source' => 'synced', 'is_active' => true, 'created_by' => $adminId]);

        $template->fill([
            'category' => array_key_exists($category, WhatsAppTemplate::CATEGORIES) ? $category : 'utility',
            'external_id' => is_scalar($v['id']) && filled($v['id']) ? mb_substr((string) $v['id'], 0, 100) : $template->external_id,
            'header' => $headerFormat === 'text' && is_string($header['text'] ?? null) ? mb_substr($header['text'], 0, 60) : null,
            'body' => mb_substr(trim($body), 0, 1024),
            'footer' => is_string($components->get('FOOTER')['text'] ?? null) ? mb_substr($components->get('FOOTER')['text'], 0, 60) : null,
            'buttons' => $this->buttons($components->get('BUTTONS')['buttons'] ?? []) ?: null,
            'approval_status' => match (true) {
                $status === 'APPROVED' => 'approved',
                in_array($status, ['REJECTED', 'DISABLED', 'PAUSED'], true) => 'rejected',
                default => 'pending',
            },
            'synced_at' => now(),
            'meta' => array_filter(['header_format' => $headerFormat ?: null, 'msg91_status' => $status ?: null, 'msg91_variables' => $v['variables'] ?? null]),
            'updated_by' => $adminId,
        ]);

        if (blank($template->variable_examples)) {
            $template->variable_examples = array_intersect_key(
                $this->examples((array) ($components->get('BODY')['example'] ?? [])),
                array_flip($template->bodyVariables()),
            ) ?: null;
        }

        $template->save();

        return true;
    }

    /**
     * Example values keyed by variable: named templates list
     * `body_text_named_params[{param_name, example}]`, positional ones `body_text[[…]]`.
     *
     * @return array<string, string>
     */
    private function examples(array $example): array
    {
        $named = collect(is_array($example['body_text_named_params'] ?? null) ? $example['body_text_named_params'] : [])
            ->filter(fn ($p) => is_array($p) && is_string($p['param_name'] ?? null) && is_scalar($p['example'] ?? null))
            ->mapWithKeys(fn ($p) => [$p['param_name'] => mb_substr((string) $p['example'], 0, 200)])->all();

        $positional = $example['body_text'][0] ?? [];
        $positional = is_array($positional)
            ? collect(array_values($positional))->filter(fn ($e) => is_scalar($e))->mapWithKeys(fn ($e, $i) => [(string) ($i + 1) => mb_substr((string) $e, 0, 200)])->all()
            : [];

        return $named + $positional;
    }

    private function buttons(mixed $buttons): array
    {
        return collect(is_array($buttons) ? $buttons : [])->filter(fn ($b) => is_array($b) && filled($b['text'] ?? null))
            ->map(fn (array $b) => array_filter([
                'type' => match (strtoupper((string) ($b['type'] ?? ''))) {
                    'URL' => 'url',
                    'PHONE_NUMBER' => 'phone_number',
                    default => 'quick_reply',
                },
                'text' => mb_substr((string) $b['text'], 0, 25),
                'url' => is_string($b['url'] ?? null) ? $b['url'] : null,
                'phone' => is_string($b['phone_number'] ?? null) ? $b['phone_number'] : null,
            ]))->take(10)->values()->all();
    }

    private function first(?array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private function text(mixed $value): ?string
    {
        return is_scalar($value) && filled($value) ? mb_substr((string) $value, 0, 300) : null;
    }

    private function time(mixed $value): ?string
    {
        if (! is_scalar($value) || blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $seconds = (int) $value > 9_999_999_999 ? intdiv((int) $value, 1000) : (int) $value;

                return Carbon::createFromTimestamp($seconds)->toIso8601String();
            }

            return Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
