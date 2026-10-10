<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Result;
use Illuminate\Support\Collection;

/**
 * Per-section performance for a scored attempt, built from the answers ExamScoringService
 * already marked (is_correct / marks_awarded) and the paper's section placement.
 */
class ExamSectionScoreService
{
    /**
     * @return array<int, array{section_id: ?int, name: string, score: float, max_score: float, percentage: float, total: int, correct: int, wrong: int, skipped: int}>
     */
    public function breakdown(ExamAttempt $attempt, ?Exam $exam = null): array
    {
        $exam ??= $attempt->exam;
        $exam->loadMissing('sections', 'questions');
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $bySection = $exam->questions->groupBy(fn (Question $question) => $question->pivot->exam_section_id ?? 0);

        $sections = $exam->sections->map(fn ($section) => ['id' => $section->id, 'name' => $section->name]);
        if ($bySection->has(0)) {
            $sections->push(['id' => null, 'name' => 'General']);
        }

        return $sections->map(function (array $section) use ($bySection, $answers) {
            $questions = $bySection->get($section['id'] ?? 0, collect());
            $row = [
                'section_id' => $section['id'],
                'name' => $section['name'],
                'score' => 0.0,
                'max_score' => 0.0,
                'percentage' => 0.0,
                'total' => $questions->count(),
                'correct' => 0,
                'wrong' => 0,
                'skipped' => 0,
            ];

            foreach ($questions as $question) {
                $answer = $answers->get($question->id);
                $row['max_score'] += (float) ($question->pivot->marks ?? $question->marks);

                if (! $answer || empty($answer->selected_options)) {
                    $row['skipped']++;
                } elseif ($answer->is_correct) {
                    $row['correct']++;
                } else {
                    $row['wrong']++;
                }
                $row['score'] += (float) ($answer?->marks_awarded ?? 0);
            }

            $row['score'] = round($row['score'], 2);
            $row['max_score'] = round($row['max_score'], 2);
            $row['percentage'] = $row['max_score'] > 0 ? round($row['score'] / $row['max_score'] * 100, 2) : 0.0;

            return $row;
        })->filter(fn (array $row) => $row['total'] > 0)->values()->all();
    }

    /**
     * The stored snapshot, or a live breakdown for results processed before snapshots existed.
     */
    public function forResult(Result $result): array
    {
        if (is_array($result->section_scores)) {
            return $result->section_scores;
        }

        return $result->attempt ? $this->breakdown($result->attempt, $result->exam) : [];
    }

    /**
     * Cohort view per section: average, highest and average accuracy across processed results.
     *
     * @param  Collection<int, array>  $breakdowns  one forResult() array per student
     */
    public function summary(Collection $breakdowns): array
    {
        return $breakdowns->flatten(1)
            ->groupBy(fn (array $row) => $row['section_id'] ?? 0)
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $answered = $rows->sum('correct') + $rows->sum('wrong');

                return [
                    'section_id' => $first['section_id'],
                    'name' => $first['name'],
                    'max_score' => $first['max_score'],
                    'average' => round($rows->avg('score'), 2),
                    'highest' => round($rows->max('score'), 2),
                    'accuracy' => $answered > 0 ? round($rows->sum('correct') / $answered * 100, 1) : 0.0,
                ];
            })->values()->all();
    }
}
