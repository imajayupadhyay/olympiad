<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExamController extends Controller
{
    private function meta(): array
    {
        return [
            'subjects' => Subject::active(),
            'classLevels' => ClassLevel::active(),
            'statuses' => Exam::statuses(),
            'scoringModes' => Exam::scoringModes(),
            'difficulties' => Question::difficulties(),
        ];
    }

    public function index(Request $request)
    {
        $query = Exam::with(['subject:id,name,slug,icon,color', 'classLevel:id,level,label'])
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

        if ($request->filled('class_level_id')) {
            $query->where('class_level_id', $request->input('class_level_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return Inertia::render('Admin/Exams/Index', [
            ...$this->meta(),
            'exams' => $query->paginate(12)->withQueryString(),
            'filters' => $request->only(['search', 'subject_id', 'class_level_id', 'status']),
            'stats' => [
                'total' => Exam::count(),
                'draft' => Exam::where('status', 'draft')->count(),
                'published' => Exam::where('status', 'published')->count(),
                'archived' => Exam::where('status', 'archived')->count(),
                'unassigned' => Exam::doesntHave('questions')->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Admin/Exams/Create', [
            ...$this->meta(),
            'availableQuestions' => $this->questionOptions($request),
            'questionFilters' => $this->questionFilters($request),
            'assignedQuestions' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedExamData($request);
        $questionIds = $this->validQuestionIds($request, $data['subject_id'], $data['class_level_id']);

        if ($data['status'] === 'published') {
            $this->ensurePublishReady($data, $questionIds);
        }

        DB::transaction(function () use ($data, $questionIds) {
            $data['slug'] = $this->uniqueSlug($data['name']);
            $data['exam_code'] = $this->generateExamCode();
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();
            $data['published_at'] = $data['status'] === 'published' ? now() : null;

            $exam = Exam::create($data);
            $this->syncQuestions($exam, $questionIds);
        });

        return redirect()->route('admin.exams.index')
            ->with('success', 'Exam created successfully.');
    }

    public function show(Exam $exam)
    {
        return redirect()->route('admin.exams.edit', $exam);
    }

    public function edit(Request $request, Exam $exam)
    {
        $exam->load(['subject:id,name,slug,icon,color', 'classLevel:id,level,label', 'questions.subject:id,name,slug,icon,color', 'questions.classLevel:id,level,label']);

        return Inertia::render('Admin/Exams/Edit', [
            ...$this->meta(),
            'exam' => $this->examPayload($exam),
            'availableQuestions' => $this->questionOptions($request, $exam),
            'questionFilters' => $this->questionFilters($request),
            'assignedQuestions' => $exam->questions->map(fn (Question $question) => $this->questionPayload($question))->values(),
        ]);
    }

    public function update(Request $request, Exam $exam)
    {
        $data = $this->validatedExamData($request);
        $questionIds = $this->validQuestionIds($request, $data['subject_id'], $data['class_level_id']);

        if ($data['status'] === 'published') {
            $this->ensurePublishReady($data, $questionIds);
        }

        DB::transaction(function () use ($exam, $data, $questionIds) {
            if ($exam->name !== $data['name']) {
                $data['slug'] = $this->uniqueSlug($data['name'], $exam->id);
            }

            $data['updated_by'] = Auth::id();
            $data['published_at'] = $data['status'] === 'published'
                ? ($exam->published_at ?? now())
                : null;

            $exam->update($data);
            $this->syncQuestions($exam->fresh(), $questionIds);
        });

        return redirect()->route('admin.exams.index')
            ->with('success', 'Exam updated.');
    }

    public function destroy(Exam $exam)
    {
        if ($exam->status === 'published') {
            return back()->with('error', 'Unpublish or archive this exam before deleting it.');
        }

        $exam->delete();

        return back()->with('success', 'Exam deleted.');
    }

    public function publish(Exam $exam)
    {
        $exam->loadCount('questions');

        if ($exam->questions_count === 0 || ! $exam->starts_at || ! $exam->duration_minutes) {
            return back()->with('error', 'Add questions, schedule, and duration before publishing this exam.');
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
            'question_ids' => ['nullable', 'array'],
            'question_ids.*' => ['integer', 'distinct', 'exists:questions,id'],
        ]);

        unset($data['question_ids']);

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

    private function validQuestionIds(Request $request, int $subjectId, int $classLevelId): Collection
    {
        $ids = collect($request->input('question_ids', []))
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $ids;
        }

        $validCount = Question::whereIn('id', $ids)
            ->where('is_active', true)
            ->where('subject_id', $subjectId)
            ->where('class_level_id', $classLevelId)
            ->count();

        if ($validCount !== $ids->count()) {
            throw ValidationException::withMessages([
                'question_ids' => 'Only active questions from this exam subject and class can be assigned.',
            ]);
        }

        return $ids;
    }

    private function ensurePublishReady(array $data, Collection $questionIds): void
    {
        $errors = [];

        if ($questionIds->isEmpty()) {
            $errors['question_ids'] = 'Assign at least one question before publishing.';
        }

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

    private function syncQuestions(Exam $exam, Collection $questionIds): void
    {
        if ($questionIds->isEmpty()) {
            $exam->questions()->sync([]);

            return;
        }

        $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

        $sync = [];
        foreach ($questionIds as $index => $questionId) {
            $question = $questions->get($questionId);
            $marks = $exam->scoring_mode === 'uniform'
                ? $exam->marks_per_question
                : $question->marks;
            $negativeMarks = $exam->negative_marking_enabled
                ? ($exam->scoring_mode === 'uniform' ? $exam->negative_marks_per_question : $question->negative_marks)
                : 0;

            $sync[$questionId] = [
                'sort_order' => $index + 1,
                'marks' => $marks,
                'negative_marks' => $negativeMarks,
            ];
        }

        $exam->questions()->sync($sync);
    }

    private function questionOptions(Request $request, ?Exam $exam = null)
    {
        $subjectId = $request->input('question_subject_id') ?: $exam?->subject_id;
        $classLevelId = $request->input('question_class_level_id') ?: $exam?->class_level_id;

        $query = Question::with(['subject:id,name,slug,icon,color', 'classLevel:id,level,label'])
            ->where('is_active', true)
            ->latest();

        if (! $subjectId || ! $classLevelId) {
            $query->whereRaw('0 = 1');
        } else {
            $query->where('subject_id', $subjectId)
                ->where('class_level_id', $classLevelId);
        }

        if ($request->filled('question_difficulty')) {
            $query->where('difficulty', $request->input('question_difficulty'));
        }

        if ($request->filled('question_search')) {
            $search = $request->string('question_search');
            $query->where(function ($q) use ($search) {
                $q->where('question_text', 'like', "%{$search}%")
                    ->orWhere('topic', 'like', "%{$search}%");
            });
        }

        return $query->paginate(8, ['*'], 'questions_page')->withQueryString();
    }

    private function questionFilters(Request $request): array
    {
        return [
            'search' => $request->input('question_search', ''),
            'difficulty' => $request->input('question_difficulty', ''),
        ];
    }

    private function examPayload(Exam $exam): array
    {
        return [
            'id' => $exam->id,
            'subject_id' => $exam->subject_id,
            'class_level_id' => $exam->class_level_id,
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
            'question_ids' => $exam->questions->pluck('id')->values(),
        ];
    }

    private function questionPayload(Question $question): array
    {
        return [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'question_image_url' => $question->question_image_url,
            'topic' => $question->topic,
            'difficulty' => $question->difficulty,
            'question_type' => $question->question_type,
            'marks' => $question->pivot->marks ?? $question->marks,
            'negative_marks' => $question->pivot->negative_marks ?? $question->negative_marks,
            'subject' => $question->subject,
            'class_level' => $question->classLevel,
        ];
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
            $code = 'NOH-'.now()->format('ym').'-'.strtoupper(Str::random(4));
        } while (Exam::where('exam_code', $code)->exists());

        return $code;
    }
}
