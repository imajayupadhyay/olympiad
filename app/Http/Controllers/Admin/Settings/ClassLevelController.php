<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassLevelController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings/Classes/Index', [
            'classes' => ClassLevel::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'min:1', 'max:20', 'unique:class_levels,level'],
            'label' => ['required', 'string', 'max:50'],
        ]);

        $data['sort_order'] = $data['level'];
        ClassLevel::create($data);

        return back()->with('success', "\"{$data['label']}\" added.");
    }

    public function update(Request $request, ClassLevel $classLevel)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $classLevel->update($data);

        return back()->with('success', 'Class level updated.');
    }

    public function destroy(ClassLevel $classLevel)
    {
        if ($classLevel->questions()->count() > 0) {
            return back()->with('error', "Cannot delete \"{$classLevel->label}\" — it has questions linked to it.");
        }

        if ($classLevel->exams()->count() > 0) {
            return back()->with('error', "Cannot delete \"{$classLevel->label}\" — it has exams linked to it.");
        }

        $classLevel->delete();

        return back()->with('success', 'Class level deleted.');
    }
}
