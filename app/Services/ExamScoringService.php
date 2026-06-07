<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\ExamAttempt;
use Illuminate\Support\Carbon;

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
            $marks = (float) ($question->pivot->marks ?? $question->marks);
            $negative = (float) ($question->pivot->negative_marks ?? $question->negative_marks ?? 0);

            /** @var Answer|null $answer */
            $answer = $answers->get($question->id);
            $selected = $this->normalize($answer?->selected_options ?? []);

            // Unanswered → skipped, no marks.
            if (empty($selected)) {
                $skipped++;
                if ($answer) {
                    $answer->update(['is_correct' => false, 'marks_awarded' => 0]);
                }
                continue;
            }

            $attempted++;
            $isCorrect = $selected === $this->normalize($question->correct_options ?? []);

            if ($isCorrect) {
                $correct++;
                $awarded = $marks;
            } else {
                $wrong++;
                $awarded = $negativeEnabled ? -abs($negative) : 0;
            }

            $totalScore += $awarded;
            $answer->update(['is_correct' => $isCorrect, 'marks_awarded' => $awarded]);
        }

        $attempt->update([
            'status'             => $finalStatus,
            'submitted_at'       => now(),
            'time_taken_seconds' => $this->elapsedSeconds($attempt),
            'total_score'        => round($totalScore, 2),
            'total_attempted'    => $attempted,
            'total_correct'      => $correct,
            'total_wrong'        => $wrong,
            'total_skipped'      => $skipped,
        ]);

        return $attempt->refresh();
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

    protected function elapsedSeconds(ExamAttempt $attempt): int
    {
        if (! $attempt->started_at) {
            return 0;
        }

        return (int) max(0, Carbon::now()->diffInSeconds($attempt->started_at, true));
    }
}
