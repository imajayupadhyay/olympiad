<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ReceiptSetting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class ReceiptExportService
{
    public const MAX_BULK_RECEIPTS = 200;

    public const MAX_SALES_REPORT_ROWS = 10000;

    /**
     * @param  Collection<int, Receipt>  $receipts
     */
    public function receipts(Collection $receipts, ?string $filename = null): Response
    {
        abort_if($receipts->isEmpty(), 404);

        $settings = ReceiptSetting::current();
        $filename ??= $receipts->count() === 1
            ? $receipts->first()->filename()
            : 'receipts-'.now()->format('Y-m-d-His').'.pdf';

        $dompdf = $this->dompdf(view('receipts.pdf', [
            'receipts' => $receipts,
            'company' => $settings->renderCompanyPayload(),
            'numberingSettings' => $settings,
            'generatedAt' => now(),
        ])->render(), 'portrait');

        return $this->download($dompdf, $filename);
    }

    /**
     * @param  Collection<int, Receipt>  $receipts
     */
    public function salesReport(Collection $receipts, array $summary, array $filters): Response
    {
        $dateFrom = date('d-m-Y', strtotime((string) $filters['date_from']));
        $dateTo = date('d-m-Y', strtotime((string) $filters['date_to']));
        $settings = ReceiptSetting::current();

        $dompdf = $this->dompdf(view('receipts.sales-report-pdf', [
            'receipts' => $receipts,
            'summary' => $summary,
            'filters' => $filters,
            'company' => $settings->renderCompanyPayload(),
            'numberingSettings' => $settings,
            'generatedAt' => now(),
        ])->render(), 'landscape');

        return $this->download($dompdf, "sales-report-{$dateFrom}-to-{$dateTo}.pdf");
    }

    private function dompdf(string $html, string $orientation): Dompdf
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', $orientation);
        $dompdf->render();

        return $dompdf;
    }

    private function download(Dompdf $dompdf, string $filename): Response
    {
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
