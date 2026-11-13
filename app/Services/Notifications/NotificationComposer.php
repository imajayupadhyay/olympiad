<?php

namespace App\Services\Notifications;

use App\Models\Exam;
use Illuminate\Validation\ValidationException;

/**
 * Validates and normalises the per-channel message content for send, preview and
 * test (so all three accept exactly the same thing).
 *
 *   email     subject + body_html (TipTap HTML; values escaped on render)
 *   in_app    title + message (plain text) + optional /student/… link
 *   whatsapp  template_id + variable mapping → an immutable template snapshot built
 *             from Bird's live catalogue (the browser never supplies template text)
 */
class NotificationComposer
{
    public const LINK_PATTERN = '#^/student(/[A-Za-z0-9\-_/]*)?$#';

    public function __construct(
        private readonly NotificationPlaceholderService $placeholders,
        private readonly BirdTemplateCatalogue $templates,
        private readonly BirdWhatsAppGateway $bird,
    ) {}

    public function rules(string $channel): array
    {
        return match ($channel) {
            'email' => [
                'email' => ['required', 'array'],
                'email.subject' => ['required', 'string', 'max:200'],
                'email.body_html' => ['required', 'string', 'max:100000'],
            ],
            'in_app' => [
                'in_app' => ['required', 'array'],
                'in_app.title' => ['required', 'string', 'max:150'],
                'in_app.message' => ['required', 'string', 'max:2000'],
                'in_app.link' => ['nullable', 'string', 'max:255', 'regex:'.self::LINK_PATTERN],
            ],
            'whatsapp' => [
                'whatsapp' => ['required', 'array'],
                'whatsapp.template_id' => ['required', 'string', 'max:100'],
                'whatsapp.variables' => ['nullable', 'array', 'max:30'],
                'whatsapp.variables.*.source' => ['required', 'in:placeholder,text'],
                'whatsapp.variables.*.value' => ['nullable', 'string', 'max:500'],
                'whatsapp.variables.*.fallback' => ['nullable', 'string', 'max:200'],
                'whatsapp.variables.*.label' => ['nullable', 'string', 'max:60'],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $input  the validated request data
     * @return array<string, mixed> content stored on the broadcast
     *
     * @throws ValidationException
     */
    public function content(string $channel, array $input, ?Exam $exam, bool $strict = true): array
    {
        $content = match ($channel) {
            'email' => $this->email((array) $input['email'], $strict),
            'in_app' => [
                'title' => trim((string) $input['in_app']['title']),
                'message' => trim((string) $input['in_app']['message']),
                'link' => filled($input['in_app']['link'] ?? null) ? $input['in_app']['link'] : null,
            ],
            'whatsapp' => ['template' => $this->whatsappSnapshot((array) $input['whatsapp'], $strict)],
        };

        $problems = $this->placeholders->problems($this->usedKeys($channel, $content), $exam);
        if ($problems && $strict) {
            throw ValidationException::withMessages(['content' => $problems]);
        }

        return $content + ['problems' => $problems];
    }

    /** Placeholder keys a content block uses. */
    public function usedKeys(string $channel, array $content): array
    {
        return match ($channel) {
            'email' => $this->placeholders->usedKeys($content['subject'] ?? '', $content['body_html'] ?? ''),
            'in_app' => $this->placeholders->usedKeys($content['title'] ?? '', $content['message'] ?? ''),
            'whatsapp' => collect($content['template']['variables'] ?? [])->flatMap(fn ($v) => ($v['source'] ?? '') === 'placeholder'
                ? [$v['value']]
                : $this->placeholders->usedKeys($v['value'] ?? '', $v['fallback'] ?? ''))
                ->filter()->unique()->values()->all(),
            default => [],
        };
    }

    private function email(array $email, bool $strict): array
    {
        $body = (string) $email['body_html'];

        if ($strict && trim(html_entity_decode(strip_tags($body))) === '' && ! str_contains($body, '<img')) {
            throw ValidationException::withMessages(['email.body_html' => 'Write the email content.']);
        }

        return ['subject' => trim((string) $email['subject']), 'body_html' => $body];
    }

    /** Builds the template snapshot from Bird's live catalogue. */
    private function whatsappSnapshot(array $input, bool $strict): array
    {
        if (! $this->bird->isConfigured()) {
            throw ValidationException::withMessages(['whatsapp.template_id' => 'WhatsApp is not configured on this server (BIRD_API_KEY).']);
        }

        try {
            $template = $this->templates->find((string) $input['template_id']);
        } catch (BirdException $e) {
            throw ValidationException::withMessages(['whatsapp.template_id' => 'Could not load the template from Bird: '.$e->getMessage()]);
        }

        if (! $template) {
            throw ValidationException::withMessages(['whatsapp.template_id' => 'This template no longer exists in Bird. Refresh the template list.']);
        }

        if (! $template['usable']) {
            throw ValidationException::withMessages(['whatsapp.template_id' => $template['unusable_reason']]);
        }

        $mapping = (array) ($input['variables'] ?? []);
        $catalogue = array_keys(NotificationPlaceholderService::CATALOGUE);
        $errors = [];

        $variables = collect($template['variables'])->map(function (array $variable) use ($mapping, $catalogue, $strict, &$errors) {
            $key = $variable['key'];
            $map = (array) ($mapping[$key] ?? []);
            $source = ($map['source'] ?? 'text') === 'placeholder' ? 'placeholder' : 'text';
            $value = trim((string) ($map['value'] ?? ''));

            if ($strict && $source === 'placeholder' && ! in_array($value, $catalogue, true)) {
                $errors["whatsapp.variables.{$key}.value"] = 'Choose a student field for {{'.$key.'}}.';
            }
            if ($strict && $source === 'text' && $value === '' && $variable['required']) {
                $errors["whatsapp.variables.{$key}.value"] = 'Enter a value for {{'.$key.'}}.';
            }

            return [
                'key' => $key,
                'label' => trim((string) ($map['label'] ?? '')) ?: null,
                'required' => $variable['required'],
                'source' => $source,
                'value' => $value,
                'fallback' => trim((string) ($map['fallback'] ?? '')) ?: null,
                'example' => $variable['example'],
            ];
        })->values()->all();

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'id' => $template['id'],
            'slug' => $template['slug'],
            'name' => $template['name'],
            'language' => $template['language'],
            'category' => $template['category'],
            'version_id' => $template['live_version_id'],
            'header' => $template['header'],
            'body' => $template['body'],
            'footer' => $template['footer'],
            'buttons' => $template['buttons'],
            'variables' => $variables,
        ];
    }
}
