<?php

namespace App\Services\Notifications\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin HTTP adapter for MSG91's WhatsApp API: the only class that talks to MSG91
 * (workflow.md §13.6). Endpoints are the ones in MSG91's published OpenAPI specs
 * (docs.msg91.com/whatsapp, read 2026-11-22); auth is the `authkey` header.
 *
 *   POST /api/v5/whatsapp/whatsapp-outbound-message/bulk/   send a template (one recipient per call)
 *   GET  /api/v5/whatsapp/get-template-client/{number}       templates of the integrated number
 *   GET  /api/v5/whatsapp/whatsapp-activation/               numbers on the account (credential check)
 *   GET  /api/v5/report/logs/wa                              delivery logs (by requestId, last 3 days)
 *
 * The send response only carries a `request_id` (no per-message id), so that is what
 * is stored as provider_message_id and used to look the message up in the logs.
 * MSG91 doesn't document the response fields of the template list or the logs, so
 * those are read defensively (see Msg91WhatsAppProvider) and status polling stays
 * off until a real response has been checked (MSG91_WHATSAPP_STATUS_POLLING).
 * MSG91 can answer HTTP 200 with `hasError: true`; that is treated as an error.
 */
class Msg91WhatsAppGateway
{
    public const DEFAULT_BASE_URL = 'https://control.msg91.com';

    public function isConfigured(): bool
    {
        return filled(config('services.msg91.auth_key')) && filled($this->integratedNumber());
    }

    public function supportsStatusPolling(): bool
    {
        return (bool) config('services.msg91.status_polling', false);
    }

    /** The integrated (sender) number as MSG91 expects it: country code + number, digits only. */
    public function integratedNumber(): string
    {
        return self::digits((string) config('services.msg91.integrated_number'));
    }

    public static function digits(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }

    /**
     * Each template variable becomes the body component MSG91 lists for it in the
     * template's `variables`: `body_` + the variable's key. Named templates
     * ({{student_name}}) → body_student_name; positional ones ({{1}}) → body_1.
     *
     * @param  array<string, string>  $variables  template variable key => value
     * @return array<string, mixed> `id` = MSG91 request_id, `status` = accepted
     */
    public function send(string $to, string $templateName, string $language, array $variables = []): array
    {
        $components = [];
        foreach ($variables as $key => $value) {
            $components['body_'.$key] = ['type' => 'text', 'value' => (string) $value];
        }

        $recipient = ['to' => [self::digits($to)]];
        if ($components !== []) {
            $recipient['components'] = $components;
        }

        $json = $this->request(fn (PendingRequest $c) => $c->post('/api/v5/whatsapp/whatsapp-outbound-message/bulk/', [
            'integrated_number' => $this->integratedNumber(),
            'content_type' => 'template',
            'payload' => [
                'messaging_product' => 'whatsapp',
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $language, 'policy' => 'deterministic'],
                    'to_and_components' => [$recipient],
                ],
            ],
        ]));

        $requestId = $json['request_id'] ?? null;
        if (blank($requestId)) {
            throw new Msg91Exception('MSG91 accepted the request but returned no request_id.', 200);
        }

        return ['id' => (string) $requestId, 'status' => 'accepted'];
    }

    /**
     * Delivery log rows for one request. MSG91 only serves logs from the last 3 days,
     * in a range of at most 3 days; older requests return nothing.
     *
     * @return array<string, mixed> the raw logs response
     */
    public function message(string $requestId): array
    {
        $today = now('Asia/Kolkata');

        return $this->request(fn (PendingRequest $c) => $c->get('/api/v5/report/logs/wa', [
            'startDate' => $today->copy()->subDays(2)->format('Y-m-d'),
            'endDate' => $today->format('Y-m-d'),
            'requestId' => $requestId,
        ]));
    }

    /**
     * The raw template list for the integrated number (MSG91 returns at most 500).
     *
     * @return array<string, mixed>
     */
    public function templates(?string $status = null): array
    {
        return $this->request(fn (PendingRequest $c) => $c->get(
            '/api/v5/whatsapp/get-template-client/'.rawurlencode($this->integratedNumber()),
            array_filter(['template_status' => $status]),
        ));
    }

    /** @return array<string, mixed> WhatsApp numbers active on the account */
    public function numbers(): array
    {
        return $this->request(fn (PendingRequest $c) => $c->get('/api/v5/whatsapp/whatsapp-activation/'));
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new Msg91Exception('MSG91 is not configured (MSG91_AUTH_KEY / MSG91_WHATSAPP_NUMBER).');
        }

        return Http::baseUrl(rtrim((string) (config('services.msg91.base_url') ?: self::DEFAULT_BASE_URL), '/'))
            ->withHeaders(['authkey' => (string) config('services.msg91.auth_key')])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(20);
    }

    /** Runs one call; transport errors (DNS, timeout) become Msg91Exception with status 0. */
    private function request(callable $call): array
    {
        try {
            $response = $call($this->client());
        } catch (ConnectionException $e) {
            throw new Msg91Exception('Could not reach MSG91: '.Str::limit($e->getMessage(), 200));
        }

        return $this->json($response);
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        if ($response->status() === 429) {
            $header = trim((string) $response->header('Retry-After'));
            $retryAfter = is_numeric($header) ? max(1, (int) ceil((float) $header)) : 60;

            throw new Msg91Exception('MSG91 rate limit reached (HTTP 429).', 429, $retryAfter);
        }

        $json = $response->json();
        $failed = ! $response->successful()
            || (is_array($json) && (($json['hasError'] ?? false) === true || ($json['status'] ?? null) === 'fail'));

        if ($failed) {
            $code = is_array($json) && is_scalar($json['code'] ?? null) ? (string) $json['code'] : null;

            throw new Msg91Exception($this->errorMessage($response, $json), $response->status(), null, $code);
        }

        return is_array($json) ? $json : [];
    }

    /** A readable message without echoing request data (numbers, names) back into logs. */
    private function errorMessage(Response $response, mixed $json): string
    {
        $detail = null;
        if (is_array($json)) {
            $detail = $json['errors'] ?? $json['message'] ?? $json['error'] ?? $json['data'] ?? null;
            if (is_array($detail)) {
                $detail = collect($detail)->flatten()->filter(fn ($v) => is_string($v))->implode('; ');
            }
        }

        return 'MSG91 HTTP '.$response->status().(filled($detail) ? ' — '.Str::limit((string) $detail, 300) : '');
    }
}
