<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Console\Command;

class BackfillReceipts extends Command
{
    protected $signature = 'receipts:backfill {--chunk=200 : Number of payments to process per chunk}';

    protected $description = 'Issue missing receipts for completed payments in payment-date order.';

    public function handle(ReceiptService $receipts): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $issued = 0;

        $ids = Payment::query()
            ->where('status', 'paid')
            ->whereDoesntHave('receipt')
            ->orderByRaw('COALESCE(paid_at, created_at) asc')
            ->orderBy('id')
            ->pluck('id');

        $ids->chunk($chunk)->each(function ($chunkIds) use ($receipts, &$issued): void {
            $payments = Payment::query()
                ->whereIn('id', $chunkIds)
                ->get()
                ->sortBy(fn (Payment $payment) => $chunkIds->search($payment->id));

            foreach ($payments as $payment) {
                $receipts->issueForPayment($payment);
                $issued++;
            }
        });

        $this->info("Issued {$issued} missing receipt(s).");

        return self::SUCCESS;
    }
}
