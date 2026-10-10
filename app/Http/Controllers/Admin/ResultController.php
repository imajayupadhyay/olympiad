<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Result;
use App\Services\ExamSectionScoreService;
use App\Services\ManagedEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ResultController extends Controller
{
    public function index(): Response
    {
        $exams = Exam::with(['subject', 'classLevel'])
            ->withCount([
                'attempts as total_attempts' => fn($q) => $q->whereIn('status', ['submitted', 'timed_out', 'auto_submitted']),
                'results as processed_count',
                'results as released_count' => fn($q) => $q->where('is_released', true),
            ])
            ->whereIn('status', ['published', 'archived'])
            ->latest('starts_at')
            ->get();

        return Inertia::render('Admin/Results/Index', [
            'exams' => $exams,
        ]);
    }

    public function show(Exam $exam, ExamSectionScoreService $sectionScores): Response
    {
        $exam->load(['subject', 'classLevel', 'sections:id,exam_id,name,sort_order']);

        $results = Result::where('exam_id', $exam->id)
            ->with([
                'user:id,name,email,school,city,state,class_level_id',
                'user.classLevel:id,label',
                'attempt:id,total_correct,total_wrong,total_skipped,time_taken_seconds,submitted_at',
                'overriddenBy:id,name',
            ])
            ->orderBy('rank_national')
            ->paginate(50);
        $results->getCollection()->transform(function (Result $result) use ($sectionScores) {
            $result->section_scores = $sectionScores->forResult($result);

            return $result;
        });

        $stats = [
            'total_attempts' => ExamAttempt::where('exam_id', $exam->id)->whereIn('status', ['submitted', 'timed_out', 'auto_submitted'])->count(),
            'processed'      => Result::where('exam_id', $exam->id)->count(),
            'released'       => Result::where('exam_id', $exam->id)->where('is_released', true)->count(),
            'avg_score'      => Result::where('exam_id', $exam->id)->avg('total_score') ?? 0,
            'highest_score'  => Result::where('exam_id', $exam->id)->max('total_score') ?? 0,
        ];

        // Cohort section stats only matter when the paper has more than one section.
        $sectionStats = $exam->sections->count() > 1
            ? $sectionScores->summary(Result::where('exam_id', $exam->id)->with('attempt', 'exam')->get()
                ->map(fn (Result $result) => $sectionScores->forResult($result)))
            : [];

        return Inertia::render('Admin/Results/Show', [
            'exam'    => $exam,
            'results' => $results,
            'stats'   => $stats,
            'sectionStats' => $sectionStats,
        ]);
    }

    public function process(Request $request, Exam $exam, ExamSectionScoreService $sectionScores)
    {
        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->whereIn('status', ['submitted', 'timed_out', 'auto_submitted'])
            ->get();

        if ($attempts->isEmpty()) {
            return back()->with('error', 'No submitted attempts found for this exam.');
        }

        $maxScore = $exam->questions()->sum(DB::raw('COALESCE(exam_questions.marks, questions.marks)'));

        $exam->load('sections', 'questions');

        DB::transaction(function () use ($exam, $attempts, $maxScore, $sectionScores) {
            $ranked = $attempts->sortByDesc('total_score')->values();

            foreach ($ranked as $rank => $attempt) {
                $score      = $attempt->total_score;
                $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0;

                Result::updateOrCreate(
                    ['user_id' => $attempt->user_id, 'exam_id' => $exam->id],
                    [
                        'exam_attempt_id' => $attempt->id,
                        'total_score'     => $score,
                        'max_score'       => $maxScore,
                        'percentage'      => $percentage,
                        'section_scores'  => $sectionScores->breakdown($attempt, $exam),
                        'rank_national'   => $rank + 1,
                        'grade'           => $this->grade($percentage),
                        'is_released'     => false,
                    ]
                );
            }

            $this->calculatePercentiles($exam->id);
        });

        return back()->with('success', "Results processed for {$attempts->count()} students. Ranks assigned.");
    }

    public function release(Request $request, Exam $exam, ManagedEmailService $emails)
    {
        $results = Result::where('exam_id', $exam->id)
            ->where('is_released', false)
            ->with(['user', 'exam'])
            ->get();

        $count = $results->count();

        Result::where('exam_id', $exam->id)->update([
            'is_released' => true,
            'released_at' => now(),
        ]);

        foreach ($results as $result) {
            $emails->queue(
                'result_released',
                $result->user,
                $emails->resultVariables($result),
                ['related_type' => Result::class, 'related_id' => $result->id]
            );
        }

        return back()->with('success', "{$count} results released to students.");
    }

    public function override(Request $request, Result $result)
    {
        $data = $request->validate([
            'score_override'  => 'required|numeric|min:0',
            'override_reason' => 'required|string|max:500',
        ]);

        $result->update([
            'score_override'  => $data['score_override'],
            'override_reason' => $data['override_reason'],
            'override_by'     => auth()->id(),
        ]);

        return back()->with('success', 'Score override applied successfully.');
    }

    private function grade(float $pct): string
    {
        return match (true) {
            $pct >= 90 => 'A+',
            $pct >= 75 => 'A',
            $pct >= 60 => 'B',
            $pct >= 45 => 'C',
            $pct >= 33 => 'D',
            default    => 'F',
        };
    }

    private function calculatePercentiles(int $examId): void
    {
        $results = Result::where('exam_id', $examId)->orderBy('total_score')->get();
        $total   = $results->count();
        foreach ($results as $i => $r) {
            $r->update(['percentile' => round(($i / $total) * 100, 2)]);
        }
    }
}
