<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolDataEntryBulkUpdateRequest;
use App\Models\SchoolDesignation;
use App\Services\SchoolDataEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataEntryController extends Controller
{
    public function index(Request $request, SchoolDataEntryService $dataEntry): Response
    {
        $filters = $dataEntry->filters($request->query());

        return Inertia::render('Admin/DataEntry/Index', [
            'initialRows' => $dataEntry->paginate($filters),
            'filters' => $filters,
            'summary' => $dataEntry->summary(),
            'states' => $dataEntry->states(),
            'categories' => $dataEntry->categories(),
            'queues' => $dataEntry->queueOptions(),
            'schoolDesignations' => SchoolDesignation::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (SchoolDesignation $designation): array => [
                    'id' => $designation->id,
                    'name' => $designation->name,
                ])
                ->all(),
        ]);
    }

    public function rows(Request $request, SchoolDataEntryService $dataEntry): JsonResponse
    {
        $filters = $dataEntry->filters($request->query());

        return response()->json([
            'success' => true,
            'rows' => $dataEntry->paginate($filters),
            'filters' => $filters,
            'summary' => $dataEntry->summary(),
        ]);
    }

    public function updateRows(
        SchoolDataEntryBulkUpdateRequest $request,
        SchoolDataEntryService $dataEntry,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'rows' => $dataEntry->updateRows($request->rows()),
            'summary' => $dataEntry->summary(),
            'message' => count($request->rows()).' school row(s) saved.',
        ]);
    }
}
