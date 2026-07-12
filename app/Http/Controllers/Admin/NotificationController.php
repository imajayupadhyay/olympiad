<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\NotificationLog;
use App\Models\StudentNotification;
use App\Models\User;
use App\Services\ManagedEmailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        $logs = NotificationLog::with(['sentBy:id,name', 'exam:id,name', 'classLevel:id,label'])
            ->withCount([
                'emailLogs as email_queued_count' => fn (Builder $query) => $query->where('status', 'queued'),
                'emailLogs as email_sent_count' => fn (Builder $query) => $query->where('status', 'sent'),
                'emailLogs as email_failed_count' => fn (Builder $query) => $query->where('status', 'failed'),
                'emailLogs as email_skipped_count' => fn (Builder $query) => $query->where('status', 'skipped'),
            ])
            ->latest('sent_at')
            ->paginate(20);

        return Inertia::render('Admin/Notifications/Index', [
            'logs'        => $logs,
            'exams'       => Exam::whereIn('status', ['published', 'archived'])->get(['id', 'name']),
            'classLevels' => ClassLevel::active(),
            'states'      => User::indianStates(),
            'stats'       => [
                'total_sent'  => NotificationLog::count(),
                'this_month'  => NotificationLog::whereMonth('sent_at', now()->month)->whereYear('sent_at', now()->year)->count(),
                'total_reach' => NotificationLog::sum('recipient_count'),
            ],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $this->validatedBroadcastData($request, false);
        $query = $this->recipientQuery($data);

        $count = (clone $query)->count();
        $sample = (clone $query)
            ->with('classLevel:id,label')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email', 'class_level_id', 'state'])
            ->map(fn (User $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'class' => $student->classLevel?->label,
                'state' => $student->state,
            ]);

        return response()->json([
            'count' => $count,
            'sample' => $sample,
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:100',
            'ids' => 'nullable|array|max:50',
            'ids.*' => 'integer|exists:users,id',
        ]);

        $query = User::query()
            ->where('role', 'student')
            ->whereNotNull('email')
            ->with('classLevel:id,label')
            ->withExists([
                'payments as has_paid_payment' => fn (Builder $payments) => $payments->where('status', 'paid'),
                'enrollments as has_active_enrollment' => fn (Builder $enrollments) => $enrollments->where('status', 'enrolled'),
            ]);

        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        } elseif (filled($data['search'] ?? null)) {
            $term = trim($data['search']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('school', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%");
            });
        } else {
            $query->latest('id');
        }

        $students = $query
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'school', 'city', 'state', 'class_level_id', 'is_active'])
            ->map(fn (User $student) => $this->studentOption($student));

        return response()->json(['students' => $students]);
    }

    public function send(Request $request, ManagedEmailService $emails)
    {
        $data = $this->validatedBroadcastData($request);

        $query = $this->recipientQuery($data);
        $count = (clone $query)->count();

        if ($count === 0) {
            return back()->with('error', 'No students matched the selected filters.');
        }

        $log = NotificationLog::create([
            'title'           => $data['title'],
            'message'         => $data['message'],
            'channel'         => $data['channel'],
            'audience'        => $data['audience'],
            'exam_id'         => $data['exam_id'] ?? null,
            'class_level_id'  => $data['class_level_id'] ?? null,
            'audience_filters' => $this->audienceFilters($data),
            'recipient_count' => $count,
            'status'          => 'sent',
            'sent_by'         => auth()->id(),
            'sent_at'         => now(),
        ]);

        (clone $query)
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->chunkById(200, function ($recipients) use ($data, $emails, $log) {
                if (in_array($data['channel'], ['in_app', 'both'], true)) {
                    StudentNotification::insert($recipients->map(fn (User $student) => [
                        'user_id'             => $student->id,
                        'notification_log_id' => $log->id,
                        'title'               => $data['title'],
                        'message'             => $data['message'],
                        'is_read'             => false,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ])->toArray());
                }

                if (in_array($data['channel'], ['email', 'both'], true)) {
                    foreach ($recipients as $student) {
                        $emails->queue(
                            'notification_blast',
                            $student,
                            $emails->notificationVariables($student, $data['title'], $data['message']),
                            ['related_type' => NotificationLog::class, 'related_id' => $log->id, 'notification_log_id' => $log->id]
                        );
                    }
                }
            });

        return back()->with('success', "Broadcast queued for {$count} student(s).");
    }

    protected function validatedBroadcastData(Request $request, bool $requireContent = true): array
    {
        $rules = [
            'title'          => 'required|string|max:150',
            'message'        => 'required|string|max:5000',
            'channel'        => 'required|in:email,in_app,both',
            'recipient_mode' => 'required|in:filters,selected',
            'selected_user_ids' => 'nullable|array|max:500',
            'selected_user_ids.*' => 'integer|exists:users,id',
            'audience'       => 'required|in:all,exam,class',
            'exam_id'        => 'nullable|exists:exams,id',
            'class_level_id' => 'nullable|exists:class_levels,id',
            'student_status' => 'required|in:active,inactive,all',
            'payment_status' => 'required|in:all,paid,unpaid,pending,failed,refunded',
            'enrollment_status' => 'required|in:all,enrolled,not_enrolled,cancelled',
            'state'          => 'nullable|string|max:80',
            'search'         => 'nullable|string|max:100',
        ];

        if (! $requireContent) {
            $rules['title'] = 'nullable|string|max:150';
            $rules['message'] = 'nullable|string|max:5000';
        }

        $data = $request->validate($rules);

        validator([], [])->after(function ($validator) use ($data, $requireContent) {
            if (($data['recipient_mode'] ?? 'filters') === 'selected' && $requireContent && empty($data['selected_user_ids'])) {
                $validator->errors()->add('selected_user_ids', 'Select at least one student.');
            }

            if (($data['recipient_mode'] ?? 'filters') !== 'filters') {
                return;
            }

            if (($data['audience'] ?? 'all') === 'exam' && empty($data['exam_id'])) {
                $validator->errors()->add('exam_id', 'Select an exam.');
            }

            if (($data['audience'] ?? 'all') === 'class' && empty($data['class_level_id'])) {
                $validator->errors()->add('class_level_id', 'Select a class.');
            }
        })->validate();

        return $data;
    }

    protected function recipientQuery(array $data): Builder
    {
        $query = User::query()
            ->where('role', 'student')
            ->whereNotNull('email');

        if (($data['recipient_mode'] ?? 'filters') === 'selected') {
            return $query->whereIn('id', collect($data['selected_user_ids'] ?? [])->filter()->unique()->values());
        }

        if (($data['student_status'] ?? 'active') === 'active') {
            $query->where('is_active', true);
        } elseif (($data['student_status'] ?? 'active') === 'inactive') {
            $query->where('is_active', false);
        }

        if (($data['audience'] ?? 'all') === 'exam' && ! empty($data['exam_id'])) {
            $examId = (int) $data['exam_id'];
            $query->where(function (Builder $q) use ($examId) {
                $q->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                    ->where('exam_id', $examId)
                    ->where('status', 'enrolled'))
                    ->orWhereHas('examAttempts', fn (Builder $attempts) => $attempts->where('exam_id', $examId));
            });
        } elseif (($data['audience'] ?? 'all') === 'class' && ! empty($data['class_level_id'])) {
            $query->where('class_level_id', $data['class_level_id']);
        }

        match ($data['payment_status'] ?? 'all') {
            'paid' => $query->whereHas('payments', fn (Builder $payments) => $payments->where('status', 'paid')),
            'unpaid' => $query->whereDoesntHave('payments', fn (Builder $payments) => $payments->where('status', 'paid')),
            'pending' => $query->whereHas('payments', fn (Builder $payments) => $payments->where('status', 'created')),
            'failed' => $query->whereHas('payments', fn (Builder $payments) => $payments->where('status', 'failed')),
            'refunded' => $query->whereHas('payments', fn (Builder $payments) => $payments->where('status', 'refunded')),
            default => null,
        };

        match ($data['enrollment_status'] ?? 'all') {
            'enrolled' => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->where('status', 'enrolled')),
            'not_enrolled' => $query->whereDoesntHave('enrollments', fn (Builder $enrollments) => $enrollments->where('status', 'enrolled')),
            'cancelled' => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->where('status', 'cancelled')),
            default => null,
        };

        if (filled($data['state'] ?? null)) {
            $query->where('state', $data['state']);
        }

        if (filled($data['search'] ?? null)) {
            $term = trim($data['search']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('school', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%");
            });
        }

        return $query;
    }

    protected function audienceFilters(array $data): array
    {
        return collect([
            'recipient_mode' => $data['recipient_mode'] ?? 'filters',
            'selected_user_ids' => ($data['recipient_mode'] ?? 'filters') === 'selected'
                ? collect($data['selected_user_ids'] ?? [])->filter()->unique()->values()->all()
                : null,
            'student_status' => $data['student_status'] ?? 'active',
            'payment_status' => $data['payment_status'] ?? 'all',
            'enrollment_status' => $data['enrollment_status'] ?? 'all',
            'state' => $data['state'] ?? null,
            'search' => $data['search'] ?? null,
        ])->filter(fn ($value) => filled($value) && ! in_array($value, ['all', 'active', 'filters'], true))->all();
    }

    protected function studentOption(User $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $student->phone,
            'school' => $student->school,
            'city' => $student->city,
            'state' => $student->state,
            'class' => $student->classLevel?->label,
            'is_active' => (bool) $student->is_active,
            'has_paid_payment' => (bool) $student->has_paid_payment,
            'has_active_enrollment' => (bool) $student->has_active_enrollment,
        ];
    }

    public function destroy(NotificationLog $log)
    {
        StudentNotification::where('notification_log_id', $log->id)->delete();
        $log->delete();

        return back()->with('success', 'Notification log deleted.');
    }
}
