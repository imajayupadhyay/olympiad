<?php

namespace App\Services\Notifications\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Validation\Rule;

/**
 * Locally managed WhatsApp templates (whatsapp_templates) for providers whose
 * catalogue isn't read live (MSG91). Every row is scoped by `provider`, so one
 * provider's templates are never shown to, changed by or deleted with another's.
 *
 * list()/find() return the same normalised shape as BirdTemplateCatalogue, so the
 * composer, preview and send path don't care where a template came from.
 * A template is usable when it is active, approved, not an authentication (OTP)
 * template, and has no header variables (broadcasts only fill body variables).
 */
class WhatsAppTemplateRegistry
{
    public const NAME_PATTERN = '/^[a-z0-9_]+$/';

    public const LANGUAGE_PATTERN = '/^[a-z]{2,3}(_[A-Z]{2})?$/';

    /** @return array<int, array<string, mixed>> */
    public function list(string $provider): array
    {
        return WhatsAppTemplate::forProvider($provider)->get()
            ->map(fn (WhatsAppTemplate $t) => $this->summary($t))
            ->sortBy([['usable', 'desc'], ['name', 'asc']])
            ->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function find(string $provider, string $id): ?array
    {
        if (! ctype_digit($id)) {
            return null;
        }

        $template = WhatsAppTemplate::forProvider($provider)->find((int) $id);

        return $template ? $this->detail($template) : null;
    }

    /** Full rows for the management table. */
    public function rows(string $provider): array
    {
        return WhatsAppTemplate::forProvider($provider)->orderBy('name')->orderBy('language')->get()
            ->map(fn (WhatsAppTemplate $t) => $this->detail($t) + [
                'display_name' => $t->display_name,
                'external_id' => $t->external_id,
                'is_active' => $t->is_active,
                'source' => $t->source,
                'synced_at' => $t->synced_at,
                'variable_examples' => (object) ($t->variable_examples ?? []),
            ])->all();
    }

    public function rules(string $provider, ?string $language, ?WhatsAppTemplate $template = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:'.self::NAME_PATTERN,
                Rule::unique('whatsapp_templates', 'name')
                    ->where(fn ($q) => $q->where('provider', $provider)->where('language', (string) $language))
                    ->ignore($template?->id)],
            'display_name' => ['nullable', 'string', 'max:150'],
            'language' => ['required', 'string', 'max:12', 'regex:'.self::LANGUAGE_PATTERN],
            'category' => ['required', Rule::in(array_keys(WhatsAppTemplate::CATEGORIES))],
            'external_id' => ['nullable', 'string', 'max:100'],
            'header' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:10'],
            'buttons.*.type' => ['required', Rule::in(['quick_reply', 'url', 'phone_number'])],
            'buttons.*.text' => ['required', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2000'],
            'variable_examples' => ['nullable', 'array', 'max:30'],
            'variable_examples.*' => ['nullable', 'string', 'max:200'],
            'approval_status' => ['required', Rule::in(array_keys(WhatsAppTemplate::APPROVAL_STATUSES))],
            'is_active' => ['boolean'],
        ];
    }

    public function save(string $provider, array $data, User $admin, ?WhatsAppTemplate $template = null): WhatsAppTemplate
    {
        $template ??= new WhatsAppTemplate(['provider' => $provider, 'source' => 'manual', 'created_by' => $admin->id]);

        $examples = collect((array) ($data['variable_examples'] ?? []))
            ->map(fn ($v) => trim((string) $v))->filter()->all();

        $template->fill([
            'name' => $data['name'],
            'display_name' => filled($data['display_name'] ?? null) ? trim($data['display_name']) : null,
            'language' => $data['language'],
            'category' => $data['category'],
            'external_id' => filled($data['external_id'] ?? null) ? trim($data['external_id']) : null,
            'header' => filled($data['header'] ?? null) ? trim($data['header']) : null,
            'body' => trim((string) $data['body']),
            'footer' => filled($data['footer'] ?? null) ? trim($data['footer']) : null,
            'buttons' => array_values((array) ($data['buttons'] ?? [])) ?: null,
            'approval_status' => $data['approval_status'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'updated_by' => $admin->id,
        ]);
        $template->variable_examples = array_intersect_key($examples, array_flip($template->bodyVariables())) ?: null;
        $template->save();

        return $template;
    }

    private function summary(WhatsAppTemplate $t): array
    {
        $reason = match (true) {
            ! $t->is_active => 'Switched off in Settings → Messaging.',
            $t->approval_status !== 'approved' => 'Not approved for '.$t->language.' yet.',
            $t->category === 'authentication' => 'Authentication (OTP) templates can’t be used for broadcasts.',
            $t->headerHasVariables() => 'Has header variables, which broadcasts can’t fill.',
            ! in_array($t->meta['header_format'] ?? 'text', ['text', ''], true) => 'Has a media header ('.$t->meta['header_format'].'), which broadcasts can’t fill.',
            collect((array) ($t->buttons ?? []))->contains(fn ($b) => str_contains((string) ($b['url'] ?? ''), '{{')) => 'Has a link button with a variable, which broadcasts can’t fill.',
            default => null,
        };

        return [
            'id' => (string) $t->id,
            'slug' => $t->name,
            'name' => $t->display_name ?: $t->name,
            'description' => null,
            'category' => $t->category,
            'language' => $t->language,
            'languages' => [$t->language],
            'approval_status' => $t->approval_status,
            'live_version_id' => null,
            'updated_at' => $t->updated_at,
            'usable' => $reason === null,
            'unusable_reason' => $reason,
        ];
    }

    private function detail(WhatsAppTemplate $t): array
    {
        $examples = (array) ($t->variable_examples ?? []);

        return array_merge($this->summary($t), [
            'header' => $t->header,
            'header_format' => $t->header ? 'text' : null,
            'body' => $t->body,
            'footer' => $t->footer,
            'buttons' => collect((array) ($t->buttons ?? []))
                ->map(fn ($b) => ['type' => $b['type'] ?? 'quick_reply', 'text' => (string) ($b['text'] ?? ''), 'url' => $b['url'] ?? null, 'phone' => $b['phone'] ?? null])
                ->values()->all(),
            'variables' => collect($t->bodyVariables())->map(fn (string $key) => [
                'key' => $key,
                'required' => true,
                'type' => 'text',
                'example' => $examples[$key] ?? null,
                'in_body' => true,
            ])->all(),
            'version_number' => null,
        ]);
    }
}
