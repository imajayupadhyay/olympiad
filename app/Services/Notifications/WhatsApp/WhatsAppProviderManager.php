<?php

namespace App\Services\Notifications\WhatsApp;

use App\Models\MessagingSetting;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * The one place that decides which WhatsApp provider handles a message
 * (workflow.md §13.6).
 *
 *   active()      the provider selected in Settings → Messaging; used for NEW
 *                 broadcasts, previews, template lists and composer test sends.
 *   forLog()      the provider a broadcast was created with (notification_logs.provider).
 *                 Queued batches, retries and status sync always use it, so switching
 *                 providers never re-routes or breaks a broadcast already sent.
 *   provider($k)  a specific provider (settings page: configure/test the inactive one).
 *
 * Every provider() call re-applies the saved configuration (MessagingConfigRepository),
 * so queue workers pick up changed keys without a restart.
 */
class WhatsAppProviderManager
{
    public const DEFAULT = 'bird';

    /** key => class. Add new providers here (and their fields in MessagingConfigRepository). */
    public const PROVIDERS = [
        'bird' => BirdWhatsAppProvider::class,
        'msg91' => Msg91WhatsAppProvider::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly MessagingConfigRepository $configs,
    ) {}

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys(self::PROVIDERS);
    }

    public function exists(?string $key): bool
    {
        return $key !== null && isset(self::PROVIDERS[$key]);
    }

    /** Display name without resolving the provider (no config is applied). */
    public function label(string $key): string
    {
        return $this->configs->label($key);
    }

    public function provider(string $key): WhatsAppProvider
    {
        if (! $this->exists($key)) {
            throw new InvalidArgumentException("Unknown WhatsApp provider [{$key}].");
        }

        $this->configs->apply($key);

        return $this->container->make(self::PROVIDERS[$key]);
    }

    /** @return array<string, WhatsAppProvider> */
    public function all(): array
    {
        return collect($this->keys())->mapWithKeys(fn (string $k) => [$k => $this->provider($k)])->all();
    }

    public function activeKey(): string
    {
        try {
            $key = MessagingSetting::current()->whatsapp_provider;
        } catch (QueryException) {
            return self::DEFAULT; // not migrated yet
        }

        return $this->exists($key) ? $key : self::DEFAULT;
    }

    public function active(): WhatsAppProvider
    {
        return $this->provider($this->activeKey());
    }

    /** Provider pinned on a broadcast; broadcasts from before switching existed are Bird. */
    public function keyForLog(NotificationLog $log): string
    {
        $key = $log->provider ?: data_get($log->whatsapp_template, 'provider');

        return $this->exists($key) ? $key : self::DEFAULT;
    }

    public function forLog(NotificationLog $log): WhatsAppProvider
    {
        return $this->provider($this->keyForLog($log));
    }

    public function forDelivery(NotificationDelivery $delivery): WhatsAppProvider
    {
        return $this->provider($this->exists($delivery->provider) ? $delivery->provider : self::DEFAULT);
    }

    /** Switches the provider used for new WhatsApp broadcasts. Nothing else changes. */
    public function switchTo(string $key, User $admin): MessagingSetting
    {
        if (! $this->exists($key)) {
            throw new InvalidArgumentException("Unknown WhatsApp provider [{$key}].");
        }

        $setting = MessagingSetting::current();
        if ($setting->whatsapp_provider !== $key) {
            $setting->update(['whatsapp_provider' => $key, 'updated_by' => $admin->id, 'switched_at' => now()]);
        }

        return $setting;
    }
}
