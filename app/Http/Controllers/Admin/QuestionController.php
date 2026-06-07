<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class QuestionController extends Controller
{
    private function meta(): array
    {
        return [
            'subjects'     => Subject::active(),
            'classLevels'  => ClassLevel::active(),
            'difficulties' => Question::difficulties(),
            'types'        => Question::types(),
        ];
    }

    private function validationRules(bool $isUpdate = false): array
    {
        return [
            'subject_id'      => ['required', 'exists:subjects,id'],
            'class_level_id'  => ['required', 'exists:class_levels,id'],
            'difficulty'      => ['required', 'in:easy,medium,hard'],
            'question_type'   => ['required', 'in:single,multiple'],
            'topic'           => ['nullable', 'string', 'max:100'],
            'question_text'   => ['required', 'string'],
            'option_a'        => ['required', 'string'],
            'option_b'        => ['required', 'string'],
            'option_c'        => ['required', 'string'],
            'option_d'        => ['required', 'string'],
            'correct_options' => ['required', 'array', 'min:1'],
            'correct_options.*' => ['in:a,b,c,d'],
            'explanation'     => ['nullable', 'string'],
            'marks'           => ['required', 'integer', 'min:1', 'max:10'],
            'negative_marks'  => ['required', 'numeric', 'min:0', 'max:5'],
            'question_image'  => $isUpdate
                ? ['nullable', 'image', 'max:2048']
                : ['nullable', 'image', 'max:2048'],
            'is_active'       => ['boolean'],
        ];
    }

    public function index(Request $request)
    {
        $query = Question::with(['subject:id,name,slug,icon,color', 'classLevel:id,level,label'])
            ->latest();

        if ($request->filled('subject_id'))     $query->where('subject_id', $request->subject_id);
        if ($request->filled('class_level_id')) $query->where('class_level_id', $request->class_level_id);
        if ($request->filled('difficulty'))     $query->where('difficulty', $request->difficulty);
        if ($request->filled('question_type'))  $query->where('question_type', $request->question_type);
        if ($request->filled('search'))         $query->where('question_text', 'like', '%'.$request->search.'%');

        $questions = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Questions/Index', [
            ...$this->meta(),
            'questions' => $questions,
            'filters'   => $request->only(['subject_id', 'class_level_id', 'difficulty', 'question_type', 'search']),
            'total'     => Question::count(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Questions/Create', $this->meta());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());

        if ($request->hasFile('question_image')) {
            $data['question_image'] = $request->file('question_image')
                ->store('questions', 'public');
        }

        $data['created_by'] = Auth::id();
        Question::create($data);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Question added successfully.');
    }

    public function edit(Question $question)
    {
        return Inertia::render('Admin/Questions/Edit', [
            ...$this->meta(),
            'question' => $question,
        ]);
    }

    public function update(Request $request, Question $question)
    {
        $data = $request->validate($this->validationRules(true));

        if ($request->hasFile('question_image')) {
            if ($question->question_image) {
                Storage::disk('public')->delete($question->question_image);
            }
            $data['question_image'] = $request->file('question_image')
                ->store('questions', 'public');
        }

        // Allow clearing image
        if ($request->input('remove_image')) {
            if ($question->question_image) {
                Storage::disk('public')->delete($question->question_image);
            }
            $data['question_image'] = null;
        }

        $question->update($data);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Question updated.');
    }

    public function destroy(Question $question)
    {
        if ($question->question_image) {
            Storage::disk('public')->delete($question->question_image);
        }
        $question->delete();
        return back()->with('success', 'Question deleted.');
    }

    public function show(Question $question)
    {
        return redirect()->route('admin.questions.edit', $question);
    }
}
