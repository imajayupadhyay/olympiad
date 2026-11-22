<?php

namespace App\Services\Notifications\WhatsApp;

/**
 * One WhatsApp messaging provider (workflow.md §13.6). Everything outside a
 * provider's own classes (channel, composer, batch job, status sync, controllers)
 * talks to this contract through WhatsAppProviderManager, never to a gateway.
 *
 * Templates use one normalised shape for every provider:
 *   summary  id, slug (name sent to the provider), name, description, category,
 *            language, languages, approval_status, live_version_id, updated_at,
 *            usable, unusable_reason
 *   detail   summary + header, header_format, body, footer, buttons[], variables[]
 *            ({key, required, type, example, in_body}), version_number
 *
 * To add a provider: implement this interface, add it to
 * WhatsAppProviderManager::PROVIDERS and its fields to MessagingConfigRepository::PROVIDERS.
 */
interface WhatsAppProvider
{
    /** Stable key stored on broadcasts and deliveries (e.g. "bird"). Never rename. */
    public function key(): string;

    public function label(): string;

    /** Required credentials/settings are present. */
    public function isConfigured(): bool;

    /** Configured and able to send real messages right now. */
    public function canSend(): bool;

    /** Why canSend() is false, in admin-readable words; null when it can send. */
    public function unavailableReason(): ?string;

    /** The sender number/channel, unmasked (callers mask it before display). */
    public function sender(): ?string;

    /** "live" = read from the provider's API; "registry" = managed in whatsapp_templates. */
    public function templateSource(): string;

    /** @return array<int, array<string, mixed>> template summaries */
    public function templates(bool $refresh = false): array;

    /** @return array<string, mixed>|null template detail */
    public function template(string $id, bool $refresh = false): ?array;

    public function canSyncTemplates(): bool;

    /** Pulls templates from the provider into the registry. @return int how many were saved */
    public function syncTemplates(?int $adminId = null): int;

    /**
     * Sends one template message.
     *
     * @param  array<string, mixed>  $template  the broadcast's template snapshot
     * @param  array<string, string>  $variables  variable key => final one-line value
     * @return array<string, mixed> at least ['id' => provider message id, 'status' => …]
     *
     * @throws WhatsAppProviderException
     */
    public function send(string $to, array $template, array $variables): array;

    public function supportsStatusSync(): bool;

    /**
     * Normalised status for an accepted message.
     *
     * @return array{status: string, delivered_at: ?string, read_at: ?string, error: ?string, error_code: ?string, failed_at: ?string}
     *
     * @throws WhatsAppProviderException
     */
    public function messageStatus(string $messageId): array;

    /** Minimum milliseconds between two sends on this account (across all workers). */
    public function sendIntervalMs(): int;

    public function batchSize(): int;

    public function maxRateLimitRetries(): int;

    /** Cache key prefix for pacing, unique per provider account. */
    public function paceKey(): string;

    /** Read-only connectivity check. @return array{ok: bool, message: string} */
    public function checkConnection(): array;
}
