<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notifications\BirdWhatsAppGateway;
use App\Services\Notifications\DuplicateBroadcastException;
use App\Services\Notifications\NotificationAudienceService;
use App\Services\Notifications\NotificationBroadcastService;
use App\Services\Notifications\NotificationComposer;
use App\Services\Notifications\NotificationPlaceholderService;
use App\Services\Notifications\WhatsAppNotificationChannel;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Notifications (workflow.md §13.2). Thin: every rule lives in
 * App\Services\Notifications\*. JSON helpers for the composer are in
 * NotificationComposerController, Bird templates in WhatsAppTemplateController.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationBroadcastService $broadcasts,
        private readonly NotificationAudienceService $audience,
        private readonly NotificationComposer $composer,
    ) {}

    public function index(Request $request, NotificationPlaceholderService $placeholders, BirdWhatsAppGateway $bird): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['email', 'in_app', 'whatsapp', 'history'])],
            'channel' => ['nullable', Rule::in(array_keys(NotificationLog::CHANNELS))],
            'status' => ['nullable', Rule::in(array_keys(NotificationLog::STATUSES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = NotificationLog::with(['sentBy:id,name'])
            ->when($filters['channel'] ?? null, fn ($q, $c) => $q->where('channel', $c))
            ->when($filters['status'] ?? null, fn ($q, $s) => $s === 'completed' ? $q->whereIn('status', ['completed', 'sent']) : $q->where('status', $s))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (NotificationLog $log) => $this->logRow($log));

        $monthStart = now()->startOfMonth();

        return Inertia::render('Admin/Notifications/Index', [
            'tab' => $filters['tab'] ?? 'email',
            'historyFilters' => array_intersect_key($filters, array_flip(['channel', 'status', 'q'])),
            'logs' => $logs,
            'stats' => [
                'broadcasts' => NotificationLog::count(),
                'this_month' => NotificationLog::where('created_at', '>=', $monthStart)->count(),
                'reach' => (int) NotificationLog::sum('sent_count'),
                'in_progress' => NotificationLog::whereIn('status', ['queued', 'sending'])->count(),
                'failed_30d' => NotificationDelivery::where('status', 'failed')->where('updated_at', '>=', now()->subDays(30))->count(),
                'by_channel' => NotificationLog::selectRaw('channel, count(*) as c')->groupBy('channel')->pluck('c', 'channel'),
            ],
            'options' => [
                'classLevels' => ClassLevel::active()->map->only(['id', 'label'])->values(),
                'subjects' => Subject::active()->map->only(['id', 'name'])->values(),
                'exams' => Exam::with(['subject:id,name', 'classLevel:id,label'])
                    ->whereIn('status', ['published', 'archived', 'draft'])
                    ->orderByDesc('starts_at')
                    ->get(['id', 'name', 'status', 'subject_id', 'class_level_id', 'starts_at', 'ends_at'])
                    ->map(fn (Exam $e) => [
                        'id' => $e->id, 'name' => $e->name, 'status' => $e->status,
                        'subject_id' => $e->subject_id, 'subject' => $e->subject?->name,
                        'class' => $e->classLevel?->label, 'starts_at' => $e->starts_at, 'ends_at' => $e->ends_at,
                    ]),
                'states' => User::indianStates(),
                'placeholders' => $placeholders->catalogue(),
                'linkPresets' => $this->linkPresets(),
                'whatsapp' => [
                    'configured' => $bird->isConfigured(),
                    'sender' => $bird->isConfigured() && config('services.bird.whatsapp_from')
                        ? app(PhoneNumberService::class)->mask((string) config('services.bird.whatsapp_from'))
                        : null,
                ],
                'email' => [
                    'configured' => filled(config('services.brevo.api_key')),
                    'sender' => config('services.brevo.sender_email'),
                ],
                'queue' => config('queue.default'),
                'maxIds' => NotificationAudienceService::MAX_IDS,
            ],
        ]);
    }

    public function show(Request $request, NotificationLog $log): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_merge(array_keys(NotificationDelivery::STATUSES), ['retryable']))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $deliveries = $log->deliveries()
            ->with(['user:id,name,email,phone_e164,class_level_id', 'user.classLevel:id,label'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $s === 'retryable' ? $q->retryable() : $q->where('status', $s))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('recipient_name', 'like', $like)->orWhere('destination', 'like', $like));
            })
            ->orderByRaw("case status when 'failed' then 0 when 'sending' then 1 when 'pending' then 2 else 3 end")
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $readIds = $log->channel === 'in_app' || $log->channel === 'both'
            ? $log->studentNotifications()->whereIn('user_id', collect($deliveries->items())->pluck('user_id'))->get(['user_id', 'is_read', 'read_at'])->keyBy('user_id')
            : collect();

        $deliveries->through(function (NotificationDelivery $d) use ($readIds) {
            $read = $d->channel === 'in_app' ? $readIds->get($d->user_id) : null;

            return [
                'id' => $d->id,
                'channel' => $d->channel,
                'name' => $d->recipient_name ?? $d->user?->name,
                'user_id' => $d->user_id,
                'class' => $d->user?->classLevel?->label,
                'destination' => $d->destination,
                'status' => $d->channel === 'in_app' && $read?->is_read ? 'read' : $d->status,
                'error' => $d->error,
                'error_code' => $d->error_code,
                'permanent' => $d->permanent_failure,
                'attempts' => $d->attempts,
                'sent_at' => $d->sent_at,
                'delivered_at' => $d->delivered_at,
                'read_at' => $d->channel === 'in_app' ? $read?->read_at : $d->read_at,
                'failed_at' => $d->failed_at,
                'variables' => $d->channel === 'whatsapp' ? data_get($d->payload, 'variables') : null,
            ];
        });

        $this->broadcasts->refreshCounts($log);

        return Inertia::render('Admin/Notifications/Show', [
            'log' => $this->logRow($log->load('sentBy:id,name', 'exam:id,name'), true),
            'deliveries' => $deliveries,
            'filters' => $filters,
            'retryable' => $log->deliveries()->retryable()->count(),
            'canRetry' => in_array($log->channel, ['email', 'whatsapp'], true),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $channel = $request->validate(['channel' => ['required', Rule::in(['email', 'in_app', 'whatsapp'])]])['channel'];

        $data = $request->validate(array_merge(
            $this->audience->rules(),
            $this->composer->rules($channel),
            [
                'exam_context_id' => ['nullable', 'integer', 'exists:exams,id'],
                'idempotency_key' => ['required', 'string', 'min:16', 'max:64'],
                'allow_duplicate' => ['boolean'],
            ],
        ));

        $exam = ! empty($data['exam_context_id']) ? Exam::find($data['exam_context_id']) : null;
        $content = $this->composer->content($channel, $data, $exam);
        unset($content['problems']);

        if ($this->audience->recipients($data['audience'], $channel)->count() === 0) {
            throw ValidationException::withMessages(['audience' => 'No student can receive this '.NotificationLog::CHANNELS[$channel].' message with the current selection.']);
        }

        try {
            [$log, $created] = $this->broadcasts->create([
                'channel' => $channel,
                'audience' => $data['audience'],
                'exam_context_id' => $exam?->id,
                'idempotency_key' => $data['idempotency_key'],
                'allow_duplicate' => (bool) ($data['allow_duplicate'] ?? false),
                'content' => $content,
            ], $request->user());
        } catch (DuplicateBroadcastException $e) {
            throw ValidationException::withMessages([
                'duplicate' => 'An identical message was sent to the same students '.$e->existing->created_at->diffForHumans().' (#'.$e->existing->id.'). Send again only if you really mean to.',
            ]);
        }

        $message = $created
            ? match ($log->channel) {
                'in_app' => "In-app notification delivered to {$log->sent_count} student(s).",
                default => NotificationLog::CHANNELS[$log->channel]." broadcast queued for {$log->recipient_count} student(s). Delivery status updates below.",
            }
        : 'This broadcast was already submitted. Showing its delivery status.';

        return redirect()->route('admin.notifications.show', $log)->with('success', $message);
    }

    public function retry(NotificationLog $log): RedirectResponse
    {
        $count = $this->broadcasts->retry($log);

        return back()->with($count ? 'success' : 'info', $count
            ? "Retrying {$count} failed deliver".($count === 1 ? 'y' : 'ies').'.'
            : 'Nothing to retry: no failed deliveries that a retry can fix.');
    }

    public function retryDelivery(NotificationLog $log, NotificationDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->notification_log_id === $log->id, 404);

        $count = $this->broadcasts->retry($log, $delivery);

        return back()->with($count ? 'success' : 'info', $count
            ? 'Retrying the message to '.$delivery->recipient_name.'.'
            : 'This delivery can’t be retried.');
    }

    public function refreshStatus(NotificationLog $log, WhatsAppNotificationChannel $whatsapp, BirdWhatsAppGateway $bird): RedirectResponse
    {
        if ($log->channel !== 'whatsapp' || ! $bird->isConfigured()) {
            return back()->with('info', 'Live status is only available for WhatsApp broadcasts.');
        }

        $synced = $this->broadcasts->syncWhatsAppStatuses($whatsapp, $log, 200);

        return back()->with('success', "Checked {$synced} message(s) with Bird.");
    }

    public function destroy(NotificationLog $log): RedirectResponse
    {
        if ($log->isInProgress()) {
            return back()->with('error', 'This broadcast is still sending. Wait for it to finish before deleting it.');
        }

        $log->delete(); // cascades to deliveries and in-app notifications

        return redirect()->route('admin.notifications', ['tab' => 'history'])->with('success', 'Broadcast deleted. In-app notifications from it were removed from student portals.');
    }

    private function logRow(NotificationLog $log, bool $full = false): array
    {
        $template = (array) $log->whatsapp_template;
        $chips = data_get($log->audience_filters, 'chips') ?? $this->legacyChips($log);

        $row = [
            'id' => $log->id,
            'channel' => $log->channel,
            'title' => $log->title,
            'excerpt' => mb_substr((string) $log->message, 0, 180),
            'status' => $log->status === 'sent' ? 'completed' : $log->status,
            'recipient_count' => $log->recipient_count,
            'pending_count' => $log->pending_count,
            'sent_count' => $log->sent_count,
            'delivered_count' => $log->delivered_count,
            'read_count' => $log->read_count,
            'failed_count' => $log->failed_count,
            'skipped_count' => $log->skipped_count,
            'audience_chips' => $chips,
            'sent_by' => $log->sentBy?->name,
            'created_at' => $log->created_at,
            'completed_at' => $log->completed_at,
            'template_name' => $template['name'] ?? null,
        ];

        if ($full) {
            $row += [
                'subject' => $log->subject,
                'message' => $log->message,
                'body_html' => $log->body_html,
                'link' => $log->link,
                'whatsapp_template' => $template ?: null,
                'exam' => $log->exam?->name,
                'meta' => $log->meta,
            ];
        }

        return $row;
    }

    /** Chips for broadcasts sent before 2026-11-13 (old filter shape). */
    private function legacyChips(NotificationLog $log): array
    {
        $f = (array) $log->audience_filters;

        return array_values(array_filter([
            ($f['recipient_mode'] ?? null) === 'selected' ? count($f['selected_user_ids'] ?? []).' hand-picked' : null,
            $log->audience === 'exam' ? 'Exam: '.($log->exam?->name ?? '#'.$log->exam_id) : null,
            $log->audience === 'class' ? 'Class: '.($log->classLevel?->label ?? '#'.$log->class_level_id) : null,
            isset($f['payment_status']) ? 'Payment: '.$f['payment_status'] : null,
            isset($f['enrollment_status']) ? 'Enrollment: '.$f['enrollment_status'] : null,
            $f['state'] ?? null,
        ])) ?: ['All students'];
    }

    private function linkPresets(): array
    {
        return [
            ['label' => 'No link', 'value' => ''],
            ['label' => 'Dashboard', 'value' => '/student/dashboard'],
            ['label' => 'Exams', 'value' => '/student/exams'],
            ['label' => 'Hall ticket', 'value' => '/student/hall-ticket'],
            ['label' => 'Results', 'value' => '/student/results'],
            ['label' => 'Certificates', 'value' => '/student/certificates'],
            ['label' => 'Payments', 'value' => '/student/payments'],
            ['label' => 'Profile', 'value' => '/student/profile'],
            ['label' => 'Refer & Earn', 'value' => '/student/refer'],
            ['label' => 'Support', 'value' => '/student/support'],
        ];
    }
}
