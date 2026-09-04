<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolIndexRequest;
use App\Http\Requests\Admin\SchoolRequest;
use App\Models\School;
use App\Services\SchoolExportService;
use App\Services\SchoolManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolController extends Controller
{
    public function index(SchoolIndexRequest $request, SchoolManagementService $schools): Response
    {
        $filters = $request->filters();

        $records = $schools->query($filters)
            ->with('coordinators')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (School $school) => $schools->row($school));

        return Inertia::render('Admin/Schools/Index', array_merge($schools->metadata(), [
            'schools' => $records,
            'filters' => $filters,
            'summary' => $schools->summary($filters),
            'exportLimits' => [
                'excel' => SchoolExportService::MAX_EXCEL_ROWS,
            ],
        ]));
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $schools = School::query()
            ->where('is_managed', false)
            ->search($term)
            ->limit(10)
            ->get(['id', 'name', 'address', 'category']);

        return response()->json(['success' => true, 'data' => $schools]);
    }

    public function store(SchoolRequest $request, SchoolManagementService $schools)
    {
        $schools->create(
            $request->schoolAttributes(),
            $request->coordinatorAttributes(),
            $request->sourceSchoolId(),
        );

        return back()->with('success', 'School added successfully.');
    }

    public function update(SchoolRequest $request, School $school, SchoolManagementService $schools)
    {
        $this->assertManaged($school);

        $schools->update($school, $request->schoolAttributes(), $request->coordinatorAttributes());

        return back()->with('success', 'School updated successfully.');
    }

    public function toggle(Request $request, School $school)
    {
        $this->assertManaged($school);

        $request->validate(['is_active' => ['required', 'boolean']]);
        $school->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', $request->boolean('is_active') ? 'School marked active.' : 'School marked inactive.');
    }

    public function destroy(School $school)
    {
        $this->assertManaged($school);
        $school->delete();

        return redirect()->route('admin.schools.index')->with('success', 'School deleted successfully.');
    }

    public function excel(
        SchoolIndexRequest $request,
        SchoolManagementService $schools,
        SchoolExportService $exports,
    ): StreamedResponse {
        $filters = $request->filters();
        $summary = $schools->summary($filters);

        abort_if($summary['matched'] > SchoolExportService::MAX_EXCEL_ROWS, 422, 'This export is limited to '.SchoolExportService::MAX_EXCEL_ROWS.' schools. Apply more filters and try again.');

        $rows = $schools->query($filters)
            ->with('coordinators')
            ->get()
            ->map(fn (School $school) => $schools->row($school));

        return $exports->excel($rows, $summary, $schools->filterLabels($filters));
    }

    private function assertManaged(School $school): void
    {
        abort_unless($school->is_managed, 404);
    }
}
