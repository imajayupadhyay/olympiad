<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Razorpay webhook receiver — the reliability net for payments where the browser
 * never returned the signed response (e.g. closed after paying). Verifies the
 * webhook signature, then marks the matching Payment paid (idempotent).
 */
class RazorpayWebhookController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $body = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');
        $secret = config('services.razorpay.webhook_secret');

        if (! $secret) {
            return response('webhook not configured', 503);
        }

        try {
            $this->payments->api()->utility->verifyWebhookSignature($body, $signature, $secret);
        } catch (\Throwable $e) {
            return response('invalid signature', 400);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;

        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            $entity = $payload['payload']['payment']['entity'] ?? [];
            $orderId = $entity['order_id'] ?? null;
            $paymentId = $entity['id'] ?? null;

            if ($orderId && $paymentId) {
                $payment = Payment::where('razorpay_order_id', $orderId)->first();

                if ($payment && $payment->status !== 'paid') {
                    $this->payments->markPaidFromWebhook($payment, $paymentId);
                }
            }
        }

        return response('ok', 200);
    }
}
