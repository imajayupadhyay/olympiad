<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

class EnrollmentService
{
    /**
     * Validate a set of exam ids and return a checkout summary:
     * line items (per exam) + the total fee. Only published exams are accepted,
     * and exams the user is already enrolled in are excluded.
     *
     * @return array{items: array<int, array<string, mixed>>, total: float, currency: string}
     */
    public function selectionSummary(array $examIds, ?User $user = null): array
    {
        $exams = $this->resolveExams($examIds, $user);

        $items = $exams->map(fn (Exam $exam) => [
            'id'         => $exam->id,
            'name'       => $exam->name,
            'fee_amount' => (float) $exam->fee_amount,
            'currency'   => $exam->fee_currency,
            'is_free'    => $exam->isFree(),
        ])->values()->all();

        return [
            'items'    => $items,
            'total'    => (float) $exams->sum(fn (Exam $e) => (float) $e->fee_amount),
            'currency' => $exams->first()->fee_currency ?? 'INR',
        ];
    }

    /**
     * Enrol a user into free exams instantly (no payment).
     * Silently skips any non-free or already-enrolled exam.
     *
     * @return Collection<int, ExamEnrollment>
     */
    public function enrollFree(User $user, array $examIds): Collection
    {
        $exams = $this->resolveExams($examIds, $user)->filter->isFree();

        return $exams->map(fn (Exam $exam) => $this->createEnrollment($user, $exam));
    }

    /**
     * Enrol a user into the selected exams after a successful payment.
     *
     * @return Collection<int, ExamEnrollment>
     */
    public function enrollAfterPayment(User $user, Payment $payment, array $examIds): Collection
    {
        $exams = $this->resolveExams($examIds, $user);

        return $exams->map(fn (Exam $exam) => $this->createEnrollment($user, $exam, $payment));
    }

    /**
     * Idempotent enrolment: re-confirms an existing row or creates a new one.
     */
    protected function createEnrollment(User $user, Exam $exam, ?Payment $payment = null): ExamEnrollment
    {
        return ExamEnrollment::updateOrCreate(
            ['user_id' => $user->id, 'exam_id' => $exam->id],
            [
                'payment_id'  => $payment?->id,
                'status'      => 'enrolled',
                'amount'      => (float) $exam->fee_amount,
                'currency'    => $exam->fee_currency,
                'enrolled_at' => now(),
            ]
        );
    }

    /**
     * Load published exams for the given ids, excluding ones the user is already enrolled in.
     *
     * @return Collection<int, Exam>
     */
    protected function resolveExams(array $examIds, ?User $user = null): Collection
    {
        $ids = collect($examIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = Exam::whereIn('id', $ids)->where('status', 'published');

        $exams = $query->get();

        if ($user) {
            $enrolledIds = ExamEnrollment::where('user_id', $user->id)
                ->whereIn('exam_id', $ids)
                ->where('status', 'enrolled')
                ->pluck('exam_id');

            $exams = $exams->reject(fn (Exam $e) => $enrolledIds->contains($e->id))->values();
        }

        return $exams;
    }
}
