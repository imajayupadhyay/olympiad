<?php

namespace App\Services;

use App\Exceptions\OtpDeliveryException;
use Illuminate\Support\Facades\Http;

class AuthenticationOtpDeliveryService
{
    public function isConfigured(string $channel): bool
    {
        return match ($channel) {
            'email' => filled(config('services.brevo.api_key'))
                && filled(config('services.brevo.sender_email')),
            'whatsapp' => filled(config('services.aisensy.api_key'))
                && filled(config('services.aisensy.campaign_name')),
            default => false,
        };
    }

    public function send(string $channel, string $destination, string $recipientName, string $code): void
    {
        $recipientName = $this->safeDisplayName($recipientName);

        match ($channel) {
            'email' => $this->sendEmail($destination, $recipientName, $code),
            'whatsapp' => $this->sendWhatsApp($destination, $recipientName, $code),
            default => throw new OtpDeliveryException('Unsupported OTP delivery channel.'),
        };
    }

    private function sendEmail(string $email, string $name, string $code): void
    {
        if (! $this->isConfigured('email')) {
            throw new OtpDeliveryException('Email OTP delivery is not configured.');
        }

        $rawAppName = (string) config('app.name', 'National Olympiad Hunt');
        $appName = e($rawAppName);
        $safeName = e($name ?: 'Student');
        $safeCode = e($code);
        $portalUrl = e(config('app.url'));

        $html = '<!doctype html><html><body style="margin:0;background:#FBF6EC;padding:28px 14px;font-family:Arial,sans-serif;color:#0A1024;">'
            .'<div style="max-width:620px;margin:auto;background:#fff;border:1px solid #E7D9BE;border-radius:22px;overflow:hidden;">'
            .'<div style="background:#0A1024;padding:24px 28px;border-bottom:4px solid #EE6A2C;color:#FBF6EC;font-weight:700;">'.$appName.'</div>'
            .'<div style="padding:30px 28px;font-size:15px;line-height:1.7;">'
            .'<p>Hi '.$safeName.',</p><p>Here is your secure login code:</p>'
            .'<p style="text-align:center;margin:24px 0;"><span style="display:inline-block;background:#F3E9D6;border:1px solid #E7D9BE;border-radius:14px;padding:13px 22px;font-size:32px;font-weight:700;letter-spacing:9px;">'.$safeCode.'</span></p>'
            .'<p>This code expires in <strong>'.LoginOtpService::EXPIRY_MINUTES.' minutes</strong> and can only be used once.</p>'
            .'<p>Never share this code. Our team will never ask you for it.</p>'
            .'<p>If you did not request this login, you can safely ignore this email.</p>'
            .'<p><a href="'.$portalUrl.'" style="color:#C9501A;font-weight:700;">Open Student Portal</a></p>'
            .'</div></div></body></html>';

        $text = "Hi {$name},\n\nYour {$rawAppName} login code is: {$code}\n\n"
            .'It expires in '.LoginOtpService::EXPIRY_MINUTES." minutes and can only be used once.\n"
            ."Never share this code. Our team will never ask you for it.\n";

        $response = Http::withHeaders([
            'api-key' => config('services.brevo.api_key'),
            'accept' => 'application/json',
        ])->asJson()->connectTimeout(5)->timeout(15)->post(config('services.brevo.endpoint'), [
            'sender' => [
                'email' => config('services.brevo.sender_email'),
                'name' => config('services.brevo.sender_name'),
            ],
            'to' => [['email' => $email, 'name' => $name ?: $email]],
            'subject' => 'Your secure login code',
            'htmlContent' => $html,
            'textContent' => $text,
        ]);

        if (! $response->successful()) {
            throw new OtpDeliveryException('Brevo rejected the OTP delivery request.');
        }
    }

    private function sendWhatsApp(string $phone, string $name, string $code): void
    {
        if (! $this->isConfigured('whatsapp')) {
            throw new OtpDeliveryException('WhatsApp OTP delivery is not configured.');
        }

        // AiSensy's approved Authentication template has a fixed body. The
        // student's name is supplied as userName/contact context; the code must
        // appear both in templateParams and the copy-code button parameter.
        $response = Http::asJson()->connectTimeout(5)->timeout(15)
            ->post(config('services.aisensy.endpoint'), [
                'apiKey' => config('services.aisensy.api_key'),
                'campaignName' => config('services.aisensy.campaign_name'),
                'destination' => ltrim($phone, '+'),
                'userName' => $name ?: 'NEO Student',
                'source' => config('services.aisensy.source'),
                'templateParams' => [$code],
                'buttons' => [[
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [[
                        'type' => 'text',
                        'text' => $code,
                    ]],
                ]],
            ]);

        if (! $response->successful()) {
            throw new OtpDeliveryException('AiSensy rejected the OTP delivery request.');
        }
    }

    private function safeDisplayName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return mb_substr($name ?: 'Student', 0, 80);
    }
}
