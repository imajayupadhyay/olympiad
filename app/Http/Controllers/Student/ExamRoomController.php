<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\ExamScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ExamRoomController extends Controller
{
    public function __construct(
        protected ExamScoringService $scoring,
    ) {
    }

    /**
     * Start (or resume) an attempt. Single attempt per exam, only inside the admin window.
     */
    public function start(Request $request, Exam $exam)
    {
        $user = $request->user();

        abort_unless($exam->status === 'published', 404);

        if (! $user->isEnrolledIn($exam->id)) {
            return redirect()->route('student.exams.show', $exam)->with('error', 'Enrol in this exam first.');
        }

        if (! $exam->isOpenNow()) {
            $msg = $exam->availabilityState() === 'upcoming'
                ? 'This exam has not opened yet.'
                : 'The exam window has closed.';

            return redirect()->route('student.exams.show', $exam)->with('error', $msg);
        }

        $attempt = ExamAttempt::firstOrCreate(
            ['user_id' => $user->id, 'exam_id' => $exam->id],
            ['status' => 'in_progress', 'started_at' => now(), 'ip_address' => $request->ip()],
        );

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('student.exam-room.submitted', $attempt);
        }

        return redirect()->route('student.exam-room', $attempt);
    }

    /**
     * The exam room: sanitized questions, saved answers, server-computed remaining time.
     */
    public function index(Request $request, ExamAttempt $attempt): Response|\Illuminate\Http\RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('student.exam-room.submitted', $attempt);
        }

        $exam = $attempt->exam()->with('questions')->first();

        $remaining = $this->remainingSeconds($attempt, $exam);
        if ($remaining <= 0) {
            $this->scoring->score($attempt, 'timed_out');

            return redirect()->route('student.exam-room.submitted', $attempt);
        }

        $questions = $this->orderedQuestions($attempt, $exam);
        $saved = $attempt->answers()->get()->keyBy('question_id');

        $payload = $questions->map(function ($q) use ($attempt, $exam, $saved) {
            $answer = $saved->get($q->id);

            return [
                'id'             => $q->id,
                'question_text'  => $q->question_text,
                'question_image_url' => $q->question_image_url,
                'question_type'  => $q->question_type,                 // single | multiple
                'marks'          => (float) ($q->pivot->marks ?? $q->marks),
                'options'        => $this->orderedOptions($attempt, $exam, $q),
                'selected'       => $answer?->selected_options ?? [],
                'flagged'        => (bool) ($answer?->is_flagged ?? false),
            ];
        })->values();

        return Inertia::render('Student/ExamRoom/Index', [
            'attempt' => [
                'id'          => $attempt->id,
                'started_at'  => $attempt->started_at,
            ],
            'exam' => [
                'id'                => $exam->id,
                'name'              => $exam->name,
                'duration_minutes'  => $exam->duration_minutes,
                'negative_marking_enabled'    => (bool) $exam->negative_marking_enabled,
                'total_questions'   => $payload->count(),
            ],
            'questions'         => $payload,
            'remaining_seconds' => $remaining,
        ]);
    }

    /**
     * Silent autosave of a single answer/flag (JSON, no Inertia reload).
     */
    public function saveAnswer(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json(['ok' => false, 'reason' => 'closed'], 409);
        }

        $data = $request->validate([
            'question_id'      => 'required|integer',
            'selected_options' => 'array',
            'selected_options.*' => 'string|in:a,b,c,d',
            'is_flagged'       => 'boolean',
        ]);

        // Question must belong to this exam.
        $belongs = $attempt->exam->questions()->where('questions.id', $data['question_id'])->exists();
        abort_unless($belongs, 422);

        Answer::updateOrCreate(
            ['exam_attempt_id' => $attempt->id, 'question_id' => $data['question_id']],
            [
                'selected_options' => array_values(array_unique($data['selected_options'] ?? [])),
                'is_flagged'       => $data['is_flagged'] ?? false,
            ],
        );

        return response()->json(['ok' => true, 'saved_at' => now()->toIso8601String()]);
    }

    /**
     * Submit the attempt (manual / timeout / anti-cheat). Idempotent.
     */
    public function submit(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);

        if ($attempt->status === 'in_progress') {
            $reason = $request->input('reason');           // manual | timeout | strikes
            $exam = $attempt->exam;
            $status = ($reason === 'timeout' || $this->remainingSeconds($attempt, $exam) <= 0)
                ? 'timed_out'
                : ($reason === 'strikes' ? 'auto_submitted' : 'submitted');

            $this->scoring->score($attempt, $status);
        }

        return redirect()->route('student.exam-room.submitted', $attempt);
    }

    /**
     * Post-submit confirmation (no score shown — results are admin-declared).
     */
    public function submitted(Request $request, ExamAttempt $attempt): Response|\Illuminate\Http\RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);

        if ($attempt->status === 'in_progress') {
            return redirect()->route('student.exam-room', $attempt);
        }

        return Inertia::render('Student/ExamRoom/Submitted', [
            'exam' => $attempt->exam()->first()->only(['id', 'name']),
            'attempt' => [
                'total_questions' => $attempt->total_attempted + $attempt->total_skipped,
                'answered'        => $attempt->total_attempted,
                'time_taken_seconds' => $attempt->time_taken_seconds,
            ],
        ]);
    }

    /* ───────────────────────── helpers ───────────────────────── */

    protected function authorizeAttempt(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }

    /**
     * Personal deadline = min(started_at + duration, exam ends_at). Returns seconds left.
     */
    protected function remainingSeconds(ExamAttempt $attempt, Exam $exam): int
    {
        $start = $attempt->started_at ?? now();
        $deadline = $start->copy()->addMinutes($exam->duration_minutes);

        if ($exam->ends_at && $exam->ends_at->lt($deadline)) {
            $deadline = $exam->ends_at;
        }

        return (int) max(0, Carbon::now()->diffInSeconds($deadline, false));
    }

    /**
     * Questions in pivot order, deterministically shuffled per-attempt when randomized.
     */
    protected function orderedQuestions(ExamAttempt $attempt, Exam $exam)
    {
        $questions = $exam->questions;

        if ($exam->randomize_questions) {
            $questions = $questions->sortBy(fn ($q) => md5($attempt->id.'-'.$q->id))->values();
        }

        return $questions;
    }

    /**
     * Build option list [{key,text}], preserving original letters (so scoring stays valid),
     * deterministically shuffled per-attempt when randomized.
     *
     * @return array<int, array{key:string,text:string}>
     */
    protected function orderedOptions(ExamAttempt $attempt, Exam $exam, $q): array
    {
        $options = collect(['a', 'b', 'c', 'd'])
            ->map(fn ($k) => ['key' => $k, 'text' => $q->{'option_'.$k}])
            ->filter(fn ($o) => filled($o['text']))
            ->values();

        if ($exam->randomize_options) {
            $options = $options->sortBy(fn ($o) => md5($attempt->id.'-'.$q->id.'-'.$o['key']))->values();
        }

        return $options->all();
    }
}
