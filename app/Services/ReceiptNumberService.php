<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ReceiptSequence;
use App\Models\ReceiptSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptNumberService
{
    public const SERIES = 'default';

    /**
     * Reserve the next receipt number under a row lock.
     *
     * @return array{settings:ReceiptSetting, financial_year:string, sequence_number:int, receipt_number:string}
     */
    public function reserve(CarbonInterface $date): array
    {
        ReceiptSetting::current();

        /** @var ReceiptSetting $settings */
        $settings = ReceiptSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
        $financialYear = $settings->financialYear($date);

        $sequence = ReceiptSequence::query()
            ->where('series', self::SERIES)
            ->where('financial_year', $financialYear)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = ReceiptSequence::create([
                'series' => self::SERIES,
                'financial_year' => $financialYear,
                'next_number' => $this->minimumNextNumber($financialYear),
            ]);
        }

        $sequenceNumber = max((int) $sequence->next_number, $this->minimumNextNumber($financialYear));
        $receiptNumber = $settings->formatReceiptNumber($sequenceNumber, $date);

        while (Receipt::query()->where('receipt_number', $receiptNumber)->exists()) {
            $sequenceNumber++;
            $receiptNumber = $settings->formatReceiptNumber($sequenceNumber, $date);
        }

        $sequence->update(['next_number' => $sequenceNumber + 1]);

        return [
            'settings' => $settings,
            'financial_year' => $financialYear,
            'sequence_number' => $sequenceNumber,
            'receipt_number' => $receiptNumber,
        ];
    }

    /**
     * @return array{financial_year:string, next_number:int, minimum_next_number:int, next_receipt_number:string}
     */
    public function preview(?CarbonInterface $date = null): array
    {
        $settings = ReceiptSetting::current();
        $date ??= now();
        $financialYear = $settings->financialYear($date);
        $minimum = $this->minimumNextNumber($financialYear);
        $next = ReceiptSequence::query()
            ->where('series', self::SERIES)
            ->where('financial_year', $financialYear)
            ->value('next_number');

        $next = max((int) ($next ?: $minimum), $minimum);

        return [
            'financial_year' => $financialYear,
            'next_number' => $next,
            'minimum_next_number' => $minimum,
            'next_receipt_number' => $settings->formatReceiptNumber($next, $date),
        ];
    }

    public function setNextNumber(int $nextNumber, ?CarbonInterface $date = null): void
    {
        DB::transaction(function () use ($nextNumber, $date): void {
            ReceiptSetting::current();

            /** @var ReceiptSetting $settings */
            $settings = ReceiptSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $date ??= now();
            $financialYear = $settings->financialYear($date);
            $minimum = $this->minimumNextNumber($financialYear);

            if ($nextNumber < $minimum) {
                throw ValidationException::withMessages([
                    'next_sequence_number' => "The next receipt number cannot be lower than {$minimum} for {$financialYear}.",
                ]);
            }

            $sequence = ReceiptSequence::query()
                ->where('series', self::SERIES)
                ->where('financial_year', $financialYear)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $sequence->update(['next_number' => $nextNumber]);

                return;
            }

            ReceiptSequence::create([
                'series' => self::SERIES,
                'financial_year' => $financialYear,
                'next_number' => $nextNumber,
            ]);
        });
    }

    private function minimumNextNumber(string $financialYear): int
    {
        $maxIssued = Receipt::query()
            ->where('series', self::SERIES)
            ->where('financial_year', $financialYear)
            ->max('sequence_number');

        return ((int) $maxIssued) + 1;
    }
}
