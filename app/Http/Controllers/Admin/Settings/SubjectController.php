<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings/Subjects/Index', [
            'subjects' => Subject::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:10'],
            'color' => ['required', 'string', 'max:7'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['sort_order'] = Subject::max('sort_order') + 1;

        Subject::create($data);

        return back()->with('success', "Subject \"{$data['name']}\" added.");
    }

    public function update(Request $request, Subject $subject)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:10'],
            'color' => ['required', 'string', 'max:7'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $subject->update($data);

        return back()->with('success', 'Subject updated.');
    }

    public function destroy(Subject $subject)
    {
        if ($subject->questions()->count() > 0) {
            return back()->with('error', "Cannot delete \"{$subject->name}\" — it has questions linked to it.");
        }

        if ($subject->exams()->count() > 0) {
            return back()->with('error', "Cannot delete \"{$subject->name}\" — it has exams linked to it.");
        }

        $subject->delete();

        return back()->with('success', 'Subject deleted.');
    }
}
