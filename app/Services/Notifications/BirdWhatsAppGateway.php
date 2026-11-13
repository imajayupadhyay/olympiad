<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin HTTP adapter for the Bird platform WhatsApp API (workflow.md §13.5).
 * The only class that talks to Bird. Never call it from controllers or Vue:
 * go through BirdTemplateCatalogue / WhatsAppNotificationChannel.
 *
 *   GET  /v1/whatsapp/templates                               catalogue
 *   GET  /v1/whatsapp/templates/{id}/versions/{versionId}     body + variables
 *   POST /v1/whatsapp/messages                                one recipient per call
 *   GET  /v1/whatsapp/messages/{id}                           status / delivered / read / last_error
 */
class BirdWhatsAppGateway
{
    public function isConfigured(): bool
    {
        return filled(config('services.bird.api_key')) && filled(config('services.bird.base_url'));
    }

    /** @return array<int, array<string, mixed>> */
    public function templates(): array
    {
        $items = [];
        $cursor = null;

        // Bird pages with cursors; 10 pages × default size is far beyond any real catalogue.
        for ($page = 0; $page < 10; $page++) {
            $json = $this->request(fn (PendingRequest $c) => $c->get('/v1/whatsapp/templates', array_filter(['cursor' => $cursor])));
            $items = array_merge($items, is_array($json['data'] ?? null) ? $json['data'] : []);
            $cursor = $json['next_cursor'] ?? null;

            if (blank($cursor)) {
                break;
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public function templateVersion(string $templateId, string $versionId): array
    {
        return $this->request(fn (PendingRequest $c) => $c->get(
            '/v1/whatsapp/templates/'.rawurlencode($templateId).'/versions/'.rawurlencode($versionId)
        ));
    }

    /**
     * @param  array<string, string>  $variables  template variable key => value
     * @return array<string, mixed> Bird message (id, status, …)
     */
    public function send(string $to, string $slug, string $language, array $variables = []): array
    {
        $parameters = [];
        foreach ($variables as $name => $value) {
            $parameters[] = ['type' => 'text', 'name' => (string) $name, 'text' => (string) $value];
        }

        $payload = [
            'to' => $to,
            'template' => [
                'slug' => $slug,
                'language' => $language,
                'components' => $parameters === [] ? [] : [['type' => 'body', 'parameters' => $parameters]],
            ],
        ];

        if (filled(config('services.bird.whatsapp_from'))) {
            $payload['from'] = config('services.bird.whatsapp_from');
        }

        return $this->request(fn (PendingRequest $c) => $c->post('/v1/whatsapp/messages', $payload));
    }

    /** @return array<string, mixed> */
    public function message(string $messageId): array
    {
        return $this->request(fn (PendingRequest $c) => $c->get('/v1/whatsapp/messages/'.rawurlencode($messageId)));
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new BirdException('Bird is not configured (BIRD_API_KEY / BIRD_BASE_URL).');
        }

        return Http::baseUrl(rtrim((string) config('services.bird.base_url'), '/'))
            ->withToken((string) config('services.bird.api_key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(20);
    }

    /** Runs one call; transport errors (DNS, timeout) become BirdException with status 0. */
    private function request(callable $call): array
    {
        try {
            $response = $call($this->client());
        } catch (ConnectionException $e) {
            throw new BirdException('Could not reach Bird: '.Str::limit($e->getMessage(), 200));
        }

        return $this->json($response);
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        if ($response->status() === 429) {
            $header = trim((string) $response->header('Retry-After'));
            $retryAfter = is_numeric($header) ? max(1, (int) ceil((float) $header)) : 60;

            throw new BirdException('Bird rate limit reached (HTTP 429).', 429, $retryAfter);
        }

        if (! $response->successful()) {
            throw new BirdException($this->errorMessage($response), $response->status(), null, is_string($response->json('code')) ? $response->json('code') : null);
        }

        return (array) $response->json();
    }

    /** A readable message without echoing request data (numbers, names) back into logs. */
    private function errorMessage(Response $response): string
    {
        $json = $response->json();
        $detail = is_array($json)
            ? ($json['message'] ?? $json['detail'] ?? $json['error']['message'] ?? $json['code'] ?? null)
            : null;

        if (is_array($json) && ! empty($json['details']) && is_array($json['details'])) {
            $first = collect($json['details'])->flatten()->filter(fn ($v) => is_string($v))->first();
            $detail = trim(($detail ? $detail.': ' : '').($first ?? ''));
        }

        return 'Bird HTTP '.$response->status().($detail ? ' — '.Str::limit((string) $detail, 300) : '');
    }
}
