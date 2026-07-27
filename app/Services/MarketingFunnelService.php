<?php

namespace App\Services;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\ReferralSetting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The /marketing landing-page funnel — the whole "register + pick olympiads +
 * referral + pay" journey collapsed into a single submit.
 *
 * Deliberately a thin seam over the existing engine: the account is created
 * exactly the way Auth\RegisteredUserController does it (auto-generated password
 * emailed to the student), the referral is attributed through ReferralService,
 * and the money is handled end-to-end by PaymentService. Nothing in the payment,
 * coupon or referral services changes for this flow.
 */
class MarketingFunnelService
{
    public function __construct(
        protected EnrollmentService $enrollments,
        protected PaymentService $payments,
        protected ReferralService $referrals,
    ) {}

    /**
     * Every published olympiad, flattened for the landing page. The visitor has no
     * account yet, so we ship the whole catalogue and let the form filter it by the
     * class they pick (see resolveExamIds() for the server-side re-check).
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        return Exam::where('status', 'published')
            ->with('subject:id,name,icon,color', 'classLevel:id,label')
            ->withCount('questions')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Exam $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'class_level_id' => $e->class_level_id,
                'class_level' => $e->classLevel?->label,
                'subject' => $e->subject?->only(['name', 'icon', 'color']),
                'questions_count' => $e->questions_count,
                'duration_minutes' => $e->duration_minutes,
                'fee_amount' => (float) $e->fee_amount,
                'is_free' => $e->isFree(),
            ])
            ->all();
    }

    /**
     * The referee-side discount rule, shaped for the form's live price preview.
     * Mirrors ReferralService::usableCouponRules() — which we cannot call here
     * because the coupon it describes is only minted once the account exists.
     *
     * @return array<string, mixed>|null
     */
    public function refereePreviewRule(): ?array
    {
        $settings = ReferralSetting::current();

        if (! $settings->is_active || (float) $settings->referee_discount_value <= 0) {
            return null;
        }

        return [
            'type' => $settings->referee_discount_type,
            'value' => (float) $settings->referee_discount_value,
            'max_discount' => $settings->referee_max_discount !== null ? (float) $settings->referee_max_discount : null,
            'min_order_amount' => (float) $settings->referee_min_order_amount,
            'label' => $settings->refereeDiscountLabel(),
        ];
    }

    /**
     * The two sides of the referral program, for the "Refer & earn" card on the
     * form. Shown to everyone while the program is active — the visitor has no
     * account yet, so there is no personal link or progress to show until they
     * register (see the shareState() hand-off in register()).
     *
     * @return array{welcome:string,reward:string,threshold:int,mode:string}|null
     */
    public function programCard(): ?array
    {
        $settings = ReferralSetting::current();

        if (! $settings->is_active) {
            return null;
        }

        return [
            'welcome' => $settings->refereeDiscountLabel(),
            'reward' => $settings->referrerRewardLabel(),
            'threshold' => max(1, (int) $settings->unlock_threshold),
            'mode' => $settings->qualify_on,
        ];
    }

    /**
     * Confirm a referral code that arrived on the link, so the form can show the
     * same "welcome discount unlocked" banner the registration wizard shows.
     * Only the referrer's first name is exposed — never the account itself.
     *
     * @return array{name:string,label:string}|null
     */
    public function referredBy(?string $code): ?array
    {
        $settings = ReferralSetting::current();

        if (! $settings->is_active || ! $code) {
            return null;
        }

        $referrer = $this->referrals->resolveReferrer($code);

        if (! $referrer) {
            return null;
        }

        return [
            'name' => Str::of($referrer->name)->trim()->explode(' ')->first(),
            'label' => $settings->refereeDiscountLabel(),
        ];
    }

    /**
     * Run the whole funnel for one submission: account, referral, free enrolments
     * and the final priced cart.
     *
     * Deliberately stops short of the gateway. Registration settles every
     * calculation first and hands back the confirmed figures; opening the Razorpay
     * order is a separate, explicit step behind the Pay button (openOrderFor).
     * That keeps the money step from jumping the queue in front of the totals and
     * the Refer & earn card, and means a gateway outage can never break a signup.
     *
     * @param  array{name:string,email:string,phone:string,class_level_id:int,exam_ids:list<int>}  $data
     * @return array{status:'free'|'ready', ...}
     */
    public function register(array $data, ?string $referralCode = null): array
    {
        // Re-checked before the account is created, so an invalid selection never
        // leaves a half-finished signup behind.
        $examIds = $this->resolveExamIds($data['exam_ids'], (int) $data['class_level_id']);

        if (! $examIds) {
            throw ValidationException::withMessages([
                'exam_ids' => 'Those olympiads are not available for the class you selected.',
            ]);
        }

        $user = $this->createStudent($data);

        // Attribution must happen before the payment is priced: it is what mints the
        // referee's personal welcome coupon that autoCouponFor() then picks up.
        if ($referralCode) {
            $this->referrals->attribute($user, $referralCode);
        }

        Auth::login($user);

        $exams = Exam::whereIn('id', $examIds)->get();
        $free = $exams->filter->isFree()->pluck('id')->all();
        $paid = $exams->reject->isFree()->pluck('id')->all();

        // Free olympiads never touch the gateway — enrol them straight away.
        if ($free) {
            $this->enrollments->enrollFree($user, $free, 'marketing');
        }

        if (! $paid) {
            return [
                'status' => 'free',
                'redirect' => route('student.dashboard'),
                'message' => 'You are enrolled. Welcome aboard! 🎉',
            ];
        }

        $payment = $this->payments->createPendingPayment($user, $paid, 'marketing');
        $this->applyBestReferralCoupon($payment);
        $payment->refresh()->load('coupon');

        return [
            'status' => 'ready',
            'payment_id' => $payment->id,
            // Server-confirmed figures — the single source of truth for what the
            // student is about to be charged. The form's live preview is only a hint.
            'gross' => (float) $payment->gross_amount,
            'discount' => (float) $payment->discount_amount,
            'payable' => (float) $payment->amount,
            'currency' => $payment->currency,
            'coupon' => $payment->coupon ? [
                'code' => $payment->coupon->code,
                'source' => $payment->coupon->source,
            ] : null,
            'items' => Exam::whereIn('id', $paid)->get(['id', 'name', 'fee_amount'])
                ->map(fn (Exam $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'fee_amount' => (float) $e->fee_amount,
                ])->all(),
            // The account now exists, so the Refer & earn card can carry a real link
            // and live counts — the same payload the registration wizard renders.
            'referral' => $this->referrals->shareState($user),
        ];
    }

    /**
     * Open (or re-open) the Razorpay order for an already-priced funnel payment.
     * This is what the Pay button calls — never registration itself.
     *
     * @return array{status:'free'|'ok'|'failed', ...}
     */
    public function openOrderFor(Payment $payment): array
    {
        try {
            $order = $this->payments->openOrder($payment);

            // A coupon that lapsed between apply and order is dropped by openOrder;
            // the cart is now full price, so ask for the order once more.
            if ($order['status'] === 'coupon_dropped') {
                $order = $this->payments->openOrder($payment->refresh());
            }
        } catch (\Throwable $e) {
            report($e);

            return [
                'status' => 'failed',
                'payment_id' => $payment->id,
                'message' => 'We could not reach the payment gateway. Your account and selection are saved — please try again.',
            ];
        }

        // Fully covered by the welcome discount — enrol without the gateway.
        if ($order['status'] === 'free') {
            $this->payments->enrollFreeByCoupon($payment);

            return [
                'status' => 'free',
                'redirect' => route('student.dashboard'),
                'message' => 'Your discount covered the full amount — you are enrolled! 🎉',
            ];
        }

        if ($order['status'] === 'paid') {
            return [
                'status' => 'free',
                'redirect' => route('student.dashboard'),
                'message' => 'Your payment was already completed — you are enrolled! 🎉',
            ];
        }

        $payment->refresh();
        $user = $payment->user;

        return [
            'status' => 'ok',
            'payment_id' => $payment->id,
            'order_id' => $order['order_id'],
            'amount' => $order['amount_paise'],
            'currency' => $order['currency'],
            'key_id' => $order['key_id'],
            // Razorpay's signed POST callback is reliable in Instagram/Facebook
            // WebViews where the JavaScript handler is not supported.
            'callback_url' => route('marketing.payment.callback', $payment),
            'gross' => (float) $payment->gross_amount,
            'discount' => (float) $payment->discount_amount,
            'payable' => (float) $payment->amount,
            'prefill' => [
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->phone,
            ],
        ];
    }

    /**
     * Create the student account. Byte-for-byte the same contract as
     * Auth\RegisteredUserController::store() — no password is collected; one is
     * generated and emailed, leaving password_changed_at null so the dashboard's
     * "secure your account" nudge fires on first login.
     */
    protected function createStudent(array $data): User
    {
        $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false);

        $user = DB::transaction(fn () => User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'class_level_id' => $data['class_level_id'],
            'role' => 'student',
            'registration_source' => 'marketing',
            'is_active' => true,
            'password' => Hash::make($plainPassword),
        ]));

        event(new Registered($user));

        $emails = app(ManagedEmailService::class);
        $emails->queue(
            'student_registered',
            $user,
            $emails->studentRegistrationVariables($user, $plainPassword),
            ['related_type' => User::class, 'related_id' => $user->id]
        );

        return $user;
    }

    /** Auto-apply the best personal referral coupon, exactly as checkout does. */
    protected function applyBestReferralCoupon(Payment $payment): void
    {
        if ($payment->coupon_id) {
            return;
        }

        if ($coupon = $this->referrals->autoCouponFor($payment->user, (float) $payment->gross_amount)) {
            $this->payments->applyCoupon($payment, $coupon->code);
            $payment->refresh();
        }
    }

    /**
     * Keep only published olympiads the chosen class is actually eligible for, so a
     * tampered payload cannot enrol a student into another class's exam.
     *
     * @param  list<int>  $examIds
     * @return list<int>
     */
    protected function resolveExamIds(array $examIds, int $classLevelId): array
    {
        return Exam::whereIn('id', array_unique($examIds))
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('class_level_id')->orWhere('class_level_id', $classLevelId))
            ->pluck('id')
            ->all();
    }

    /** Active class levels for the form's class picker. */
    public function classLevels()
    {
        return ClassLevel::active();
    }
}
