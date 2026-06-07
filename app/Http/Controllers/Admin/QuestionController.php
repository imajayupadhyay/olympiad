<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class QuestionController extends Controller
{
    private function meta(): array
    {
        return [
            'subjects'     => Subject::active(),
            'categories'   => $this->categoryOptions(),
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
            'question_category_id' => ['nullable', 'exists:question_categories,id'],
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
        $query = Question::with([
            'subject:id,name,slug,icon,color',
            'classLevel:id,level,label',
            'questionCategory:id,subject_id,parent_id,name,slug',
        ])
            ->latest();

        if ($request->filled('subject_id'))     $query->where('subject_id', $request->subject_id);
        if ($request->filled('class_level_id')) $query->where('class_level_id', $request->class_level_id);
        if ($request->filled('question_category_id')) {
            $category = QuestionCategory::find($request->question_category_id);
            $query->whereIn('question_category_id', $category ? $category->idsWithDescendants() : []);
        }
        if ($request->filled('difficulty'))     $query->where('difficulty', $request->difficulty);
        if ($request->filled('question_type'))  $query->where('question_type', $request->question_type);
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('question_text', 'like', '%'.$request->search.'%')
                    ->orWhere('topic', 'like', '%'.$request->search.'%');
            });
        }

        $questions = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Questions/Index', [
            ...$this->meta(),
            'questions' => $questions,
            'filters'   => $request->only(['subject_id', 'class_level_id', 'question_category_id', 'difficulty', 'question_type', 'search']),
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
        $this->ensureCategoryMatchesSubject($data['subject_id'], $data['question_category_id'] ?? null);

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
        $question->load('questionCategory');

        return Inertia::render('Admin/Questions/Edit', [
            ...$this->meta(),
            'question' => $question,
        ]);
    }

    public function update(Request $request, Question $question)
    {
        $data = $request->validate($this->validationRules(true));
        $this->ensureCategoryMatchesSubject($data['subject_id'], $data['question_category_id'] ?? null);

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

    private function ensureCategoryMatchesSubject(int $subjectId, ?int $categoryId): void
    {
        if (! $categoryId) {
            return;
        }

        $categoryMatches = QuestionCategory::where('id', $categoryId)
            ->where('subject_id', $subjectId)
            ->exists();

        if (! $categoryMatches) {
            throw ValidationException::withMessages([
                'question_category_id' => 'Category must belong to the selected subject.',
            ]);
        }
    }

    private function categoryOptions(): array
    {
        $categories = QuestionCategory::with('subject:id,name,icon,color,sort_order')
            ->orderBy('subject_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $byId = $categories->keyBy('id');

        return $categories->map(function (QuestionCategory $category) use ($byId) {
            $path = [$category->name];
            $parentId = $category->parent_id;
            $seen = [$category->id => true];

            while ($parentId && $byId->has($parentId) && ! isset($seen[$parentId])) {
                $parent = $byId->get($parentId);
                array_unshift($path, $parent->name);
                $seen[$parent->id] = true;
                $parentId = $parent->parent_id;
            }

            return [
                'id' => $category->id,
                'subject_id' => $category->subject_id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'path' => implode(' / ', $path),
                'depth' => max(count($path) - 1, 0),
                'is_active' => $category->is_active,
                'subject' => $category->subject,
            ];
        })->sortBy([
            ['subject.sort_order', 'asc'],
            ['path', 'asc'],
        ])->values()->all();
    }
}
