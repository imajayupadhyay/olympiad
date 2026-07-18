<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentReportRequest;
use App\Services\StudentReportExportService;
use App\Services\StudentReportService;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(StudentReportRequest $request, StudentReportService $reports): Response
    {
        $filters = $request->filters();
        $students = $reports->queryWithReportData($filters)
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn ($student) => $reports->row($student));

        return Inertia::render('Admin/Reports/Index', array_merge($reports->metadata(), [
            'students' => $students,
            'filters' => $filters,
            'summary' => $reports->summary($filters),
            'exportLimits' => [
                'excel' => StudentReportExportService::MAX_EXCEL_ROWS,
                'pdf' => StudentReportExportService::MAX_PDF_ROWS,
            ],
        ]));
    }

    public function excel(
        StudentReportRequest $request,
        StudentReportService $reports,
        StudentReportExportService $exports,
    ): StreamedResponse {
        $filters = $request->filters();
        $rows = $this->exportRows($reports, $filters, StudentReportExportService::MAX_EXCEL_ROWS);

        return $exports->excel($rows, $filters, $reports->summary($filters), $reports->filterLabels($filters));
    }

    public function pdf(
        StudentReportRequest $request,
        StudentReportService $reports,
        StudentReportExportService $exports,
    ): HttpResponse {
        $filters = $request->filters();
        $rows = $this->exportRows($reports, $filters, StudentReportExportService::MAX_PDF_ROWS);

        return $exports->pdf($rows, $reports->summary($filters), $reports->filterLabels($filters));
    }

    private function exportRows(StudentReportService $reports, array $filters, int $limit)
    {
        abort_if($reports->query($filters)->count() > $limit, 422, "This export is limited to {$limit} students. Apply more filters and try again.");

        return $reports->queryWithReportData($filters)->get()->map(fn ($student) => $reports->row($student));
    }
}
