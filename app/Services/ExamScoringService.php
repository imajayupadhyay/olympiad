<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;

class ExamScoringService
{
    /**
     * Score a submitted attempt: evaluate every answer, write is_correct + marks_awarded,
     * and roll up the attempt totals. Idempotent — safe to call more than once.
     *
     * Correctness:
     *  - single   → the one selected option equals the one correct option
     *  - multiple → the selected set exactly equals the correct set
     * Marks use the per-exam pivot value (COALESCE pivot.marks, question.marks); a wrong
     * answer deducts the negative marks only when the exam enables negative marking.
     *
     * NOTE: produces the raw score on the attempt. Ranks/percentile/grade and visibility
     * remain the admin's job (process + release).
     */
    public function score(ExamAttempt $attempt, string $finalStatus = 'submitted'): ExamAttempt
    {
        $exam = $attempt->exam()->with('questions')->first();

        $answers = $attempt->answers()->get()->keyBy('question_id');

        $negativeEnabled = (bool) $exam->negative_marking_enabled;

        $totalScore = 0.0;
        $correct = 0;
        $wrong = 0;
        $skipped = 0;
        $attempted = 0;

        foreach ($exam->questions as $question) {
            /** @var Answer|null $answer */
            $answer = $answers->get($question->id);
            $verdict = $this->evaluate($question, $answer?->selected_options ?? [], $negativeEnabled);

            // Unanswered → skipped, no marks.
            if ($verdict['state'] === 'skipped') {
                $skipped++;
                if ($answer) {
                    $answer->update(['is_correct' => false, 'marks_awarded' => 0]);
                }

                continue;
            }

            $attempted++;
            if ($verdict['is_correct']) {
                $correct++;
            } else {
                $wrong++;
            }

            $totalScore += $verdict['marks_awarded'];
            $answer->update(['is_correct' => $verdict['is_correct'], 'marks_awarded' => $verdict['marks_awarded']]);
        }

        $attempt->update([
            'status' => $finalStatus,
            'submitted_at' => now(),
            'time_taken_seconds' => $this->elapsedSeconds($attempt),
            'total_score' => round($totalScore, 2),
            'total_attempted' => $attempted,
            'total_correct' => $correct,
            'total_wrong' => $wrong,
            'total_skipped' => $skipped,
        ]);

        return $attempt->refresh();
    }

    /**
     * The single correctness + marks rule for one question (pivot marks, then bank marks;
     * negative marks only when the exam enables negative marking). Pure — writes nothing.
     *
     * @return array{state: 'correct'|'wrong'|'skipped', is_correct: bool, marks_awarded: float, max_marks: float}
     */
    public function evaluate(Question $question, array $selectedOptions, bool $negativeEnabled): array
    {
        $marks = (float) ($question->pivot->marks ?? $question->marks);
        $negative = (float) ($question->pivot->negative_marks ?? $question->negative_marks ?? 0);
        $selected = $this->normalize($selectedOptions);

        if (empty($selected)) {
            return ['state' => 'skipped', 'is_correct' => false, 'marks_awarded' => 0.0, 'max_marks' => $marks];
        }

        $isCorrect = $selected === $this->normalize($question->correct_options ?? []);

        return [
            'state' => $isCorrect ? 'correct' : 'wrong',
            'is_correct' => $isCorrect,
            'marks_awarded' => $isCorrect ? $marks : ($negativeEnabled ? -abs($negative) : 0.0),
            'max_marks' => $marks,
        ];
    }

    /**
     * Score a set of selections for an exam WITHOUT persisting anything (admin preview).
     * Uses the same rule as score(), so a preview total equals what a student would get.
     *
     * @param  array<int|string, array>  $selections  question_id => selected option keys
     * @return array{score: float, max_score: float, percentage: float, total: int, attempted: int, correct: int, wrong: int, skipped: int, questions: array<int, array>}
     */
    public function preview(Exam $exam, array $selections): array
    {
        $exam->loadMissing('questions');
        $negativeEnabled = (bool) $exam->negative_marking_enabled;
        $totals = ['score' => 0.0, 'max_score' => 0.0, 'attempted' => 0, 'correct' => 0, 'wrong' => 0, 'skipped' => 0];
        $questions = [];

        foreach ($exam->questions as $question) {
            $selected = array_values(array_unique(array_map('strval', $selections[$question->id] ?? [])));
            $verdict = $this->evaluate($question, $selected, $negativeEnabled);

            $totals['max_score'] += $verdict['max_marks'];
            $totals['score'] += $verdict['marks_awarded'];
            $totals[$verdict['state']]++;
            if ($verdict['state'] !== 'skipped') {
                $totals['attempted']++;
            }

            $questions[$question->id] = ['selected_options' => $selected, ...$verdict];
        }

        $totals['score'] = round($totals['score'], 2);
        $totals['max_score'] = round($totals['max_score'], 2);

        return [
            ...$totals,
            'percentage' => $totals['max_score'] > 0 ? round($totals['score'] / $totals['max_score'] * 100, 2) : 0.0,
            'total' => $exam->questions->count(),
            'questions' => $questions,
        ];
    }

    /**
     * Lowercase, trim, sort and de-duplicate an option array for order-independent comparison.
     */
    protected function normalize(array $options): array
    {
        $clean = collect($options)
            ->map(fn ($o) => strtolower(trim((string) $o)))
            ->filter(fn ($o) => $o !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $clean;
    }

    /**
     * Active time used: paused / closed time is excluded and the value is capped at the
     * time the student was given (ExamAttempt::timeUsedSeconds()).
     */
    protected function elapsedSeconds(ExamAttempt $attempt): int
    {
        return $attempt->timeUsedSeconds(now());
    }
}
