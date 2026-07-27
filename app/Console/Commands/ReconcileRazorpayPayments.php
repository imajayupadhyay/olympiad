<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReconcileRazorpayPayments extends Command
{
    protected $signature = 'payments:reconcile-razorpay
        {--payment= : Reconcile one local payment ID regardless of age}
        {--minutes= : Ignore newer payments to give callbacks and webhooks time}
        {--days= : Oldest pending payment age to inspect}
        {--limit= : Maximum payments per run}';

    protected $description = 'Recover captured Razorpay payments that are still pending locally.';

    public function handle(PaymentService $payments): int
    {
        $limit = min(500, max(1, (int) ($this->option('limit') ?? config('services.razorpay.reconciliation.batch_size', 100))));
        $query = Payment::query()
            ->where('status', 'created')
            ->where('gateway', 'razorpay')
            ->whereNotNull('razorpay_order_id')
            ->oldest('id');

        if ($paymentId = $this->option('payment')) {
            $query->whereKey($paymentId);
        } else {
            $minutes = max(0, (int) ($this->option('minutes') ?? config('services.razorpay.reconciliation.delay_minutes', 2)));
            $days = max(1, (int) ($this->option('days') ?? config('services.razorpay.reconciliation.lookback_days', 30)));
            $query->where('created_at', '<=', now()->subMinutes($minutes))
                ->where('created_at', '>=', now()->subDays($days));
        }

        $candidates = $query->limit($limit)->get();

        if ($candidates->isEmpty()) {
            $this->info('No eligible pending Razorpay payments found.');

            return self::SUCCESS;
        }

        $counts = ['paid' => 0, 'already_paid' => 0, 'pending' => 0, 'skipped' => 0, 'mismatch' => 0, 'errors' => 0];

        foreach ($candidates as $payment) {
            try {
                $result = $payments->reconcileCapturedPayment($payment);
                $counts[$result['status']]++;

                if (in_array($result['status'], ['paid', 'mismatch'], true)) {
                    $this->line("Payment {$payment->id}: {$result['status']} — {$result['message']}");
                }
            } catch (\Throwable $e) {
                report($e);
                $counts['errors']++;
                $this->error("Payment {$payment->id}: {$e->getMessage()}");
            }
        }

        $this->info(sprintf(
            'Checked %d: %d recovered, %d still pending, %d already paid, %d mismatched, %d skipped, %d errors.',
            $candidates->count(),
            $counts['paid'],
            $counts['pending'],
            $counts['already_paid'],
            $counts['mismatch'],
            $counts['skipped'],
            $counts['errors'],
        ));

        return $counts['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
