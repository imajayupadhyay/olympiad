<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\StudentCustomField;
use App\Services\StudentCustomFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin-defined student fields (Settings → Hall Ticket Layout → Student fields). */
class StudentCustomFieldController extends Controller
{
    public function __construct(private StudentCustomFieldService $fields) {}

    public function store(Request $request): RedirectResponse
    {
        if (StudentCustomField::count() >= StudentCustomFieldService::MAX_FIELDS) {
            return back()->with('error', 'You can have up to '.StudentCustomFieldService::MAX_FIELDS.' student fields. Delete or reuse one first.');
        }

        $field = $this->fields->create($request->validate($this->fields->definitionRules()));

        return back()->with('success', "\"{$field->label}\" added. Students now see it on their profile; switch it on under Ticket fields to print it.");
    }

    public function update(Request $request, StudentCustomField $field): RedirectResponse
    {
        $this->fields->update($field, $request->validate($this->fields->definitionRules($field)));

        return back()->with('success', "\"{$field->label}\" updated.");
    }

    public function destroy(StudentCustomField $field): RedirectResponse
    {
        $label = $field->label;
        $this->fields->delete($field);

        return back()->with('success', "\"{$label}\" deleted along with the values students had entered.");
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:student_custom_fields,id']]);
        $this->fields->reorder($data['ids']);

        return back();
    }
}
