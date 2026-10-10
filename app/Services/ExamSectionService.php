<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Sections are the ordered blocks of an exam paper (Reasoning, Mathematics, English...).
 * Each section draws from one question-bank subject and owns an ordered list of questions;
 * question order across the whole paper is stored as a global exam_questions.sort_order.
 */
class ExamSectionService
{
    public const MAX_SECTIONS = 30;

    /**
     * Read and validate the submitted paper structure.
     *
     * Older clients post a flat `question_ids` list; that is treated as one "General" section
     * and keeps the original subject/class/category rules and `question_ids` error key.
     *
     * @return array<int, array{id: ?int, name: string, subject_id: int, instructions: ?string, marks_per_question: ?float, negative_marks_per_question: ?float, question_ids: array<int, int>}>
     */
    public function fromRequest(Request $request, array $examData, ?Exam $exam = null): array
    {
        $legacy = ! $request->has('sections');
        $sections = $legacy
            ? $this->legacySections($request, $examData, $exam)
            : $this->validatedSections($request, $exam);

        $this->ensureQuestionsFit($sections, $examData, $legacy);

        return $sections;
    }

    /**
     * The exam's saved structure in the same normalized shape fromRequest() returns.
     */
    public function currentSections(Exam $exam): array
    {
        $exam->load('sections', 'questions');
        $bySection = $exam->questions->groupBy(fn (Question $question) => $question->pivot->exam_section_id ?? 0);

        return $exam->sections->map(fn (ExamSection $section) => [
            'id' => $section->id,
            'name' => $section->name,
            'subject_id' => $section->subject_id,
            'instructions' => $section->instructions,
            'marks_per_question' => $section->marks_per_question !== null ? (float) $section->marks_per_question : null,
            'negative_marks_per_question' => $section->negative_marks_per_question !== null ? (float) $section->negative_marks_per_question : null,
            'question_ids' => $bySection->get($section->id, collect())->pluck('id')->all(),
        ])->all();
    }

    /**
     * Validate a proposed structure for an existing exam (used by imports).
     */
    public function assertFits(Exam $exam, array $sections): void
    {
        $this->ensureQuestionsFit($sections, $exam->only(['subject_id', 'class_level_id', 'question_category_id']), false);
    }

    public function sync(Exam $exam, array $sections): void
    {
        $keptIds = [];
        $sync = [];
        $position = 0;
        $questions = Question::whereIn('id', collect($sections)->pluck('question_ids')->flatten()->all())
            ->get(['id', 'marks', 'negative_marks'])
            ->keyBy('id');

        foreach (array_values($sections) as $index => $data) {
            $attributes = [
                'subject_id' => $data['subject_id'],
                'name' => $data['name'],
                'instructions' => $data['instructions'],
                'sort_order' => $index + 1,
                'marks_per_question' => $data['marks_per_question'],
                'negative_marks_per_question' => $data['negative_marks_per_question'],
            ];
            $section = $data['id']
                ? tap($exam->sections()->findOrFail($data['id']))->update($attributes)
                : $exam->sections()->create($attributes);
            $keptIds[] = $section->id;

            foreach ($data['question_ids'] as $questionId) {
                $sync[$questionId] = [
                    'exam_section_id' => $section->id,
                    'sort_order' => ++$position,
                    ...$this->pivotMarks($exam, $section, $questions->get($questionId)),
                ];
            }
        }

        $exam->sections()->whereNotIn('id', $keptIds)->delete();
        $exam->questions()->sync($sync);
    }

    public function duplicate(Exam $source, Exam $copy): void
    {
        $source->loadMissing('sections', 'questions');
        $sectionMap = [];

        foreach ($source->sections as $section) {
            $sectionMap[$section->id] = $copy->sections()->create($section->only([
                'subject_id', 'name', 'instructions', 'sort_order', 'marks_per_question', 'negative_marks_per_question',
            ]))->id;
        }

        $copy->questions()->sync($source->questions->mapWithKeys(fn (Question $question) => [
            $question->id => [
                'exam_section_id' => $sectionMap[$question->pivot->exam_section_id] ?? null,
                'sort_order' => $question->pivot->sort_order,
                'marks' => $question->pivot->marks,
                'negative_marks' => $question->pivot->negative_marks,
            ],
        ])->all());
    }

    /**
     * Problems that block publishing: an empty paper or an empty section.
     */
    public function publishErrors(array $sections, bool $legacy = false): array
    {
        if (collect($sections)->pluck('question_ids')->flatten()->isEmpty()) {
            return [$legacy ? 'question_ids' : 'sections' => 'Assign at least one question before publishing.'];
        }

        return collect($sections)
            ->filter(fn (array $section) => $section['question_ids'] === [])
            ->mapWithKeys(fn (array $section, int $index) => [
                "sections.{$index}.question_ids" => "Section \"{$section['name']}\" has no questions. Add questions or remove the section before publishing.",
            ])
            ->all();
    }

    /**
     * Sections with their ordered questions, shaped for the exam builder.
     */
    public function builderPayload(Exam $exam): array
    {
        $exam->loadMissing([
            'sections',
            'questions.subject:id,name',
            'questions.classLevels:id,label',
            'questions.questionCategory:id,name',
        ]);
        $bySection = $exam->questions->groupBy(fn (Question $question) => $question->pivot->exam_section_id ?? 0);

        $sections = $exam->sections->map(fn (ExamSection $section) => [
            'id' => $section->id,
            'name' => $section->name,
            'subject_id' => $section->subject_id,
            'instructions' => $section->instructions,
            'marks_per_question' => $section->marks_per_question,
            'negative_marks_per_question' => $section->negative_marks_per_question,
            'questions' => $bySection->get($section->id, collect())->map(fn (Question $question) => $this->questionPayload($question))->values(),
        ]);

        // Questions without a section (should not exist after the sections migration) are kept
        // visible instead of being silently dropped on the next save.
        if ($bySection->has(0)) {
            $sections->push([
                'id' => null,
                'name' => 'General',
                'subject_id' => $exam->subject_id,
                'instructions' => null,
                'marks_per_question' => null,
                'negative_marks_per_question' => null,
                'questions' => $bySection->get(0)->map(fn (Question $question) => $this->questionPayload($question))->values(),
            ]);
        }

        return $sections->values()->all();
    }

    public function questionPayload(Question $question): array
    {
        return [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'question_image_url' => $question->question_image_url,
            'option_a' => $question->option_a,
            'option_b' => $question->option_b,
            'option_c' => $question->option_c,
            'option_d' => $question->option_d,
            'correct_options' => $question->correct_options,
            'difficulty' => $question->difficulty,
            'question_type' => $question->question_type,
            'marks' => (float) $question->marks,
            'negative_marks' => (float) $question->negative_marks,
            'is_active' => (bool) $question->is_active,
            'subject_id' => $question->subject_id,
            'subject' => $question->subject?->only(['id', 'name']),
            'question_category' => $question->questionCategory?->only(['id', 'name']),
            'class_levels' => $question->classLevels->map(fn ($level) => $level->only(['id', 'label']))->values(),
        ];
    }

    private function validatedSections(Request $request, ?Exam $exam): array
    {
        $data = Validator::make($request->only('sections'), [
            'sections' => ['array', 'max:'.self::MAX_SECTIONS],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.name' => ['required', 'string', 'max:120'],
            'sections.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'sections.*.instructions' => ['nullable', 'string', 'max:5000'],
            'sections.*.marks_per_question' => ['nullable', 'numeric', 'min:0.25', 'max:100'],
            'sections.*.negative_marks_per_question' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sections.*.question_ids' => ['nullable', 'array'],
            'sections.*.question_ids.*' => ['integer'],
        ], [], [
            'sections.*.name' => 'section name',
            'sections.*.subject_id' => 'section subject',
        ])->validate();

        $existingIds = $exam ? $exam->sections()->pluck('id')->all() : [];

        $sections = collect($data['sections'] ?? [])->values()->map(fn (array $section) => [
            'id' => in_array((int) ($section['id'] ?? 0), $existingIds, true) ? (int) $section['id'] : null,
            'name' => trim($section['name']),
            'subject_id' => (int) $section['subject_id'],
            'instructions' => filled($section['instructions'] ?? null) ? $section['instructions'] : null,
            'marks_per_question' => filled($section['marks_per_question'] ?? null) ? (float) $section['marks_per_question'] : null,
            'negative_marks_per_question' => filled($section['negative_marks_per_question'] ?? null) ? (float) $section['negative_marks_per_question'] : null,
            'question_ids' => collect($section['question_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all(),
        ])->all();

        $errors = [];
        foreach ($sections as $index => $section) {
            if ($section['marks_per_question'] !== null && $section['negative_marks_per_question'] > $section['marks_per_question']) {
                $errors["sections.{$index}.negative_marks_per_question"] = 'Negative marks cannot exceed the marks for a correct answer.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $sections;
    }

    private function legacySections(Request $request, array $examData, ?Exam $exam): array
    {
        $request->validate([
            'question_ids' => ['nullable', 'array'],
            'question_ids.*' => ['integer', 'distinct', 'exists:questions,id'],
        ]);

        $ids = collect($request->input('question_ids', []))
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return [[
            'id' => $exam?->sections()->value('id'),
            'name' => 'General',
            'subject_id' => (int) $examData['subject_id'],
            'instructions' => null,
            'marks_per_question' => null,
            'negative_marks_per_question' => null,
            'question_ids' => $ids,
        ]];
    }

    /**
     * Every question must be active, offered to the exam's class and from its section's subject.
     * The exam's category scope narrows sections that draw from the exam's own subject.
     */
    private function ensureQuestionsFit(array $sections, array $examData, bool $legacy): void
    {
        $allIds = collect($sections)->pluck('question_ids')->flatten();
        if ($allIds->isEmpty()) {
            return;
        }

        $errors = [];
        $seen = [];
        foreach ($sections as $index => $section) {
            foreach ($section['question_ids'] as $questionId) {
                if (isset($seen[$questionId])) {
                    $errors["sections.{$index}.question_ids"] = 'A question can appear only once in an exam. Remove the duplicate.';
                }
                $seen[$questionId] = true;
            }
        }

        $questions = Question::whereIn('id', $allIds->unique())
            ->where('is_active', true)
            ->whereHas('classLevels', fn ($query) => $query->where('class_levels.id', $examData['class_level_id']))
            ->get(['id', 'subject_id', 'question_category_id'])
            ->keyBy('id');
        $categoryIds = $this->categoryScope($examData['question_category_id'] ?? null);

        foreach ($sections as $index => $section) {
            $invalid = collect($section['question_ids'])->reject(function (int $questionId) use ($questions, $section, $examData, $categoryIds) {
                $question = $questions->get($questionId);

                return $question
                    && (int) $question->subject_id === $section['subject_id']
                    && ($categoryIds === null
                        || $section['subject_id'] !== (int) $examData['subject_id']
                        || $categoryIds->contains($question->question_category_id));
            });

            if ($invalid->isNotEmpty()) {
                $errors["sections.{$index}.question_ids"] = $invalid->count() === 1
                    ? '1 question in this section is inactive, not offered to this class, or from another subject or category.'
                    : "{$invalid->count()} questions in this section are inactive, not offered to this class, or from another subject or category.";
            }
        }

        if ($errors === []) {
            return;
        }

        throw ValidationException::withMessages($legacy
            ? ['question_ids' => 'Only active questions from this exam subject, class, and category can be assigned.']
            : $errors);
    }

    private function categoryScope(mixed $categoryId): ?Collection
    {
        if (! $categoryId) {
            return null;
        }

        $category = QuestionCategory::find($categoryId);

        return collect($category ? $category->idsWithDescendants() : []);
    }

    /**
     * Section override first, then the exam-wide policy, then the question's own marks.
     * The exam's negative-marking switch is the master toggle for every section.
     */
    private function pivotMarks(Exam $exam, ExamSection $section, ?Question $question): array
    {
        $uniform = $exam->scoring_mode === 'uniform';
        $marks = $section->marks_per_question
            ?? ($uniform ? $exam->marks_per_question : $question?->marks);
        $negative = $exam->negative_marking_enabled
            ? ($section->negative_marks_per_question ?? ($uniform ? $exam->negative_marks_per_question : $question?->negative_marks))
            : 0;

        return ['marks' => $marks ?? 1, 'negative_marks' => $negative ?? 0];
    }
}
