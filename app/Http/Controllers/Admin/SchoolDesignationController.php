<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolDesignation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolDesignationController extends Controller
{
    public function index(): Response
    {
        $designations = SchoolDesignation::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/SchoolDesignations/Index', [
            'designations' => $designations,
            'summary' => [
                'total' => $designations->count(),
                'active' => $designations->where('is_active', true)->count(),
                'inactive' => $designations->where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['sort_order'] = SchoolDesignation::max('sort_order') + 1;

        SchoolDesignation::create($data);

        return back()->with('success', "Designation \"{$data['name']}\" added.");
    }

    public function update(Request $request, SchoolDesignation $schoolDesignation)
    {
        $data = $this->validatedData($request, $schoolDesignation);
        $schoolDesignation->update($data);

        return back()->with('success', 'Designation updated.');
    }

    public function destroy(SchoolDesignation $schoolDesignation)
    {
        $schoolDesignation->delete();

        return back()->with('success', 'Designation removed.');
    }

    private function validatedData(Request $request, ?SchoolDesignation $schoolDesignation = null): array
    {
        $request->merge([
            'name' => is_scalar($request->input('name')) ? trim((string) $request->input('name')) : null,
        ]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('school_designations', 'name')->ignore($schoolDesignation?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
