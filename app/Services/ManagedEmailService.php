<?php

namespace App\Services;

use App\Jobs\SendManagedEmail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\Result;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ManagedEmailService
{
    public function queue(string $templateKey, User $recipient, array $variables = [], array $meta = []): ?EmailLog
    {
        $template = EmailTemplate::where('key', $templateKey)->first();

        if (! $template) {
            return EmailLog::create([
                'template_key' => $templateKey,
                'recipient_user_id' => $recipient->id,
                'related_type' => $meta['related_type'] ?? null,
                'related_id' => $meta['related_id'] ?? null,
                'recipient_email' => $recipient->email,
                'recipient_name' => $recipient->name,
                'subject' => $templateKey,
                'status' => 'failed',
                'error_message' => "Email template [{$templateKey}] was not found.",
                'meta' => $this->safeMeta($meta),
                'queued_at' => now(),
            ]);
        }

        if (! $template->is_active) {
            return EmailLog::create([
                'email_template_id' => $template->id,
                'template_key' => $template->key,
                'recipient_user_id' => $recipient->id,
                'related_type' => $meta['related_type'] ?? null,
                'related_id' => $meta['related_id'] ?? null,
                'recipient_email' => $recipient->email,
                'recipient_name' => $recipient->name,
                'subject' => $this->render($template->subject, $this->variablesForUser($recipient, $variables)),
                'status' => 'skipped',
                'error_message' => 'Template is disabled by admin.',
                'meta' => $this->safeMeta($meta),
                'queued_at' => now(),
            ]);
        }

        $rendered = $this->renderTemplate($template, $this->variablesForUser($recipient, $variables));

        $log = EmailLog::create([
            'email_template_id' => $template->id,
            'template_key' => $template->key,
            'recipient_user_id' => $recipient->id,
            'related_type' => $meta['related_type'] ?? null,
            'related_id' => $meta['related_id'] ?? null,
            'recipient_email' => $recipient->email,
            'recipient_name' => $recipient->name,
            'subject' => $rendered['subject'],
            'status' => 'queued',
            'meta' => $this->safeMeta($meta),
            'queued_at' => now(),
        ]);

        SendManagedEmail::dispatch($log->id, $rendered);

        return $log;
    }

    public function renderTemplate(EmailTemplate $template, array $variables = []): array
    {
        $variables = $this->baseVariables($variables);

        $html = $this->render($template->html_body, $variables, true);
        $text = $template->text_body
            ? $this->render($template->text_body, $variables, false)
            : trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $html))));

        return [
            'subject' => $this->render($template->subject, $variables, false),
            'html_body' => $this->wrapHtml($html),
            'text_body' => $text,
        ];
    }

    public function sendRendered(EmailLog $log, array $rendered): void
    {
        $apiKey = config('services.brevo.api_key');

        if (! $apiKey) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'BREVO_API_KEY is not configured.',
            ]);

            return;
        }

        try {
            $payload = [
                'sender' => [
                    'email' => config('services.brevo.sender_email'),
                    'name' => config('services.brevo.sender_name'),
                ],
                'to' => [[
                    'email' => $log->recipient_email,
                    'name' => $log->recipient_name ?: $log->recipient_email,
                ]],
                'subject' => $rendered['subject'],
                'htmlContent' => $rendered['html_body'],
                'textContent' => $rendered['text_body'],
            ];

            if ($replyTo = config('services.brevo.reply_to_email')) {
                $payload['replyTo'] = [
                    'email' => $replyTo,
                    'name' => config('services.brevo.reply_to_name') ?: config('services.brevo.sender_name'),
                ];
            }

            $response = Http::withHeaders([
                'api-key' => $apiKey,
                'accept' => 'application/json',
            ])
                ->asJson()
                ->timeout(20)
                ->post(config('services.brevo.endpoint'), $payload);

            if ($response->successful()) {
                $log->update([
                    'status' => 'sent',
                    'brevo_message_id' => $response->json('messageId'),
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                return;
            }

            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($response->body(), 1000, ''),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000, ''),
            ]);
        }
    }

    public function studentRegistrationVariables(User $user, ?string $plainPassword = null): array
    {
        return $this->variablesForUser($user, [
            'login_password' => $plainPassword ?: 'Use the password created during registration',
        ]);
    }

    public function paymentVariables(Payment $payment, ?string $plainPassword = null): array
    {
        $payment->loadMissing(['user.classLevel', 'enrollments.exam']);
        $examNames = $payment->enrollments->pluck('exam.name')->filter()->values();

        return $this->variablesForUser($payment->user, [
            'login_password' => $plainPassword ?: 'Use your existing password',
            'olympiad_name' => $examNames->count() === 1 ? $examNames->first() : config('app.name'),
            'exam_names' => $examNames->join(', '),
            'amount_paid' => $this->money((float) $payment->amount, $payment->currency),
            'transaction_id' => $payment->razorpay_payment_id ?: $payment->razorpay_order_id ?: $payment->manual_reference ?: 'N/A',
            'payment_datetime' => optional($payment->paid_at)->timezone(config('app.timezone'))->format('d M Y, h:i A') ?: now()->format('d M Y, h:i A'),
            'payment_method' => $payment->method ?: $payment->gateway ?: 'Razorpay',
            'payment_gateway' => Str::headline($payment->gateway ?: 'Razorpay'),
        ]);
    }

    public function examReminderVariables(User $user, Exam $exam): array
    {
        $exam->loadMissing(['classLevel']);

        return $this->variablesForUser($user, [
            'exam_name' => $exam->name,
            'exam_code' => $exam->exam_code,
            'exam_start_datetime' => optional($exam->starts_at)->timezone(config('app.timezone'))->format('d M Y, h:i A'),
            'exam_end_datetime' => optional($exam->ends_at)->timezone(config('app.timezone'))->format('d M Y, h:i A'),
            'exam_duration' => $exam->duration_minutes,
            'student_class' => $exam->classLevel?->label ?: $user->classLevel?->label,
        ]);
    }

    public function resultVariables(Result $result): array
    {
        $result->loadMissing(['user.classLevel', 'exam']);

        return $this->variablesForUser($result->user, [
            'exam_name' => $result->exam?->name,
            'score' => $result->score_override ?? $result->total_score,
            'max_score' => $result->max_score,
            'percentage' => $result->percentage,
            'national_rank' => $result->rank_national,
            'grade' => $result->grade,
        ]);
    }

    public function certificateVariables(User $user, Exam $exam): array
    {
        return $this->variablesForUser($user, [
            'exam_name' => $exam->name,
        ]);
    }

    public function notificationVariables(User $user, string $title, string $message): array
    {
        return $this->variablesForUser($user, [
            'notification_title' => $title,
            'notification_message' => $message,
        ]);
    }

    public function supportTicketVariables(User $user, \App\Models\SupportTicket $ticket, string $replySnippet): array
    {
        return $this->variablesForUser($user, [
            'ticket_subject' => $ticket->subject,
            'reply_snippet' => $replySnippet,
            'ticket_url' => route('student.support.show', $ticket->id),
        ]);
    }

    protected function variablesForUser(User $user, array $variables = []): array
    {
        $user->loadMissing('classLevel');

        return $this->baseVariables(array_merge([
            'student_name' => $user->name,
            'student_email' => $user->email,
            'student_class' => $user->classLevel?->label,
            'school_name' => $user->school ?: 'N/A',
            'city' => $user->city,
            'state' => $user->state,
            'login_password' => 'Use your existing password',
        ], $variables));
    }

    protected function baseVariables(array $variables = []): array
    {
        return array_merge([
            'app_name' => config('app.name', 'National Olympiad Hunt'),
            'portal_url' => config('app.url'),
            'website_url' => config('app.url'),
            'support_email' => config('services.brevo.support_email') ?: config('mail.from.address'),
            'support_phone' => config('services.brevo.support_phone', '+91 72890 89009'),
        ], $variables);
    }

    protected function render(string $content, array $variables, bool $preserveLineBreaks = false): string
    {
        $rendered = preg_replace_callback('/{{\s*([a-zA-Z0-9_.-]+)\s*}}/', function ($matches) use ($variables, $preserveLineBreaks) {
            $value = Arr::get($variables, $matches[1], '');
            $value = is_scalar($value) ? (string) $value : '';
            $escaped = e($value);

            return $preserveLineBreaks ? nl2br($escaped, false) : $escaped;
        }, $content);

        return $rendered ?? $content;
    }

    protected function wrapHtml(string $body): string
    {
        $appName = e(config('app.name', 'National Olympiad Hunt'));
        $portalUrl = e(config('app.url'));
        $logoUrl = e($this->mailLogoUrl());
        $supportEmail = e(config('services.brevo.support_email') ?: config('mail.from.address'));
        $supportPhone = e(config('services.brevo.support_phone', '+91 72890 89009'));

        return '<!doctype html>'
            .'<html>'
            .'<head>'
            .'<meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta name="color-scheme" content="light">'
            .'<style>'
            .'@media only screen and (max-width:640px){.email-shell{padding:14px!important}.email-card{border-radius:18px!important}.email-content{padding:24px 20px!important}.email-header{padding:22px 20px!important}.email-footer{padding:20px!important}.email-logo{width:230px!important;max-width:100%!important}.email-cta{display:block!important;text-align:center!important}}'
            .'.email-content p{margin:0 0 14px!important}.email-content p:last-child{margin-bottom:0!important}.email-content a{color:#C9501A!important}.email-content strong{color:#0A1024!important}.email-content ul{margin:8px 0 14px 22px!important;padding:0!important}.email-content li{margin:4px 0!important}'
            .'</style>'
            .'</head>'
            .'<body style="margin:0;background:#FBF6EC;padding:0;font-family:Arial,Helvetica,sans-serif;color:#0A1024;-webkit-font-smoothing:antialiased;">'
            .'<div class="email-shell" style="width:100%;box-sizing:border-box;background:#FBF6EC;padding:32px 18px;">'
            .'<div class="email-card" style="max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #E7D9BE;border-radius:24px;overflow:hidden;box-shadow:0 24px 60px rgba(10,16,36,.12);">'
            .'<div class="email-header" bgcolor="#0A1024" style="background:#0A1024!important;padding:28px 32px;border-bottom:4px solid #EE6A2C;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">'
            .'<tr>'
            .'<td style="vertical-align:middle;">'
            .'<a href="'.$portalUrl.'" style="display:inline-block;text-decoration:none;">'
            .'<img class="email-logo" src="'.$logoUrl.'" width="300" alt="'.$appName.'" style="display:block;width:300px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;background:#0A1024;">'
            .'</a>'
            .'</td>'
            .'<td align="right" style="vertical-align:middle;color:#F2C84B;font-size:12px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;">Olympiad Portal</td>'
            .'</tr>'
            .'</table>'
            .'</div>'
            .'<div class="email-content" style="padding:34px 36px 28px;font-size:15px;line-height:1.72;color:rgba(10,16,36,.78);">'
            .$body
            .'</div>'
            .'<div style="padding:0 36px 30px;">'
            .'<a class="email-cta" href="'.$portalUrl.'" style="display:inline-block;background:#EE6A2C;color:#ffffff!important;text-decoration:none;border-radius:12px;padding:12px 18px;font-size:14px;font-weight:700;">Open Student Portal</a>'
            .'</div>'
            .'<div class="email-footer" style="background:#F3E9D6;border-top:1px solid #E7D9BE;padding:24px 32px;color:rgba(10,16,36,.62);font-size:12px;line-height:1.65;">'
            .'<p style="margin:0 0 6px;font-weight:700;color:#0A1024;">'.$appName.'</p>'
            .'<p style="margin:0;">Email: <a href="mailto:'.$supportEmail.'" style="color:#C9501A;text-decoration:none;">'.$supportEmail.'</a> &nbsp;|&nbsp; Phone: '.$supportPhone.'</p>'
            .'<p style="margin:8px 0 0;">This is an automated transactional email from the '.$appName.' platform.</p>'
            .'</div>'
            .'</div>'
            .'</div>'
            .'</body>'
            .'</html>';
    }

    protected function mailLogoUrl(): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! $host || in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            $baseUrl = 'https://neoexam.org';
        }

        return $baseUrl.'/NEO_email_header_logo.png';
    }

    protected function safeMeta(array $meta): array
    {
        unset($meta['login_password'], $meta['password']);

        return $meta;
    }

    protected function money(float $amount, string $currency = 'INR'): string
    {
        $symbol = $currency === 'INR' ? '₹' : $currency.' ';

        return $symbol.number_format($amount, 2);
    }
}
