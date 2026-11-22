<?php

namespace App\Services\Notifications\WhatsApp;

use App\Models\MessagingProviderConfig;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Provider configuration (workflow.md §13.6). Each field has a value saved from
 * Settings → Messaging (messaging_provider_configs; secrets encrypted with APP_KEY)
 * and an .env fallback (config/services.php). A saved value wins; an empty one
 * falls back to .env, so a server that only has .env keeps working unchanged.
 *
 * apply() writes the effective values into config('services.{provider}.*') so the
 * provider's own classes (e.g. BirdWhatsAppGateway) keep reading config as before.
 * It remembers the .env value of every key it overrides and restores it when the
 * saved value is cleared, so long-running queue workers never keep a stale key.
 * Bound as a singleton (AppServiceProvider) because of that memory.
 *
 * ⚠ Secrets never leave the server: describe() returns only masked values.
 */
class MessagingConfigRepository
{
    public const PROVIDERS = [
        'bird' => [
            'label' => 'Bird',
            'fields' => [
                'api_key' => ['label' => 'API access key', 'secret' => true, 'required' => true, 'env' => 'BIRD_API_KEY', 'help' => 'Bird → Settings → API access keys.'],
                'base_url' => ['label' => 'API base URL', 'type' => 'url', 'required' => true, 'env' => 'BIRD_BASE_URL', 'help' => 'Region endpoint, e.g. https://eu1.platform.bird.com.'],
                'whatsapp_from' => ['label' => 'WhatsApp sender number', 'required' => false, 'env' => 'BIRD_WHATSAPP_FROM', 'help' => 'Optional. The WhatsApp number messages are sent from.'],
            ],
        ],
        'msg91' => [
            'label' => 'MSG91',
            'fields' => [
                'auth_key' => ['label' => 'Auth key', 'secret' => true, 'required' => true, 'env' => 'MSG91_AUTH_KEY', 'help' => 'MSG91 dashboard → Authkey.'],
                'base_url' => ['label' => 'API base URL', 'type' => 'url', 'required' => false, 'env' => 'MSG91_BASE_URL', 'help' => 'Leave as https://control.msg91.com (MSG91’s documented API host) unless MSG91 gives you another.'],
                'integrated_number' => ['label' => 'WhatsApp integrated number', 'required' => true, 'env' => 'MSG91_WHATSAPP_NUMBER', 'help' => 'The WhatsApp Business number connected in MSG91, with country code.'],
            ],
        ],
    ];

    /** @var array<string, array<string, mixed>> provider => field => the .env value we replaced */
    private array $overridden = [];

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys(self::PROVIDERS);
    }

    public function label(string $provider): string
    {
        return self::PROVIDERS[$provider]['label'] ?? Str::upper($provider);
    }

    /** @return array<string, array<string, mixed>> */
    public function fields(string $provider): array
    {
        return self::PROVIDERS[$provider]['fields'] ?? [];
    }

    public function row(string $provider): ?MessagingProviderConfig
    {
        try {
            return MessagingProviderConfig::where('provider', $provider)->first();
        } catch (QueryException) {
            return null; // table not migrated yet: .env only
        }
    }

    /**
     * Saved values for one provider (secrets decrypted, server-side only).
     *
     * @return array{values: array<string, string>, decrypt_error: bool}
     */
    public function stored(string $provider): array
    {
        $row = $this->row($provider);
        if (! $row) {
            return ['values' => [], 'decrypt_error' => false];
        }

        $decryptError = false;
        try {
            $credentials = (array) ($row->credentials ?? []);
        } catch (DecryptException) {
            $credentials = [];   // APP_KEY changed since the key was saved
            $decryptError = true;
        }

        $values = collect((array) ($row->settings ?? []))->merge($credentials)
            ->only(array_keys($this->fields($provider)))
            ->filter(fn ($v) => filled($v))
            ->map(fn ($v) => (string) $v)
            ->all();

        return ['values' => $values, 'decrypt_error' => $decryptError];
    }

    /** Puts the effective values into config('services.{provider}.*'). */
    public function apply(string $provider): void
    {
        $saved = $this->stored($provider)['values'];

        foreach (array_keys($this->fields($provider)) as $field) {
            $configKey = "services.{$provider}.{$field}";
            $isOverridden = array_key_exists($field, $this->overridden[$provider] ?? []);

            if (filled($saved[$field] ?? null)) {
                if (! $isOverridden) {
                    $this->overridden[$provider][$field] = config($configKey);
                }
                config([$configKey => $saved[$field]]);
            } elseif ($isOverridden) {
                config([$configKey => $this->overridden[$provider][$field]]);
                unset($this->overridden[$provider][$field]);
            }
        }
    }

    /** The .env value of a field, even while a saved value is applied. */
    private function envValue(string $provider, string $field): mixed
    {
        return array_key_exists($field, $this->overridden[$provider] ?? [])
            ? $this->overridden[$provider][$field]
            : config("services.{$provider}.{$field}");
    }

    /**
     * Field list for the settings page. Secrets are masked; nothing secret is returned.
     *
     * @return array{fields: array<int, array<string, mixed>>, decrypt_error: bool, updated_at: mixed, updated_by: ?string}
     */
    public function describe(string $provider): array
    {
        $stored = $this->stored($provider);
        $row = $this->row($provider);

        $fields = collect($this->fields($provider))->map(function (array $def, string $field) use ($provider, $stored) {
            $saved = $stored['values'][$field] ?? null;
            $env = $this->envValue($provider, $field);
            $effective = filled($saved) ? $saved : (filled($env) ? (string) $env : null);
            $secret = (bool) ($def['secret'] ?? false);

            return [
                'key' => $field,
                'label' => $def['label'],
                'secret' => $secret,
                'type' => $def['type'] ?? 'text',
                'required' => (bool) ($def['required'] ?? false),
                'help' => $def['help'] ?? null,
                'env' => $def['env'] ?? null,
                'source' => filled($saved) ? 'database' : (filled($env) ? 'env' : 'none'),
                'saved' => filled($saved),
                'value' => $secret ? null : $saved,          // the form only edits the saved value
                'effective' => $secret ? null : $effective,
                'masked' => $secret && $effective ? $this->mask($effective) : null,
            ];
        })->values()->all();

        return [
            'fields' => $fields,
            'decrypt_error' => $stored['decrypt_error'],
            'updated_at' => $row?->updated_at,
            'updated_by' => $row?->updatedBy?->name,
        ];
    }

    /**
     * Saves the settings form. Secrets: blank keeps the saved value, `clear.{field}`
     * removes it (back to .env). Other fields: blank removes the saved value.
     *
     * @param  array<string, mixed>  $input
     */
    public function save(string $provider, array $input, User $admin): MessagingProviderConfig
    {
        $existing = $this->stored($provider)['values'];
        $settings = [];
        $credentials = [];

        foreach ($this->fields($provider) as $field => $def) {
            $value = trim((string) ($input[$field] ?? ''));
            $clear = (bool) data_get($input, "clear.{$field}", false);

            if ($def['secret'] ?? false) {
                if ($clear) {
                    continue;
                }
                $keep = $value !== '' ? $value : ($existing[$field] ?? null);
                if (filled($keep)) {
                    $credentials[$field] = $keep;
                }
            } elseif ($value !== '' && ! $clear) {
                $settings[$field] = ($def['type'] ?? null) === 'url' ? rtrim($value, '/') : $value;
            }
        }

        $row = MessagingProviderConfig::updateOrCreate(['provider' => $provider], [
            'settings' => $settings ?: null,
            'credentials' => $credentials ?: null,
            'updated_by' => $admin->id,
            // A changed configuration invalidates the last connection check.
            'last_checked_at' => null,
            'last_check_ok' => null,
            'last_check_message' => null,
        ]);

        $this->apply($provider);

        return $row;
    }

    public function recordCheck(string $provider, bool $ok, string $message): void
    {
        try {
            MessagingProviderConfig::updateOrCreate(['provider' => $provider], [
                'last_checked_at' => now(),
                'last_check_ok' => $ok,
                'last_check_message' => Str::limit($message, 490),
            ]);
        } catch (QueryException) {
            // not migrated yet; the check result is still returned to the admin
        }
    }

    /** @return array{ok: ?bool, message: ?string, at: mixed} */
    public function lastCheck(string $provider): array
    {
        $row = $this->row($provider);

        return ['ok' => $row?->last_check_ok, 'message' => $row?->last_check_message, 'at' => $row?->last_checked_at];
    }

    private function mask(string $secret): string
    {
        return strlen($secret) > 8 ? '••••••'.substr($secret, -4) : '••••••';
    }
}
