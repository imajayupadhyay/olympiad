<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * WhatsApp templates, always read live from Bird (never hard-coded).
 *
 * - list(): the catalogue, normalised, cached for services.bird.template_cache_minutes
 *   (the "Refresh" button bypasses the cache).
 * - detail(): one template + its live version (header/body/footer/buttons, variables).
 *   A version id is immutable in Bird, so its detail is cached for a day.
 *
 * A template is *usable* for broadcasts only when it is active, its language is
 * approved, it isn't an authentication (OTP) template, and every variable sits in
 * the body (header/button variables can't be sent by WhatsAppNotificationChannel).
 */
class BirdTemplateCatalogue
{
    public function __construct(private readonly BirdWhatsAppGateway $bird) {}

    /** @return array<int, array<string, mixed>> */
    public function list(bool $refresh = false): array
    {
        $key = 'bird:wa-templates:'.$this->accountHash();

        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(max(1, (int) config('services.bird.template_cache_minutes', 10))), function () {
            return collect($this->bird->templates())
                ->map(fn (array $t) => $this->summary($t))
                ->filter(fn (array $t) => filled($t['id']) && filled($t['slug']))
                ->sortBy([['usable', 'desc'], ['name', 'asc']])
                ->values()
                ->all();
        });
    }

    /** @return array<string, mixed>|null */
    public function find(string $templateId, bool $refresh = false): ?array
    {
        $summary = collect($this->list($refresh))->firstWhere('id', $templateId);

        if (! $summary || blank($summary['live_version_id'])) {
            return $summary;
        }

        $version = Cache::remember(
            'bird:wa-template-version:'.$this->accountHash().':'.$summary['live_version_id'],
            now()->addDay(),
            fn () => $this->bird->templateVersion($summary['id'], $summary['live_version_id'])
        );

        return $this->withVersion($summary, $version);
    }

    /** @param array<string, mixed> $t */
    private function summary(array $t): array
    {
        $language = (string) ($t['default_language'] ?? 'en');
        $languageStatus = data_get($t, "languages.{$language}.status");
        $approved = ($t['status'] ?? null) === 'active' && $languageStatus === 'approved';
        $category = (string) ($t['category'] ?? 'utility');

        $reason = match (true) {
            ! $approved => 'Not approved in Bird for '.$language.' yet.',
            $category === 'authentication' => 'Authentication (OTP) templates can’t be used for broadcasts.',
            blank($t['live_version_id'] ?? null) => 'No live version in Bird.',
            default => null,
        };

        return [
            'id' => (string) ($t['id'] ?? ''),
            'slug' => (string) ($t['slug'] ?? ''),
            'name' => (string) ($t['name'] ?? $t['slug'] ?? ''),
            'description' => $t['description'] ?? null,
            'category' => $category,
            'language' => $language,
            'languages' => array_keys((array) ($t['languages'] ?? [])),
            'approval_status' => $languageStatus ?? ($t['status'] ?? 'unknown'),
            'live_version_id' => $t['live_version_id'] ?? null,
            'updated_at' => $t['updated_at'] ?? null,
            'usable' => $reason === null,
            'unusable_reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $version
     */
    private function withVersion(array $summary, array $version): array
    {
        $components = collect(data_get($version, 'languages.'.$summary['language'].'.components', []));
        $header = $components->firstWhere('type', 'header');
        $body = $components->firstWhere('type', 'body');
        $footer = $components->firstWhere('type', 'footer');
        $buttons = collect(data_get($components->firstWhere('type', 'buttons'), 'buttons', []))
            ->map(fn ($b) => ['type' => $b['type'] ?? 'quick_reply', 'text' => (string) ($b['text'] ?? ''), 'url' => $b['url'] ?? null, 'phone' => $b['phone_number'] ?? null])
            ->values()->all();

        $examples = collect($body['example_parameters'] ?? [])->pluck('text', 'name');
        $bodyText = (string) ($body['text'] ?? '');

        $variables = collect($version['variables'] ?? [])
            ->filter(fn ($v) => filled($v['key'] ?? null))
            ->map(fn ($v) => [
                'key' => (string) $v['key'],
                'required' => (bool) ($v['required'] ?? true),
                'type' => $v['type'] ?? 'text',
                'example' => $examples[$v['key']] ?? null,
                'in_body' => Str::contains($bodyText, '{{'.$v['key'].'}}'),
            ])->values();

        $reason = $summary['unusable_reason'];
        if ($reason === null && $variables->contains(fn ($v) => ! $v['in_body'])) {
            $reason = 'Has header or button variables, which broadcasts can’t fill.';
        }

        return array_merge($summary, [
            'header' => ($header['format'] ?? 'text') === 'text' ? ($header['text'] ?? null) : null,
            'header_format' => $header['format'] ?? null,
            'body' => $bodyText,
            'footer' => $footer['text'] ?? null,
            'buttons' => $buttons,
            'variables' => $variables->all(),
            'version_number' => $version['version_number'] ?? null,
            'usable' => $reason === null,
            'unusable_reason' => $reason,
        ]);
    }

    private function accountHash(): string
    {
        return substr(hash('sha256', (string) config('services.bird.api_key')), 0, 16);
    }
}
