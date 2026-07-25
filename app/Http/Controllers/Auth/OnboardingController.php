<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Payment;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post-registration wizard: Step 2 (select olympiads) → Step 3 (checkout) → enrol.
 * Step 1 is the registration form itself (RegisteredUserController).
 */
class OnboardingController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollments,
        protected PaymentService $payments,
        protected ReferralService $referrals,
    ) {
    }

    /** Step 2 — pick olympiads (defaults to the student's class). */
    public function olympiads(Request $request): Response
    {
        $user = $request->user();

        $enrolledIds = $user->enrollments()->where('status', 'enrolled')->pluck('exam_id');

        $exams = Exam::where('status', 'published')
            ->when($user->class_level_id, fn ($q) => $q->where('class_level_id', $user->class_level_id))
            ->whereNotIn('id', $enrolledIds)
            ->with('subject:id,name,icon,color', 'classLevel:id,label')
            ->withCount('questions')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Exam $e) => [
                'id'               => $e->id,
                'name'             => $e->name,
                'subject'          => $e->subject?->only(['name', 'icon', 'color']),
                'class_level'      => $e->classLevel?->only(['label']),
                'questions_count'  => $e->questions_count,
                'duration_minutes' => $e->duration_minutes,
                'fee_amount'       => (float) $e->fee_amount,
                'is_free'          => $e->isFree(),
            ]);

        return Inertia::render('Auth/Onboarding/Olympiads', [
            'exams'     => $exams,
            'selected'  => session('onboarding_exam_ids', []),
            'referral'  => $this->referrals->shareState($user),
            'discounts' => $this->referrals->usableCouponRules($user),
        ]);
    }

    /** Persist the selection and move to checkout. */
    public function storeOlympiads(Request $request)
    {
        $data = $request->validate([
            'exam_ids'   => 'array',
            'exam_ids.*' => 'integer|exists:exams,id',
        ]);

        $ids = $data['exam_ids'] ?? [];

        if (empty($ids)) {
            return redirect()->route('student.dashboard')
                ->with('info', 'You can enrol in olympiads anytime from the Exams page.');
        }

        session(['onboarding_exam_ids' => $ids]);

        return redirect()->route('register.checkout');
    }

    /**
     * Step 3 — checkout. Nothing payable (all-free selection) enrols straight away;
     * otherwise a pending Payment is prepared and the coupon-enabled checkout is shown.
     * Reuses the shared `student.payments.*` coupon/order/verify endpoints so a new
     * registrant gets the exact same payment experience as a returning student.
     */
    public function checkout(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $ids = session('onboarding_exam_ids', []);

        if (empty($ids)) {
            return redirect()->route('register.olympiads');
        }

        $summary = $this->enrollments->selectionSummary($ids, $user);
        $canonicalIds = collect($summary['items'])->pluck('id')->sort()->values()->all();

        // No payable amount (all free, or everything already enrolled) → enrol and finish.
        if (empty($canonicalIds) || (float) $summary['total'] <= 0) {
            $enrolled = $this->enrollments->enrollFree($user, $canonicalIds);
            session()->forget(['onboarding_exam_ids', 'onboarding_payment_id']);

            return redirect()->route('student.dashboard')->with(
                'success',
                $enrolled->isNotEmpty()
                    ? "Welcome aboard! You're enrolled in {$enrolled->count()} olympiad(s)."
                    : 'Your account is ready. Browse olympiads anytime from the Exams page.'
            );
        }

        $payment = $this->pendingPaymentFor($user, $canonicalIds);
        $payment->load('coupon');

        // Auto-apply the referee welcome discount when nothing is applied yet.
        if (! $payment->coupon_id) {
            if ($auto = $this->referrals->autoCouponFor($user, (float) $payment->gross_amount)) {
                $this->payments->applyCoupon($payment, $auto->code);
                $payment->load('coupon');
            }
        }

        $items = Exam::whereIn('id', $payment->notes['exam_ids'] ?? [])
            ->get(['id', 'name', 'fee_amount'])
            ->map(fn (Exam $e) => ['id' => $e->id, 'name' => $e->name, 'fee_amount' => (float) $e->fee_amount]);

        return Inertia::render('Auth/Onboarding/Checkout', [
            'payment' => [
                'id'       => $payment->id,
                'gross'    => (float) $payment->gross_amount,
                'discount' => (float) $payment->discount_amount,
                'amount'   => (float) $payment->amount,
                'currency' => $payment->currency,
                'coupon'   => $payment->coupon ? [
                    'code'   => $payment->coupon->code,
                    'type'   => $payment->coupon->type,
                    'source' => $payment->coupon->source,
                ] : null,
            ],
            'items'   => $items,
            'keyId'   => config('services.razorpay.key_id'),
            'prefill' => [
                'name'    => $user->name,
                'email'   => $user->email,
                'contact' => $user->phone,
            ],
        ]);
    }

    /**
     * Reuse the still-pending payment for this exact selection (so a coupon survives
     * the back-and-forth re-renders); otherwise start a fresh one. The selection may
     * include free exams — they ride along in the payment and enrol on success.
     */
    protected function pendingPaymentFor(\App\Models\User $user, array $canonicalIds): Payment
    {
        if ($pid = session('onboarding_payment_id')) {
            $payment = Payment::where('id', $pid)
                ->where('user_id', $user->id)
                ->where('status', 'created')
                ->first();

            if ($payment) {
                $existing = collect($payment->notes['exam_ids'] ?? [])->sort()->values()->all();
                if ($existing === $canonicalIds) {
                    return $payment;
                }
            }
        }

        $payment = $this->payments->createPendingPayment($user, $canonicalIds, 'onboarding');
        session(['onboarding_payment_id' => $payment->id]);

        return $payment;
    }
}
