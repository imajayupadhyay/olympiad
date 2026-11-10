<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * The exam-room paper: questions in paper order (shuffled per seed when the exam says so)
 * with answer keys and explanations stripped. Shared by the student exam room and the
 * admin preview so both render exactly the same paper.
 */
class ExamPaperService
{
    /**
     * Load what the exam room needs onto the exam.
     */
    public function load(Exam $exam): Exam
    {
        return $exam->loadMissing(['questions', 'sections:id,exam_id,name,instructions,sort_order']);
    }

    /**
     * Sanitized question payload. NEVER includes correct_options or explanation.
     *
     * @param  string  $seed  Shuffle seed — the attempt id for students, a random token for previews.
     * @param  Collection|null  $saved  Saved answers keyed by question_id (students resuming).
     */
    public function questions(Exam $exam, string $seed, ?Collection $saved = null): Collection
    {
        $this->load($exam);

        return $this->orderedQuestions($exam, $seed)->map(function (Question $q) use ($exam, $seed, $saved) {
            $answer = $saved?->get($q->id);

            return [
                'id' => $q->id,
                'section_id' => $q->pivot->exam_section_id,
                'question_text' => $q->question_text,
                'question_image_url' => $q->question_image_url,
                'question_type' => $q->question_type,                 // single | multiple
                'marks' => (float) ($q->pivot->marks ?? $q->marks),
                'options' => $this->orderedOptions($exam, $q, $seed),
                'selected' => $answer?->selected_options ?? [],
                'flagged' => (bool) ($answer?->is_flagged ?? false),
            ];
        })->values();
    }

    /**
     * @return array<int, array{id:int,name:string,instructions:?string}>
     */
    public function sections(Exam $exam): array
    {
        $this->load($exam);

        return $exam->sections->map(fn ($section) => [
            'id' => $section->id,
            'name' => $section->name,
            'instructions' => $section->instructions,
        ])->values()->all();
    }

    /**
     * Questions in pivot order, deterministically shuffled per seed when randomized.
     * Shuffling stays inside each section so the paper keeps its section order.
     */
    public function orderedQuestions(Exam $exam, string $seed): Collection
    {
        $questions = $exam->questions;

        if ($exam->randomize_questions) {
            $questions = $questions
                ->groupBy(fn ($q) => $q->pivot->exam_section_id ?? 0)
                ->flatMap(fn ($group) => $group->sortBy(fn ($q) => md5($seed.'-'.$q->id)))
                ->values();
        }

        return $questions;
    }

    /**
     * Option list [{key,text}], preserving original letters (so scoring stays valid),
     * deterministically shuffled per seed when randomized.
     *
     * @return array<int, array{key:string,text:string}>
     */
    public function orderedOptions(Exam $exam, Question $q, string $seed): array
    {
        $options = collect(['a', 'b', 'c', 'd'])
            ->map(fn ($k) => ['key' => $k, 'text' => $q->{'option_'.$k}])
            ->filter(fn ($o) => filled($o['text']))
            ->values();

        if ($exam->randomize_options) {
            $options = $options->sortBy(fn ($o) => md5($seed.'-'.$q->id.'-'.$o['key']))->values();
        }

        return $options->all();
    }
}
