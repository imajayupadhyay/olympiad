<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolExportService
{
    public const MAX_EXCEL_ROWS = 25000;

    public function excel(Collection $rows, array $summary, array $filterLabels): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet($rows, $summary, $filterLabels);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $this->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function spreadsheet(Collection $rows, array $summary, array $filterLabels): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $this->buildSchoolsSheet($spreadsheet, $rows, $summary, $filterLabels);
        $this->buildCoordinatorsSheet($spreadsheet, $rows);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildSchoolsSheet(Spreadsheet $spreadsheet, Collection $rows, array $summary, array $filterLabels): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Schools');
        $sheet->setCellValue('A1', 'National Olympiad Hunt - School Management');
        $sheet->mergeCells('A1:R1');
        $sheet->setCellValue('A2', 'Generated: '.now()->format('d M Y, h:i A'));
        $sheet->mergeCells('A2:R2');
        $sheet->setCellValue('A3', sprintf(
            'Matched: %s | Active: %s | Inactive: %s | With Coordinators: %s | States: %s',
            number_format($summary['matched']),
            number_format($summary['active']),
            number_format($summary['inactive']),
            number_format($summary['with_coordinators']),
            number_format($summary['states']),
        ));
        $sheet->mergeCells('A3:R3');
        $sheet->setCellValueExplicit('A4', 'Filters: '.implode(' | ', $filterLabels), DataType::TYPE_STRING);
        $sheet->mergeCells('A4:R4');

        $headers = [
            'School ID', 'Source SchId', 'School Code', 'Category', 'School Name', 'Address', 'State', 'District',
            'City', 'PIN Code', 'Email', 'Mobile', 'Head Contact', 'Status',
            'Coordinator Count', 'Coordinators', 'Added On', 'Updated On',
        ];
        $sheet->fromArray($headers, null, 'A6');

        $rowNumber = 7;
        foreach ($rows as $row) {
            $values = [
                $row['id'],
                $row['external_school_id'] ?: '-',
                $row['school_code'],
                $row['category'] ?: '-',
                $row['name'],
                $row['address'] ?: '-',
                $row['state'] ?: '-',
                $row['district'] ?: '-',
                $row['city'] ?: '-',
                $row['pin_code'] ?: '-',
                $row['email'] ?: '-',
                $row['mobile'] ?: '-',
                $row['head_phone'] ?: '-',
                $row['is_active'] ? 'Active' : 'Inactive',
                $row['coordinators_count'],
                $this->coordinatorSummary($row),
                $this->date($row['created_at']),
                $this->date($row['updated_at']),
            ];

            foreach ($values as $columnIndex => $value) {
                $cell = $sheet->getCell([$columnIndex + 1, $rowNumber]);
                if (in_array($columnIndex, [0, 14], true)) {
                    $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
                } else {
                    $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                }
            }

            $rowNumber++;
        }

        $this->styleHeader($sheet, 'A1:R1', 'A6:R6');
        $sheet->getStyle('A1:R'.$rowNumber)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('F7:F'.$rowNumber)->getAlignment()->setWrapText(true);
        $sheet->getStyle('P7:P'.$rowNumber)->getAlignment()->setWrapText(true);
        $sheet->freezePane('A7');
        $sheet->setAutoFilter('A6:R'.max(6, $rowNumber - 1));

        $widths = [10, 14, 16, 12, 30, 42, 20, 20, 18, 12, 30, 18, 18, 12, 16, 48, 16, 16];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }
    }

    private function buildCoordinatorsSheet(Spreadsheet $spreadsheet, Collection $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Coordinators');
        $headers = ['School ID', 'School Code', 'School Name', 'Name', 'Designation', 'Email', 'Phone'];
        $sheet->fromArray($headers, null, 'A1');

        $rowNumber = 2;
        foreach ($rows as $row) {
            foreach ($row['coordinators'] as $coordinator) {
                $values = [
                    $row['id'],
                    $row['school_code'],
                    $row['name'],
                    $coordinator['name'],
                    $coordinator['designation'] ?: '-',
                    $coordinator['email'] ?: '-',
                    $coordinator['phone'] ?: '-',
                ];

                foreach ($values as $columnIndex => $value) {
                    $cell = $sheet->getCell([$columnIndex + 1, $rowNumber]);
                    if ($columnIndex === 0) {
                        $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
                    } else {
                        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                    }
                }

                $rowNumber++;
            }
        }

        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEE6A2C');
        $sheet->getStyle('A1:G'.$rowNumber)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G'.max(1, $rowNumber - 1));

        foreach ([10, 16, 30, 24, 22, 30, 18] as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }
    }

    private function styleHeader($sheet, string $titleRange, string $headerRange): void
    {
        $sheet->getStyle($titleRange)->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($titleRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0A1024');
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEE6A2C');
    }

    private function coordinatorSummary(array $row): string
    {
        $summary = collect($row['coordinators'])->map(function (array $coordinator): string {
            $parts = array_filter([
                $coordinator['designation'] ? "{$coordinator['name']} ({$coordinator['designation']})" : $coordinator['name'],
                $coordinator['phone'],
                $coordinator['email'],
            ]);

            return implode(' | ', $parts);
        })->implode("\n");

        return $summary !== '' ? $summary : '-';
    }

    private function date(?string $value): string
    {
        return $value ? date('d M Y', strtotime($value)) : '-';
    }

    private function filename(): string
    {
        return 'school-management-'.now()->format('Y-m-d-His').'.xlsx';
    }
}
