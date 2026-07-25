<?php

namespace App\Services;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class StudentReportService
{
    public function query(array $filters): Builder
    {
        $query = User::query()->where('role', 'student');
        $examIds = $this->scopedExamIds($filters);

        $this->applyStudentFilters($query, $filters);
        $this->applyCourseFilter($query, $filters, $examIds);
        $this->applyPaymentFilter($query, $filters, $examIds);

        return $query;
    }

    public function queryWithReportData(array $filters): Builder
    {
        $examIds = $this->scopedExamIds($filters);
        $query = $this->query($filters)->with([
            'classLevel:id,label',
            'enrollments' => function ($query) use ($examIds) {
                $query->where('status', 'enrolled');
                $this->constrainEnrollments($query, $examIds);
                $query->with(['exam:id,name,exam_code,subject_id', 'exam.subject:id,name'])
                    ->latest('enrolled_at');
            },
        ])->withCount([
            'enrollments as active_enrollments_count' => function ($query) use ($examIds) {
                $query->where('status', 'enrolled');
                $this->constrainEnrollments($query, $examIds);
            },
            'payments as report_paid_payments_count' => fn ($query) => $this->constrainPayments($query, 'paid', $examIds),
            'payments as report_pending_payments_count' => fn ($query) => $this->constrainPayments($query, 'created', $examIds),
            'payments as report_failed_payments_count' => fn ($query) => $this->constrainPayments($query, 'failed', $examIds),
            'payments as report_refunded_payments_count' => fn ($query) => $this->constrainPayments($query, 'refunded', $examIds),
        ])->withSum([
            'payments as report_paid_total' => fn ($query) => $this->constrainPayments($query, 'paid', $examIds),
        ], 'amount')->withMax([
            'payments as report_latest_paid_at' => fn ($query) => $this->constrainPayments($query, 'paid', $examIds),
        ], 'paid_at');

        return $this->applySort($query, $filters);
    }

    public function row(User $student, array $filters): array
    {
        $enrollments = $student->enrollments
            ->filter(fn ($enrollment) => $enrollment->exam !== null);

        return [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $student->phone,
            'class' => $student->classLevel?->label,
            'school' => $student->school,
            'city' => $student->city,
            'state' => $student->state,
            'is_active' => (bool) $student->is_active,
            'registration_source' => $student->registration_source ?: 'website',
            'registration_source_label' => $student->registrationSourceLabel(),
            'registered_at' => $student->created_at?->toIso8601String(),
            'active_enrollments_count' => (int) $student->active_enrollments_count,
            'olympiads' => $enrollments->map(fn ($enrollment) => [
                'id' => $enrollment->exam->id,
                'name' => $enrollment->exam->name,
                'code' => $enrollment->exam->exam_code,
                'subject' => $enrollment->exam->subject?->name,
            ])->values()->all(),
            'subjects' => $enrollments->pluck('exam.subject.name')->filter()->unique()->values()->all(),
            'paid_total' => (float) ($student->report_paid_total ?? 0),
            'paid_payments_count' => (int) $student->report_paid_payments_count,
            'pending_payments_count' => (int) $student->report_pending_payments_count,
            'latest_paid_at' => $student->report_latest_paid_at,
            'payment_label' => $this->paymentLabel($student, $filters),
        ];
    }

    public function summary(array $filters): array
    {
        $matched = $this->query($filters);
        $matchedCount = (clone $matched)->count();
        $examIds = $this->scopedExamIds($filters);

        $paidStudents = (clone $matched)
            ->whereHas('payments', fn (Builder $query) => $this->constrainPayments($query, 'paid', $examIds))
            ->count();

        $enrolledStudents = (clone $matched)
            ->whereHas('enrollments', function (Builder $query) use ($examIds) {
                $query->where('status', 'enrolled');
                $this->constrainEnrollments($query, $examIds);
            })->count();

        $payments = Payment::query()
            ->whereIn('user_id', (clone $matched)->select('users.id'));
        $this->constrainPayments($payments, 'paid', $examIds);

        return [
            'matched' => $matchedCount,
            'paid' => $paidStudents,
            'unpaid' => max(0, $matchedCount - $paidStudents),
            'enrolled' => $enrolledStudents,
            'collected' => (float) $payments->sum('amount'),
        ];
    }

    public function metadata(): array
    {
        return [
            'classLevels' => ClassLevel::query()->orderBy('sort_order')->get(['id', 'label']),
            'subjects' => Subject::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'exams' => Exam::query()
                ->with(['subject:id,name', 'classLevel:id,label'])
                ->orderByDesc('starts_at')
                ->orderBy('name')
                ->get(['id', 'name', 'exam_code', 'subject_id', 'class_level_id', 'status']),
            'states' => User::indianStates(),
            'registrationSources' => User::REGISTRATION_SOURCES,
        ];
    }

    public function filterLabels(array $filters): array
    {
        $labels = [];
        $map = [
            'payment_status' => [
                'paid' => 'Paid', 'unpaid' => 'Unpaid', 'pending' => 'Pending payment',
                'failed' => 'Failed payment', 'refunded' => 'Refunded', 'no_payments' => 'No payment records',
            ],
            'registration_source' => User::REGISTRATION_SOURCES,
        ];

        foreach ($map as $key => $options) {
            if (isset($filters[$key])) {
                $labels[] = $options[$filters[$key]];
            }
        }

        if (isset($filters['class_level_id'])) {
            $labels[] = 'Class: '.ClassLevel::find($filters['class_level_id'])?->label;
        }
        if (isset($filters['subject_id'])) {
            $labels[] = 'Subject: '.Subject::find($filters['subject_id'])?->name;
        }
        if (isset($filters['exam_id'])) {
            $labels[] = 'Olympiad: '.Exam::find($filters['exam_id'])?->name;
        }
        if (isset($filters['state'])) {
            $labels[] = 'State: '.$filters['state'];
        }

        foreach (['date_from' => 'Joined from', 'date_to' => 'Joined to'] as $key => $label) {
            if (isset($filters[$key])) {
                $labels[] = "{$label}: {$filters[$key]}";
            }
        }

        return $labels ?: ['All students'];
    }

    private function applyStudentFilters(Builder $query, array $filters): void
    {
        foreach (['class_level_id', 'state'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        // Campaign attribution. 'website' also covers accounts created before
        // the source column existed, which were backfilled to 'website'.
        if (isset($filters['registration_source'])) {
            $source = $filters['registration_source'];
            $query->where(fn (Builder $q) => $source === 'website'
                ? $q->where('registration_source', 'website')->orWhereNull('registration_source')
                : $q->where('registration_source', $source));
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    private function applyCourseFilter(Builder $query, array $filters, ?Collection $examIds): void
    {
        if ($examIds === null || isset($filters['payment_status'])) {
            return;
        }

        $query->whereHas('enrollments', function (Builder $query) use ($examIds) {
            $query->where('status', 'enrolled');
            $this->constrainEnrollments($query, $examIds);
        });
    }

    private function applyPaymentFilter(Builder $query, array $filters, ?Collection $examIds): void
    {
        $status = $filters['payment_status'] ?? null;

        if ($status === 'unpaid') {
            $query->whereDoesntHave('payments', fn (Builder $payment) => $this->constrainPayments($payment, 'paid', $examIds));

            return;
        }

        if ($status === 'no_payments') {
            $query->whereDoesntHave('payments', fn (Builder $payment) => $this->constrainPayments($payment, null, $examIds));

            return;
        }

        $databaseStatus = match ($status) {
            'pending' => 'created',
            'paid', 'failed', 'refunded' => $status,
            default => null,
        };

        if ($databaseStatus !== null) {
            $query->whereHas('payments', fn (Builder $payment) => $this->constrainPayments($payment, $databaseStatus, $examIds));
        }
    }

    private function constrainPayments(Builder $query, ?string $status, ?Collection $examIds): void
    {
        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($examIds !== null) {
            if ($examIds->isEmpty()) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function (Builder $query) use ($examIds) {
                $query->whereHas('enrollments', fn (Builder $enrollment) => $enrollment->whereIn('exam_id', $examIds));

                foreach ($examIds as $examId) {
                    $query->orWhereJsonContains('notes->exam_ids', (int) $examId);
                }
            });
        }
    }

    private function constrainEnrollments(Builder|Relation $query, ?Collection $examIds): void
    {
        if ($examIds !== null) {
            if ($examIds->isEmpty()) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('exam_id', $examIds);
        }
    }

    private function scopedExamIds(array $filters): ?Collection
    {
        if (isset($filters['exam_id'])) {
            return collect([(int) $filters['exam_id']]);
        }

        if (isset($filters['subject_id'])) {
            return Exam::query()->where('subject_id', $filters['subject_id'])->pluck('id');
        }

        return null;
    }

    private function applySort(Builder $query, array $filters): Builder
    {
        $direction = $filters['direction'] ?? 'desc';

        return match ($filters['sort'] ?? 'registered_at') {
            'name' => $query->orderBy('name', $direction)->orderBy('id'),
            'paid_total' => $query->orderBy('report_paid_total', $direction)->orderBy('id'),
            'enrollments' => $query->orderBy('active_enrollments_count', $direction)->orderBy('id'),
            default => $query->orderBy('created_at', $direction)->orderBy('id'),
        };
    }

    private function paymentLabel(User $student, array $filters): string
    {
        if (isset($filters['payment_status'])) {
            return match ($filters['payment_status']) {
                'paid' => 'Paid',
                'unpaid' => 'Unpaid',
                'pending' => 'Pending',
                'failed' => 'Failed',
                'refunded' => 'Refunded',
                'no_payments' => 'No payment',
            };
        }

        if ((int) $student->report_paid_payments_count > 0) {
            return 'Paid';
        }

        if ((int) $student->report_pending_payments_count > 0) {
            return 'Pending';
        }

        if ((int) $student->report_refunded_payments_count > 0) {
            return 'Refunded';
        }

        if ((int) $student->report_failed_payments_count > 0) {
            return 'Failed';
        }

        return 'Unpaid';
    }
}
