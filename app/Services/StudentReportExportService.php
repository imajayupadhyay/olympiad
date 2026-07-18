<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentReportExportService
{
    public const MAX_EXCEL_ROWS = 25000;

    public const MAX_PDF_ROWS = 3000;

    public function excel(Collection $rows, array $filters, array $summary, array $filterLabels): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet($rows, $summary, $filterLabels);
        $filename = $this->filename('xlsx');

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function pdf(Collection $rows, array $summary, array $filterLabels): Response
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports.students-pdf', [
            'rows' => $rows,
            'summary' => $summary,
            'filterLabels' => $filterLabels,
            'generatedAt' => now(),
        ])->render());
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename('pdf').'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function spreadsheet(Collection $rows, array $summary, array $filterLabels): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Students');
        $sheet->setCellValue('A1', 'National Olympiad Hunt - Student Report');
        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A2', 'Generated: '.now()->format('d M Y, h:i A'));
        $sheet->mergeCells('A2:P2');
        $sheet->setCellValue('A3', sprintf(
            'Matched: %s | Paid: %s | Unpaid: %s | Enrolled: %s | Collected: INR %s',
            number_format($summary['matched']),
            number_format($summary['paid']),
            number_format($summary['unpaid']),
            number_format($summary['enrolled']),
            number_format($summary['collected'], 2),
        ));
        $sheet->mergeCells('A3:P3');
        $sheet->setCellValueExplicit('A4', 'Filters: '.implode(' | ', $filterLabels), DataType::TYPE_STRING);
        $sheet->mergeCells('A4:P4');

        $headers = [
            'Student ID', 'Name', 'Email', 'Phone', 'Class', 'School', 'City', 'State',
            'Account', 'Registered', 'Olympiads', 'Subjects', 'Enrollment Count',
            'Payment', 'Paid in Scope (INR)', 'Last Paid',
        ];
        $sheet->fromArray($headers, null, 'A6');

        $rowNumber = 7;
        foreach ($rows as $row) {
            $values = [
                $row['id'], $row['name'], $row['email'], $row['phone'] ?: '-', $row['class'] ?: '-',
                $row['school'] ?: '-', $row['city'] ?: '-', $row['state'] ?: '-',
                $row['is_active'] ? 'Active' : 'Disabled',
                $this->date($row['registered_at']),
                collect($row['olympiads'])->pluck('name')->implode(', ') ?: '-',
                implode(', ', $row['subjects']) ?: '-',
                $row['active_enrollments_count'], $row['payment_label'], $row['paid_total'],
                $this->date($row['latest_paid_at']),
            ];

            foreach ($values as $columnIndex => $value) {
                $cell = $sheet->getCell([$columnIndex + 1, $rowNumber]);
                if (in_array($columnIndex, [0, 12, 14], true)) {
                    $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
                } else {
                    $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                }
            }
            $rowNumber++;
        }

        $sheet->getStyle('A1:P1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0A1024');
        $sheet->getStyle('A6:P6')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A6:P6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEE6A2C');
        $sheet->getStyle('A1:P'.$rowNumber)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('K7:L'.$rowNumber)->getAlignment()->setWrapText(true);
        $sheet->getStyle('O7:O'.$rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->freezePane('A7');
        $sheet->setAutoFilter('A6:P'.max(6, $rowNumber - 1));

        $widths = [10, 22, 30, 16, 12, 28, 18, 20, 12, 16, 38, 24, 14, 12, 18, 16];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }

        return $spreadsheet;
    }

    private function date(?string $value): string
    {
        return $value ? date('d M Y', strtotime($value)) : '-';
    }

    private function filename(string $extension): string
    {
        return 'student-report-'.now()->format('Y-m-d-His').'.'.$extension;
    }
}
