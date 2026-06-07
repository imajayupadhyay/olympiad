<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\ExamAttempt;
use App\Models\Result;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultController extends Controller
{
    /**
     * List the student's finished attempts. Released results show score/rank/grade;
     * the rest show "awaiting declaration".
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $attempts = ExamAttempt::where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'timed_out', 'auto_submitted'])
            ->with(['exam:id,name,subject_id', 'exam.subject:id,name,icon,color', 'result'])
            ->latest('submitted_at')
            ->get();

        $rows = $attempts->map(function (ExamAttempt $a) {
            $r = $a->result;
            $released = $r && $r->is_released;

            return [
                'attempt_id'   => $a->id,
                'exam'         => $a->exam?->only(['id', 'name']),
                'subject'      => $a->exam?->subject?->only(['name', 'icon', 'color']),
                'submitted_at' => $a->submitted_at,
                'released'     => $released,
                'result'       => $released ? [
                    'id'         => $r->id,
                    'score'      => $r->effectiveScore(),
                    'max_score'  => $r->max_score,
                    'percentage' => $r->percentage,
                    'grade'      => $r->grade,
                    'rank'       => $r->rank_national,
                ] : null,
            ];
        });

        return Inertia::render('Student/Results/Index', [
            'results' => $rows,
        ]);
    }

    /**
     * Detailed scorecard + answer review — only when the admin has released the result.
     */
    public function show(Request $request, Result $result): Response
    {
        abort_unless($result->user_id === $request->user()->id, 403);

        if (! $result->is_released) {
            return Inertia::render('Student/Results/Index', [
                'results' => [],
                'flash_error' => 'That result has not been declared yet.',
            ]);
        }

        $result->load([
            'exam:id,name,subject_id',
            'exam.subject:id,name',
            'attempt',
        ]);

        $attempt = $result->attempt;

        // Answer review (allowed now that the result is public).
        $exam = $result->exam()->with('questions')->first();
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $review = $exam->questions->map(function ($q) use ($answers) {
            $a = $answers->get($q->id);
            $selected = $a?->selected_options ?? [];

            return [
                'id'             => $q->id,
                'question_text'  => $q->question_text,
                'question_image_url' => $q->question_image_url,
                'options'        => collect(['a', 'b', 'c', 'd'])
                    ->map(fn ($k) => ['key' => $k, 'text' => $q->{'option_'.$k}])
                    ->filter(fn ($o) => filled($o['text']))->values(),
                'correct'        => array_map('strtolower', $q->correct_options ?? []),
                'selected'       => array_map('strtolower', $selected),
                'is_correct'     => (bool) ($a?->is_correct ?? false),
                'marks_awarded'  => (float) ($a?->marks_awarded ?? 0),
                'explanation'    => $q->explanation,
            ];
        })->values();

        $certificate = Certificate::where('user_id', $result->user_id)
            ->where('exam_id', $result->exam_id)
            ->where('type', 'student')
            ->first();

        return Inertia::render('Student/Results/Show', [
            'result' => [
                'exam'       => $result->exam?->only(['id', 'name']),
                'subject'    => $result->exam?->subject?->only(['name']),
                'score'      => $result->effectiveScore(),
                'max_score'  => $result->max_score,
                'percentage' => $result->percentage,
                'percentile' => $result->percentile,
                'grade'      => $result->grade,
                'rank'       => $result->rank_national,
                'released_at'=> $result->released_at,
            ],
            'certificate_id' => $certificate?->id,
            'attempt' => [
                'total_correct' => $attempt->total_correct,
                'total_wrong'   => $attempt->total_wrong,
                'total_skipped' => $attempt->total_skipped,
                'time_taken_seconds' => $attempt->time_taken_seconds,
            ],
            'review' => $review,
        ]);
    }
}
