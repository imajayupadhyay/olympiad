<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Subject;
use App\Services\ExamSectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExamController extends Controller
{
    public function __construct(private ExamSectionService $sections)
    {
    }

    private function meta(): array
    {
        return [
            'subjects' => Subject::active(),
            'categories' => $this->categoryOptions(),
            'classLevels' => ClassLevel::active(),
            'statuses' => Exam::statuses(),
            'scoringModes' => Exam::scoringModes(),
            'difficulties' => Question::difficulties(),
            'types' => Question::types(),
        ];
    }

    public function index(Request $request)
    {
        $query = Exam::with([
            'subject:id,name,slug,icon,color',
            'classLevel:id,level,label',
            'questionCategory:id,subject_id,parent_id,name,slug',
        ])
            ->withCount('questions')
            ->latest('updated_at');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('exam_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }

        if ($request->filled('question_category_id')) {
            $category = QuestionCategory::find($request->input('question_category_id'));
            $query->whereIn('question_category_id', $category ? $category->idsWithDescendants() : []);
        }

        if ($request->filled('class_level_id')) {
            $query->where('class_level_id', $request->input('class_level_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return Inertia::render('Admin/Exams/Index', [
            ...$this->meta(),
            'exams' => $query->paginate(12)->withQueryString(),
            'filters' => $request->only(['search', 'subject_id', 'question_category_id', 'class_level_id', 'status']),
            'stats' => [
                'total' => Exam::count(),
                'draft' => Exam::where('status', 'draft')->count(),
                'published' => Exam::where('status', 'published')->count(),
                'archived' => Exam::where('status', 'archived')->count(),
                'unassigned' => Exam::doesntHave('questions')->count(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Exams/Create', $this->meta());
    }

    public function store(Request $request)
    {
        $data = $this->validatedExamData($request);
        $this->ensureCategoryMatchesSubject($data['subject_id'], $data['question_category_id'] ?? null);
        $sections = $this->sections->fromRequest($request, $data);

        if ($data['status'] === 'published') {
            $this->ensurePublishReady($request, $data, $sections);
        }

        $exam = DB::transaction(function () use ($data, $sections) {
            $data['slug'] = $this->uniqueSlug($data['name']);
            $data['exam_code'] = $this->generateExamCode();
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();
            $data['published_at'] = $data['status'] === 'published' ? now() : null;

            $exam = Exam::create($data);
            $this->sections->sync($exam, $sections);

            return $exam;
        });

        return redirect()->route('admin.exams.edit', ['exam' => $exam, 'step' => 'questions'])
            ->with('success', 'Exam created. Now add its sections and questions.');
    }

    public function show(Exam $exam)
    {
        return redirect()->route('admin.exams.edit', $exam);
    }

    public function edit(Exam $exam)
    {
        $exam->load('questionCategory:id,subject_id,parent_id,name,slug')->loadCount('attempts');

        return Inertia::render('Admin/Exams/Edit', [
            ...$this->meta(),
            'exam' => $this->examPayload($exam),
        ]);
    }

    public function update(Request $request, Exam $exam)
    {
        $data = $this->validatedExamData($request);
        $this->ensureCategoryMatchesSubject($data['subject_id'], $data['question_category_id'] ?? null);
        $sections = $this->sections->fromRequest($request, $data, $exam);

        if ($data['status'] === 'published') {
            $this->ensurePublishReady($request, $data, $sections);
        }

        DB::transaction(function () use ($exam, $data, $sections) {
            if ($exam->name !== $data['name']) {
                $data['slug'] = $this->uniqueSlug($data['name'], $exam->id);
            }

            $data['updated_by'] = Auth::id();
            $data['published_at'] = $data['status'] === 'published'
                ? ($exam->published_at ?? now())
                : null;

            $exam->update($data);
            $this->sections->sync($exam->fresh(), $sections);
        });

        return back()->with('success', $data['status'] === 'published' ? 'Exam saved and published.' : 'Exam saved.');
    }

    public function destroy(Exam $exam)
    {
        if ($exam->status === 'published') {
            return back()->with('error', 'Unpublish or archive this exam before deleting it.');
        }

        $exam->delete();

        return back()->with('success', 'Exam deleted.');
    }

    public function duplicate(Exam $exam)
    {
        $newExam = DB::transaction(function () use ($exam) {
            $data = [
                'subject_id' => $exam->subject_id,
                'class_level_id' => $exam->class_level_id,
                'question_category_id' => $exam->question_category_id,
                'description' => $exam->description,
                'syllabus' => $exam->syllabus,
                'eligibility' => $exam->eligibility,
                'instructions' => $exam->instructions,
                'starts_at' => $exam->starts_at,
                'ends_at' => $exam->ends_at,
                'duration_minutes' => $exam->duration_minutes,
                'fee_amount' => $exam->fee_amount,
                'fee_currency' => $exam->fee_currency,
                'scoring_mode' => $exam->scoring_mode,
                'marks_per_question' => $exam->marks_per_question,
                'negative_marking_enabled' => $exam->negative_marking_enabled,
                'negative_marks_per_question' => $exam->negative_marks_per_question,
                'randomize_questions' => $exam->randomize_questions,
                'randomize_options' => $exam->randomize_options,
                'show_result_immediately' => $exam->show_result_immediately,
                'name' => $exam->name.' (Copy)',
                'status' => 'draft',
                'published_at' => null,
                'result_release_at' => null,
            ];
            $data['slug'] = $this->uniqueSlug($data['name']);
            $data['exam_code'] = $this->generateExamCode();
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            $newExam = Exam::create($data);
            $this->sections->duplicate($exam, $newExam);

            return $newExam;
        });

        return redirect()->route('admin.exams.edit', $newExam)
            ->with('success', 'Exam duplicated — review and save your changes.');
    }

    public function publish(Exam $exam)
    {
        $exam->loadCount('questions');

        if ($exam->questions_count === 0 || ! $exam->starts_at || ! $exam->duration_minutes) {
            return back()->with('error', 'Add questions, schedule, and duration before publishing this exam.');
        }

        if ($exam->sections()->doesntHave('questions')->exists()) {
            return back()->with('error', 'Every section needs at least one question before publishing. Open the exam to fill or remove empty sections.');
        }

        $exam->update([
            'status' => 'published',
            'published_at' => $exam->published_at ?? now(),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Exam published.');
    }

    public function unpublish(Exam $exam)
    {
        $exam->update([
            'status' => 'draft',
            'published_at' => null,
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Exam moved back to draft.');
    }

    public function archive(Exam $exam)
    {
        $exam->update([
            'status' => 'archived',
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Exam archived.');
    }

    private function validatedExamData(Request $request): array
    {
        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'class_level_id' => ['required', 'exists:class_levels,id'],
            'question_category_id' => ['nullable', 'exists:question_categories,id'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1500'],
            'syllabus' => ['nullable', 'string'],
            'eligibility' => ['nullable', 'string', 'max:1500'],
            'instructions' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:360'],
            'fee_amount' => ['required', 'numeric', 'min:0', 'max:999999'],
            'fee_currency' => ['required', 'string', 'size:3'],
            'scoring_mode' => ['required', Rule::in(array_keys(Exam::scoringModes()))],
            'marks_per_question' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'negative_marking_enabled' => ['boolean'],
            'negative_marks_per_question' => ['required', 'numeric', 'min:0', 'lte:marks_per_question'],
            'randomize_questions' => ['boolean'],
            'randomize_options' => ['boolean'],
            'show_result_immediately' => ['boolean'],
            'result_release_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(array_keys(Exam::statuses()))],
        ]);

        $data['fee_currency'] = strtoupper($data['fee_currency']);
        $data['negative_marking_enabled'] = $request->boolean('negative_marking_enabled');
        $data['negative_marks_per_question'] = $data['negative_marking_enabled']
            ? $data['negative_marks_per_question']
            : 0;
        $data['randomize_questions'] = $request->boolean('randomize_questions');
        $data['randomize_options'] = $request->boolean('randomize_options');
        $data['show_result_immediately'] = $request->boolean('show_result_immediately');

        return $data;
    }

    private function ensurePublishReady(Request $request, array $data, array $sections): void
    {
        $errors = $this->sections->publishErrors($sections, ! $request->has('sections'));

        if (blank($data['starts_at'] ?? null)) {
            $errors['starts_at'] = 'Set the exam start date and time before publishing.';
        }

        if (blank($data['duration_minutes'] ?? null)) {
            $errors['duration_minutes'] = 'Set the exam duration before publishing.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function examPayload(Exam $exam): array
    {
        return [
            'id' => $exam->id,
            'subject_id' => $exam->subject_id,
            'class_level_id' => $exam->class_level_id,
            'question_category_id' => $exam->question_category_id,
            'name' => $exam->name,
            'exam_code' => $exam->exam_code,
            'description' => $exam->description,
            'syllabus' => $exam->syllabus,
            'eligibility' => $exam->eligibility,
            'instructions' => $exam->instructions,
            'starts_at' => $this->dateForInput($exam->starts_at),
            'ends_at' => $this->dateForInput($exam->ends_at),
            'duration_minutes' => $exam->duration_minutes,
            'fee_amount' => $exam->fee_amount,
            'fee_currency' => $exam->fee_currency,
            'scoring_mode' => $exam->scoring_mode,
            'marks_per_question' => $exam->marks_per_question,
            'negative_marking_enabled' => $exam->negative_marking_enabled,
            'negative_marks_per_question' => $exam->negative_marks_per_question,
            'randomize_questions' => $exam->randomize_questions,
            'randomize_options' => $exam->randomize_options,
            'show_result_immediately' => $exam->show_result_immediately,
            'result_release_at' => $this->dateForInput($exam->result_release_at),
            'status' => $exam->status,
            'question_category' => $exam->questionCategory,
            'attempts_count' => $exam->attempts_count ?? 0,
            'sections' => $this->sections->builderPayload($exam),
        ];
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

    private function dateForInput($date): ?string
    {
        return $date?->format('Y-m-d\TH:i');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'exam';
        $slug = $base;
        $suffix = 2;

        while (Exam::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function generateExamCode(): string
    {
        do {
            $code = 'NEO-'.now()->format('ym').'-'.strtoupper(Str::random(4));
        } while (Exam::where('exam_code', $code)->exists());

        return $code;
    }
}
