<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Rules\ValidPhoneNumber;
use App\Services\MarketingFunnelService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Razorpay\Api\Errors\SignatureVerificationError;

/**
 * The /marketing campaign landing page and its one-shot funnel: a single form
 * captures the student, their olympiads and an optional referral code, then the
 * Razorpay modal opens in place. Every action here delegates to
 * MarketingFunnelService so the payment/referral engines stay untouched.
 */
class MarketingController extends Controller
{
    public function __construct(
        protected MarketingFunnelService $funnel,
        protected PaymentService $payments,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Public/Marketing/Index', [
            'classLevels' => $this->funnel->classLevels(),
            'exams' => $this->funnel->catalogue(),
            'referral' => $this->funnel->refereePreviewRule(),
            'program' => $this->funnel->programCard(),
            // Referrals are link-only, exactly as on /register: the code rides in on
            // /marketing?ref=CODE, CaptureReferral stashes it, and we simply confirm
            // it here. Nothing is ever typed.
            'referredBy' => $this->funnel->referredBy($request->session()->get('referral_code')),
        ]);
    }

    /** The single submit: create the account, attribute the referral, open the order. */
    public function register(Request $request): JsonResponse
    {
        if (Auth::check()) {
            return response()->json([
                'status' => 'logged_in',
                'redirect' => route('student.exams'),
                'message' => 'You are already signed in — pick your olympiads from your portal.',
            ], 409);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'phone' => ['required', 'string', 'max:25', new ValidPhoneNumber],
            'class_level_id' => 'required|exists:class_levels,id',
            'exam_ids' => 'required|array|min:1',
            'exam_ids.*' => 'integer|exists:exams,id',
        ], [
            'email.unique' => 'This email is already registered. Log in to enrol in more olympiads.',
            'exam_ids.required' => 'Select at least one olympiad to continue.',
        ]);

        // Same hand-off as RegisteredUserController: the referral code comes from the
        // session (put there by CaptureReferral), never from the form. pull() consumes
        // it so it can't re-attribute a later signup.
        $result = $this->funnel->register($data, $request->session()->pull('referral_code'));

        return response()->json($result);
    }

    /** Retry the gateway for a funnel payment that is still awaiting its order. */
    public function createOrder(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        if ($payment->isPaid()) {
            return response()->json([
                'status' => 'free',
                'redirect' => route('student.dashboard'),
                'message' => 'This payment is already complete.',
            ]);
        }

        return response()->json($this->funnel->openOrderFor($payment));
    }

    /** Verify the signed checkout response, then land the student on their dashboard. */
    public function verifyPayment(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        abort_unless($data['razorpay_order_id'] === $payment->razorpay_order_id, 400);

        try {
            $this->payments->verifyAndEnroll($payment, $data['razorpay_payment_id'], $data['razorpay_signature']);
        } catch (SignatureVerificationError $e) {
            return redirect()->route('student.payments.show', $payment)
                ->with('error', 'We could not verify that payment. Please try again.');
        }

        $payment->refresh();
        abort_unless($payment->isPaid(), 409, 'The payment order changed while verification was running.');

        return $this->successfulPaymentRedirect($payment);
    }

    /**
     * Razorpay's WebView/redirect callback. It is intentionally public and CSRF
     * exempt: authenticity comes from the Razorpay signature and the order ID
     * stored on our server, not from a browser session.
     */
    public function paymentCallback(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        abort_unless($payment->gateway === 'razorpay', 400);
        abort_unless($data['razorpay_order_id'] === $payment->razorpay_order_id, 400);

        try {
            $this->payments->verifyAndEnroll($payment, $data['razorpay_payment_id'], $data['razorpay_signature']);
        } catch (SignatureVerificationError $e) {
            abort(400, 'Payment signature verification failed.');
        }

        $payment->refresh();
        abort_unless($payment->isPaid(), 409, 'Payment is no longer eligible for fulfilment.');

        // The callback can return from a cross-site WebView without the original
        // session cookie. A valid signed payment safely re-establishes the buyer's
        // student session before redirecting to the dashboard.
        Auth::login($payment->user);
        $request->session()->regenerate();

        return $this->successfulPaymentRedirect($payment);
    }

    private function successfulPaymentRedirect(Payment $payment): RedirectResponse
    {
        return redirect()->route('student.dashboard')
            ->with('success', 'Payment successful — you are enrolled. 🎉')
            ->with('meta_purchase', [
                'event_id' => 'marketing_purchase_'.$payment->id,
                'payment_id' => $payment->id,
                // Payment.amount is the final post-discount amount in rupees.
                // Razorpay's order amount is in paise and must not be used here.
                'value' => (float) $payment->amount,
                'currency' => 'INR',
            ]);
    }
}
