<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\User;
use App\Services\ManagedEmailService;
use App\Services\Notifications\BirdException;
use App\Services\Notifications\BirdWhatsAppGateway;
use App\Services\Notifications\NotificationAudienceService;
use App\Services\Notifications\NotificationComposer;
use App\Services\Notifications\NotificationPlaceholderService;
use App\Services\Notifications\WhatsAppNotificationChannel;
use App\Services\PhoneNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * JSON endpoints behind the Notifications composer: live audience, student search,
 * server-rendered previews and test sends. Nothing here writes a broadcast.
 */
class NotificationComposerController extends Controller
{
    public function __construct(
        private readonly NotificationAudienceService $audience,
        private readonly NotificationComposer $composer,
        private readonly NotificationPlaceholderService $placeholders,
    ) {}

    /** Live count + one page of matched students. */
    public function audience(Request $request): JsonResponse
    {
        $data = $request->validate($this->audience->rules() + [
            'channel' => ['required', Rule::in(['email', 'in_app', 'whatsapp'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json([
            'summary' => $this->audience->summary($data['audience'], $data['channel']),
            'students' => $this->audience->page($data['audience'], $data['channel'], (int) ($data['page'] ?? 1)),
            'chips' => $this->audience->describe($data['audience']),
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'ids' => ['nullable', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        return response()->json(['students' => $this->audience->search($data['search'] ?? null, $data['ids'] ?? [])]);
    }

    /** What one real recipient (or sample data) will receive, plus warnings. */
    public function preview(Request $request, ManagedEmailService $emails, WhatsAppNotificationChannel $whatsapp): JsonResponse
    {
        $channel = $request->validate(['channel' => ['required', Rule::in(['email', 'in_app', 'whatsapp'])]])['channel'];
        $data = $request->validate($this->audience->rules() + $this->composer->rules($channel) + [
            'exam_context_id' => ['nullable', 'integer', 'exists:exams,id'],
            'sample_user_id' => ['nullable', 'integer'],
        ]);

        $exam = ! empty($data['exam_context_id']) ? Exam::find($data['exam_context_id']) : null;
        $content = $this->composer->content($channel, $data, $exam, false);
        $recipients = $this->audience->recipients($data['audience'], $channel);

        $sample = null;
        if (! empty($data['sample_user_id'])) {
            $sample = (clone $recipients)->whereKey($data['sample_user_id'])->with($this->placeholders->eagerLoads($exam))->first();
        }
        $sample ??= (clone $recipients)->orderBy('name')->with($this->placeholders->eagerLoads($exam))->first();

        $values = $sample ? $this->placeholders->valuesFor($sample, $exam) : $this->placeholders->sampleValues($exam);
        $keys = $this->composer->usedKeys($channel, $content);

        $response = [
            'sample' => $sample ? ['id' => $sample->id, 'name' => $sample->name] : null,
            'problems' => $content['problems'],
            'blank_counts' => collect($this->placeholders->blankCounts(clone $recipients, $keys))
                ->map(fn ($count, $key) => ['key' => $key, 'label' => NotificationPlaceholderService::CATALOGUE[$key][0], 'count' => $count])
                ->values(),
        ];

        $response['preview'] = match ($channel) {
            'email' => [
                'subject' => $this->placeholders->renderText($content['subject'], $values),
                'html' => $emails->wrapBody($this->placeholders->renderHtml($content['body_html'], $values)),
            ],
            'in_app' => [
                'title' => $this->placeholders->renderText($content['title'], $values),
                'message' => $this->placeholders->renderText($content['message'], $values),
                'link' => $content['link'],
            ],
            'whatsapp' => (function () use ($content, $values, $whatsapp) {
                [$variables, $missing] = $whatsapp->variables($content['template'], $values);

                return [
                    'header' => $content['template']['header'],
                    'body' => $whatsapp->renderBody($content['template'], $variables),
                    'footer' => $content['template']['footer'],
                    'buttons' => $content['template']['buttons'],
                    'variables' => $variables,
                    'missing' => $missing,
                ];
            })(),
        };

        return response()->json($response);
    }

    /** Sends one rendered copy to an address/number the admin types. */
    public function test(Request $request, ManagedEmailService $emails, BirdWhatsAppGateway $bird, WhatsAppNotificationChannel $whatsapp, PhoneNumberService $phones): JsonResponse
    {
        $channel = $request->validate(['channel' => ['required', Rule::in(['email', 'whatsapp'])]])['channel'];
        $data = $request->validate($this->composer->rules($channel) + [
            'to' => ['required', 'string', 'max:190', $channel === 'email' ? 'email' : 'min:6'],
            'exam_context_id' => ['nullable', 'integer', 'exists:exams,id'],
            'sample_user_id' => ['nullable', 'integer'],
        ]);

        $exam = ! empty($data['exam_context_id']) ? Exam::find($data['exam_context_id']) : null;
        $content = $this->composer->content($channel, $data, $exam);
        $sample = ! empty($data['sample_user_id'])
            ? User::where('role', 'student')->with($this->placeholders->eagerLoads($exam))->find($data['sample_user_id'])
            : null;
        $values = $sample ? $this->placeholders->valuesFor($sample, $exam) : $this->placeholders->sampleValues($exam);

        if ($channel === 'email') {
            $recipient = new User(['name' => 'Test recipient', 'email' => $data['to']]);
            [$log, $rendered] = $emails->prepareBroadcast(
                $recipient,
                '[TEST] '.$this->placeholders->renderText($content['subject'], $values),
                $this->placeholders->renderHtml($content['body_html'], $values),
                ['test' => true, 'sent_by' => $request->user()->id],
            );
            $emails->sendRendered($log, $rendered);
            $log->refresh();

            return $log->status === 'sent'
                ? response()->json(['ok' => true, 'message' => 'Test email sent to '.$data['to'].'.'])
                : response()->json(['ok' => false, 'message' => 'Brevo refused the email: '.$log->error_message], 422);
        }

        try {
            $to = $phones->normalize($data['to']);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['to' => 'Enter a valid mobile number with country code, e.g. +91 98765 43210.']);
        }

        [$variables, $missing] = $whatsapp->variables($content['template'], $values);
        if ($missing) {
            throw ValidationException::withMessages(['to' => 'The sample student has no value for '.implode(', ', $missing).'. Add a fallback first.']);
        }

        try {
            $message = $bird->send($to, $content['template']['slug'], $content['template']['language'], $variables);
        } catch (BirdException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Bird accepted the test message to '.$phones->mask($to).' (status: '.($message['status'] ?? 'accepted').').',
            'message_id' => $message['id'] ?? null,
        ]);
    }
}
