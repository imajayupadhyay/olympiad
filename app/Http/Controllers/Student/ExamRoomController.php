<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\ExamPaperService;
use App\Services\ExamSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExamRoomController extends Controller
{
    public function __construct(
        protected ExamPaperService $paper,
        protected ExamSessionService $sessions,
    ) {}

    /**
     * Start (or resume) the single attempt, inside the exam window or the student's
     * personal (rescheduled) window.
     */
    public function start(Request $request, Exam $exam)
    {
        $user = $request->user();

        abort_unless($exam->status === 'published', 404);

        $existing = ExamAttempt::where('user_id', $user->id)->where('exam_id', $exam->id)->first();
        if ($existing) {
            return $existing->isOpen()
                ? redirect()->route('student.exam-room', $existing)
                : redirect()->route('student.exam-room.submitted', $existing);
        }

        if ($blocker = $this->sessions->startBlocker($user, $exam)) {
            return redirect()->route('student.exams.show', $exam)->with('error', $blocker);
        }

        $attempt = $this->sessions->begin($user, $exam, $request->ip());

        return $attempt->isOpen()
            ? redirect()->route('student.exam-room', $attempt)
            : redirect()->route('student.exam-room.submitted', $attempt);
    }

    /**
     * The exam room: sanitized questions, saved answers, server-computed remaining time and
     * where the student left off. A paused session gets no questions.
     */
    public function index(Request $request, ExamAttempt $attempt): Response|RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);

        if (! $attempt->isOpen()) {
            return redirect()->route('student.exam-room.submitted', $attempt);
        }

        $attempt = $this->sessions->enterRoom($attempt, $request);
        $exam = $this->paper->load($attempt->exam);

        if (! $attempt->isPaused() && $attempt->remainingSeconds() <= 0) {
            $this->sessions->finalize($attempt, 'timed_out', 'timed_out', null, 'Deadline passed before the student came back.');

            return redirect()->route('student.exam-room.submitted', $attempt);
        }

        $saved = $attempt->answers()->get()->keyBy('question_id');
        $paused = $attempt->isPaused();
        $payload = $paused ? collect() : $this->paper->questions($exam, (string) $attempt->id, $saved);
        $answered = $saved->filter(fn ($answer) => ! empty($answer->selected_options))->count();

        $enrollment = $paused ? $this->sessions->enrollment($attempt->user_id, $exam->id) : null;

        return Inertia::render('Student/ExamRoom/Index', [
            'attempt' => [
                'id' => $attempt->id,
                'started_at' => $attempt->started_at,
            ],
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'duration_minutes' => $exam->duration_minutes,
                'negative_marking_enabled' => (bool) $exam->negative_marking_enabled,
                'total_questions' => $exam->questions->count(),
            ],
            'sections' => $this->paper->sections($exam),
            'questions' => $payload,
            'remaining_seconds' => $attempt->remainingSeconds(),
            'session' => [
                'token' => $attempt->session_token,
                'heartbeat_seconds' => ExamSessionService::HEARTBEAT_SECONDS,
                'violation_count' => $attempt->violation_count,
                'max_strikes' => ExamSessionService::MAX_STRIKES,
                'is_resume' => $answered > 0 || $saved->isNotEmpty() || $attempt->current_question_id !== null,
                'answered' => $answered,
                'current_question_id' => $attempt->current_question_id,
                'paused' => $paused ? [
                    'rescheduled' => (bool) $attempt->resume_on_window,
                    'window_label' => $attempt->resume_on_window && $enrollment
                        ? $this->sessions->windowLabel($enrollment->window_starts_at, $enrollment->window_ends_at)
                        : null,
                ] : null,
            ],
        ]);
    }

    /**
     * Silent autosave of a single answer/flag (JSON, no Inertia reload). Serialised with
     * admin actions through the attempt row lock.
     */
    public function saveAnswer(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);

        $data = $request->validate([
            'question_id' => 'required|integer',
            'selected_options' => 'array',
            'selected_options.*' => 'string|in:a,b,c,d',
            'is_flagged' => 'boolean',
            'session_token' => 'nullable|string|max:40',
            'current_question_id' => 'nullable|integer',
        ]);

        // Question must belong to this exam.
        $belongs = $attempt->exam->questions()->where('questions.id', $data['question_id'])->exists();
        abort_unless($belongs, 422);

        return DB::transaction(function () use ($attempt, $data) {
            $attempt = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->first();

            if ($blocker = $this->sessions->blocker($attempt, $data['session_token'] ?? null)) {
                return response()->json(['ok' => false, 'reason' => $blocker], 409);
            }

            Answer::updateOrCreate(
                ['exam_attempt_id' => $attempt->id, 'question_id' => $data['question_id']],
                [
                    'selected_options' => array_values(array_unique($data['selected_options'] ?? [])),
                    'is_flagged' => $data['is_flagged'] ?? false,
                ],
            );
            $this->sessions->touch($attempt, $data['current_question_id'] ?? $data['question_id']);

            return response()->json(['ok' => true, 'saved_at' => now()->toIso8601String()]);
        });
    }

    /**
     * Heartbeat: keeps "last seen" fresh and returns the server's view of the session, so
     * the room picks up pauses, added time and force-submits live.
     */
    public function heartbeat(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);

        $data = $request->validate([
            'session_token' => 'nullable|string|max:40',
            'current_question_id' => 'nullable|integer',
        ]);

        $blocker = $this->sessions->blocker($attempt, $data['session_token'] ?? null);
        if (in_array($blocker, ['closed', 'superseded'], true)) {
            return response()->json(['ok' => false, 'reason' => $blocker], 409);
        }

        // A paused student is still online; touch() skips the lost-time log while paused.
        $this->sessions->touch($attempt, $data['current_question_id'] ?? null);

        $resumable = $attempt->isPaused() && $attempt->resume_on_window
            && $this->sessions->windowState($attempt->exam, $this->sessions->enrollment($attempt->user_id, $attempt->exam_id)) === 'live';

        return response()->json([
            'ok' => true,
            'paused' => $attempt->isPaused(),
            // Rescheduled window just opened: reload the room to resume.
            'resumable' => $resumable,
            'remaining_seconds' => $attempt->remainingSeconds(),
            'violation_count' => $attempt->violation_count,
        ]);
    }

    /** A tab switch. The server counts strikes and auto-submits at the limit. */
    public function violation(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);

        $token = $request->validate(['session_token' => 'nullable|string|max:40'])['session_token'] ?? null;
        if ($blocker = $this->sessions->blocker($attempt, $token)) {
            return response()->json(['ok' => false, 'reason' => $blocker], 409);
        }

        return response()->json(['ok' => true, ...$this->sessions->recordViolation($attempt)]);
    }

    /**
     * Submit the attempt (manual / timeout). Idempotent. A "timeout" from a stale timer
     * (e.g. an admin just added time) goes back to the room instead of submitting.
     */
    public function submit(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);

        if ($attempt->isOpen()) {
            $reason = $request->input('reason');           // manual | timeout | strikes
            $remaining = $attempt->remainingSeconds();

            if ($attempt->isPaused() || ($reason === 'timeout' && $remaining > 5)) {
                return redirect()->route('student.exam-room', $attempt);
            }

            $status = ($reason === 'timeout' || $remaining <= 0) ? 'timed_out' : 'submitted';
            $this->sessions->finalize($attempt, $status, $status);
        }

        return redirect()->route('student.exam-room.submitted', $attempt);
    }

    /**
     * Post-submit confirmation (no score shown — results are admin-declared).
     */
    public function submitted(Request $request, ExamAttempt $attempt): Response|RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);

        if ($attempt->isOpen()) {
            return redirect()->route('student.exam-room', $attempt);
        }

        return Inertia::render('Student/ExamRoom/Submitted', [
            'exam' => $attempt->exam()->first()->only(['id', 'name']),
            'attempt' => [
                'total_questions' => $attempt->total_attempted + $attempt->total_skipped,
                'answered' => $attempt->total_attempted,
                'time_taken_seconds' => $attempt->time_taken_seconds,
            ],
        ]);
    }

    /* ───────────────────────── helpers ───────────────────────── */

    protected function authorizeAttempt(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }
}
