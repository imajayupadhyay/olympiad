<?php

namespace App\Services;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentReportService
{
    public function query(array $filters): Builder
    {
        $query = User::query()->where('role', 'student');
        $examIds = $this->scopedExamIds($filters);

        $this->applyStudentFilters($query, $filters);
        $this->applyEnrollmentFilter($query, $filters, $examIds);
        $this->applyPaymentFilter($query, $filters, $examIds);

        return $query;
    }

    public function queryWithReportData(array $filters): Builder
    {
        $query = $this->query($filters)->with([
            'classLevel:id,label',
            'enrollments' => fn ($query) => $query
                ->where('status', 'enrolled')
                ->with(['exam:id,name,exam_code,subject_id', 'exam.subject:id,name'])
                ->latest('enrolled_at'),
        ])->withCount([
            'enrollments as active_enrollments_count' => fn ($query) => $query->where('status', 'enrolled'),
            'payments as paid_payments_count' => fn ($query) => $query->where('status', 'paid'),
            'payments as pending_payments_count' => fn ($query) => $query->where('status', 'created'),
        ])->withSum([
            'payments as paid_total' => fn ($query) => $query->where('status', 'paid'),
        ], 'amount')->withMax([
            'payments as latest_paid_at' => fn ($query) => $query->where('status', 'paid'),
        ], 'paid_at');

        return $this->applySort($query, $filters);
    }

    public function row(User $student): array
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
            'registered_at' => $student->created_at?->toIso8601String(),
            'active_enrollments_count' => (int) $student->active_enrollments_count,
            'olympiads' => $enrollments->map(fn ($enrollment) => [
                'id' => $enrollment->exam->id,
                'name' => $enrollment->exam->name,
                'code' => $enrollment->exam->exam_code,
                'subject' => $enrollment->exam->subject?->name,
            ])->values()->all(),
            'subjects' => $enrollments->pluck('exam.subject.name')->filter()->unique()->values()->all(),
            'paid_total' => (float) ($student->paid_total ?? 0),
            'paid_payments_count' => (int) $student->paid_payments_count,
            'pending_payments_count' => (int) $student->pending_payments_count,
            'latest_paid_at' => $student->latest_paid_at,
            'payment_label' => $this->paymentLabel($student),
        ];
    }

    public function summary(array $filters): array
    {
        $matched = $this->query($filters);
        $matchedCount = (clone $matched)->count();
        $examIds = $this->scopedExamIds($filters);

        $paidStudents = (clone $matched)
            ->whereHas('payments', fn (Builder $query) => $this->constrainPayments($query, 'paid', $filters, $examIds))
            ->count();

        $enrolledStudents = (clone $matched)
            ->whereHas('enrollments', function (Builder $query) use ($examIds) {
                $query->where('status', 'enrolled');
                $this->constrainEnrollments($query, $examIds);
            })->count();

        $payments = Payment::query()
            ->whereIn('user_id', (clone $matched)->select('users.id'));
        $this->constrainPayments($payments, 'paid', $filters, $examIds);

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
        ];
    }

    public function filterLabels(array $filters): array
    {
        $labels = [];
        $map = [
            'account_status' => ['active' => 'Active accounts', 'inactive' => 'Disabled accounts'],
            'enrollment_status' => ['enrolled' => 'Enrolled', 'not_enrolled' => 'Not enrolled'],
            'payment_status' => [
                'paid' => 'Paid', 'unpaid' => 'Unpaid', 'pending' => 'Pending payment',
                'failed' => 'Failed payment', 'refunded' => 'Refunded', 'no_payments' => 'No payment records',
            ],
        ];

        foreach ($map as $key => $options) {
            if (isset($filters[$key])) {
                $labels[] = $options[$filters[$key]];
            }
        }

        if (isset($filters['search'])) {
            $labels[] = 'Search: '.$filters['search'];
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

        foreach (['registered_from' => 'Registered from', 'registered_to' => 'Registered to', 'paid_from' => 'Paid from', 'paid_to' => 'Paid to'] as $key => $label) {
            if (isset($filters[$key])) {
                $labels[] = "{$label}: {$filters[$key]}";
            }
        }

        return $labels ?: ['All students'];
    }

    private function applyStudentFilters(Builder $query, array $filters): void
    {
        if (isset($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('school', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        foreach (['class_level_id', 'state'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['account_status'])) {
            $query->where('is_active', $filters['account_status'] === 'active');
        }
        if (isset($filters['registered_from'])) {
            $query->whereDate('created_at', '>=', $filters['registered_from']);
        }
        if (isset($filters['registered_to'])) {
            $query->whereDate('created_at', '<=', $filters['registered_to']);
        }
    }

    private function applyEnrollmentFilter(Builder $query, array $filters, ?Collection $examIds): void
    {
        $status = $filters['enrollment_status'] ?? null;

        $hasPaymentFilter = isset($filters['payment_status']) || isset($filters['paid_from']) || isset($filters['paid_to']);

        if ($status === null && $examIds !== null && ! $hasPaymentFilter) {
            $status = 'enrolled';
        }

        if ($status === null) {
            return;
        }

        $method = $status === 'enrolled' ? 'whereHas' : 'whereDoesntHave';
        $query->{$method}('enrollments', function (Builder $query) use ($examIds) {
            $query->where('status', 'enrolled');
            $this->constrainEnrollments($query, $examIds);
        });
    }

    private function applyPaymentFilter(Builder $query, array $filters, ?Collection $examIds): void
    {
        $status = $filters['payment_status'] ?? null;

        if ($status === 'unpaid') {
            $query->whereDoesntHave('payments', fn (Builder $payment) => $this->constrainPayments($payment, 'paid', $filters, $examIds));

            return;
        }

        if ($status === 'no_payments') {
            $query->whereDoesntHave('payments', fn (Builder $payment) => $this->constrainPayments($payment, null, $filters, $examIds, false));
            $this->applyPaidDateRequirement($query, $filters, $examIds);

            return;
        }

        $databaseStatus = match ($status) {
            'pending' => 'created',
            'paid', 'failed', 'refunded' => $status,
            default => null,
        };

        if ($databaseStatus !== null) {
            $query->whereHas('payments', fn (Builder $payment) => $this->constrainPayments($payment, $databaseStatus, $filters, $examIds));
            if ($databaseStatus !== 'paid') {
                $this->applyPaidDateRequirement($query, $filters, $examIds);
            }
        } elseif (isset($filters['paid_from']) || isset($filters['paid_to'])) {
            $query->whereHas('payments', fn (Builder $payment) => $this->constrainPayments($payment, 'paid', $filters, $examIds));
        }
    }

    private function applyPaidDateRequirement(Builder $query, array $filters, ?Collection $examIds): void
    {
        if (isset($filters['paid_from']) || isset($filters['paid_to'])) {
            $query->whereHas('payments', fn (Builder $payment) => $this->constrainPayments($payment, 'paid', $filters, $examIds));
        }
    }

    private function constrainPayments(Builder $query, ?string $status, array $filters, ?Collection $examIds, bool $applyPaidDates = true): void
    {
        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($applyPaidDates && ($status === 'paid' || $status === null)) {
            if (isset($filters['paid_from'])) {
                $query->whereDate('paid_at', '>=', $filters['paid_from']);
            }
            if (isset($filters['paid_to'])) {
                $query->whereDate('paid_at', '<=', $filters['paid_to']);
            }
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

    private function constrainEnrollments(Builder $query, ?Collection $examIds): void
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
            'paid_total' => $query->orderBy('paid_total', $direction)->orderBy('id'),
            'enrollments' => $query->orderBy('active_enrollments_count', $direction)->orderBy('id'),
            default => $query->orderBy('created_at', $direction)->orderBy('id'),
        };
    }

    private function paymentLabel(User $student): string
    {
        if ((int) $student->paid_payments_count > 0) {
            return 'Paid';
        }

        return (int) $student->pending_payments_count > 0 ? 'Pending' : 'Unpaid';
    }
}
