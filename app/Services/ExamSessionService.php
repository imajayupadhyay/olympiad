<?php

namespace App\Services;

use App\Exceptions\ExamSessionException;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamEnrollment;
use App\Models\ExamSessionEvent;
use App\Models\Result;
use App\Models\StudentNotification;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the live exam session: personal windows, deadlines, the room heartbeat, strikes,
 * finalising, and every admin recovery action (Ongoing Exams).
 *
 * Rules:
 *  - Every state change runs in a transaction with the attempt row locked, re-checks its
 *    preconditions inside the lock, and writes an ExamSessionEvent.
 *  - "Paused" is in_progress + suspended_at. The clock (deadline_at) is frozen while paused.
 *  - Admins never edit answers or correctness, and nothing is reopened or reset once its
 *    result is released.
 */
class ExamSessionService
{
    public const HEARTBEAT_SECONDS = 30;

    public const ONLINE_SECONDS = 75;

    public const UNSTABLE_SECONDS = 180;

    public const EXPIRY_GRACE_SECONDS = 120;

    public const DEVICE_SWITCH_SECONDS = 90;

    public const MAX_STRIKES = 3;

    public const MAX_GRANT_MINUTES = 180;

    /** Set while a bulk action runs, so each attempt's event carries the shared batch id. */
    private ?string $batch = null;

    public function __construct(private ExamScoringService $scoring) {}

    /* ───────────────────────── windows & starting ───────────────────────── */

    public function enrollment(int $userId, int $examId): ?ExamEnrollment
    {
        return ExamEnrollment::where('user_id', $userId)->where('exam_id', $examId)
            ->where('status', 'enrolled')->first();
    }

    /**
     * The window this student may start in: their personal window (reschedule) or the exam's.
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: bool} [starts, ends, isPersonal]
     */
    public function window(Exam $exam, ?ExamEnrollment $enrollment): array
    {
        if ($enrollment?->hasPersonalWindow()) {
            return [$enrollment->window_starts_at, $enrollment->window_ends_at, true];
        }

        return [$exam->starts_at, $exam->ends_at, false];
    }

    /** upcoming | live | closed for this student. */
    public function windowState(Exam $exam, ?ExamEnrollment $enrollment): string
    {
        [$starts, $ends] = $this->window($exam, $enrollment);
        $now = now();

        return match (true) {
            $starts && $now->lt($starts) => 'upcoming',
            $ends && $now->gt($ends) => 'closed',
            default => 'live',
        };
    }

    /** Null when the student may start now, otherwise the reason they can't. */
    public function startBlocker(User $user, Exam $exam): ?string
    {
        if ($exam->status !== 'published') {
            return 'This exam is not available.';
        }

        $enrollment = $this->enrollment($user->id, $exam->id);
        if (! $enrollment) {
            return 'Enrol in this exam first.';
        }

        return match ($this->windowState($exam, $enrollment)) {
            'upcoming' => 'This exam has not opened yet.',
            'closed' => 'The exam window has closed.',
            default => null,
        };
    }

    /**
     * Create the single attempt (or return the existing one). The personal deadline is
     * fixed here: min(now + duration, window end).
     */
    public function begin(User $user, Exam $exam, ?string $ip): ExamAttempt
    {
        try {
            return $this->createAttempt($user, $exam, $ip);
        } catch (UniqueConstraintViolationException) {
            // A double-click raced us to the unique (user, exam) row; use the winner.
            return ExamAttempt::where('user_id', $user->id)->where('exam_id', $exam->id)->firstOrFail();
        }
    }

    private function createAttempt(User $user, Exam $exam, ?string $ip): ExamAttempt
    {
        return DB::transaction(function () use ($user, $exam, $ip) {
            $existing = ExamAttempt::where('user_id', $user->id)->where('exam_id', $exam->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            [, $ends] = $this->window($exam, $this->enrollment($user->id, $exam->id));
            $deadline = now()->addMinutes($exam->duration_minutes);
            if ($ends && $ends->lt($deadline)) {
                $deadline = $ends->copy();
            }

            $attempt = ExamAttempt::create([
                'user_id' => $user->id,
                'exam_id' => $exam->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'deadline_at' => $deadline,
                'ip_address' => $ip,
            ]);
            $this->log($attempt, 'started', meta: ['ip' => $ip, 'deadline_at' => $deadline->toIso8601String()]);

            return $attempt;
        });
    }

    /** Stored deadline; computes and saves it for attempts that predate deadline_at. */
    public function deadline(ExamAttempt $attempt): Carbon
    {
        if (! $attempt->deadline_at) {
            $exam = $attempt->exam;
            $deadline = ($attempt->started_at ?? $attempt->created_at ?? now())->copy()->addMinutes($exam->duration_minutes);
            if ($exam->ends_at && $exam->ends_at->lt($deadline)) {
                $deadline = $exam->ends_at->copy();
            }
            $attempt->forceFill(['deadline_at' => $deadline])->save();
        }

        return $attempt->deadline_at;
    }

    /* ───────────────────────── the exam room ───────────────────────── */

    /**
     * Entering the room: rotate the session token (newest tab/device wins), resume a
     * rescheduled pause inside its window, and log it.
     */
    public function enterRoom(ExamAttempt $attempt, Request $request): ExamAttempt
    {
        return $this->locked($attempt, function (ExamAttempt $attempt) use ($request) {
            $this->deadline($attempt);

            if (! $attempt->isOpen()) {
                return $attempt;
            }

            if ($attempt->isPaused() && $attempt->resume_on_window) {
                $enrollment = $this->enrollment($attempt->user_id, $attempt->exam_id);
                if ($enrollment && $this->windowState($attempt->exam, $enrollment) === 'live') {
                    $remaining = $attempt->remainingSeconds();
                    $this->unpause($attempt);
                    // Like a late start, the time left is capped at the personal window's end.
                    $deadline = now()->addSeconds($remaining);
                    if ($enrollment->window_ends_at && $enrollment->window_ends_at->lt($deadline)) {
                        $deadline = $enrollment->window_ends_at->copy();
                    }
                    $attempt->deadline_at = $deadline;
                    $this->log($attempt, 'resumed', meta: ['by' => 'personal_window', 'remaining_seconds' => $attempt->remainingSeconds()]);
                }
            }

            $switched = $attempt->session_token
                && $attempt->last_seen_at
                && $attempt->last_seen_at->gt(now()->subSeconds(self::DEVICE_SWITCH_SECONDS));

            // Coming back after a crash or lost connection: record the gap as lost time.
            $gap = $attempt->last_seen_at ? (int) $attempt->last_seen_at->diffInSeconds(now(), true) : 0;
            if ($gap > self::ONLINE_SECONDS && ! $attempt->isPaused()) {
                $this->log($attempt, 'reconnected', meta: ['gap_seconds' => $gap]);
            }

            $attempt->session_token = Str::random(40);
            $attempt->last_seen_at = now();
            $attempt->save();

            $this->log($attempt, $switched ? 'device_switched' : 'room_opened', meta: [
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 180, ''),
                'answered' => $this->answeredCount($attempt),
            ]);

            return $attempt;
        });
    }

    /**
     * Why this request may not write to the attempt: closed | paused | superseded, or null.
     * A missing token is accepted so a room loaded before this deploy keeps saving.
     */
    public function blocker(ExamAttempt $attempt, ?string $token): ?string
    {
        return match (true) {
            ! $attempt->isOpen() => 'closed',
            $token !== null && $attempt->session_token !== null && ! hash_equals($attempt->session_token, $token) => 'superseded',
            $attempt->isPaused() => 'paused',
            default => null,
        };
    }

    /**
     * Heartbeat / autosave bookkeeping: last seen + current question. Logs a "reconnected"
     * event (with the gap) when the student comes back after missing heartbeats, so admins
     * can restore exactly the time lost.
     */
    public function touch(ExamAttempt $attempt, ?int $questionId = null): void
    {
        $now = now();
        $gap = $attempt->last_seen_at ? (int) $attempt->last_seen_at->diffInSeconds($now, true) : 0;

        $values = ['last_seen_at' => $now];
        if ($questionId && $attempt->exam->questions()->where('questions.id', $questionId)->exists()) {
            $values['current_question_id'] = $questionId;
        }
        ExamAttempt::whereKey($attempt->id)->toBase()->update($values);
        $attempt->forceFill($values);

        if ($gap > self::ONLINE_SECONDS && ! $attempt->isPaused()) {
            $this->log($attempt, 'reconnected', meta: ['gap_seconds' => $gap]);
        }
    }

    /**
     * A tab switch. The server counts strikes and auto-submits at MAX_STRIKES.
     *
     * @return array{violation_count: int, auto_submitted: bool}
     */
    public function recordViolation(ExamAttempt $attempt): array
    {
        return $this->locked($attempt, function (ExamAttempt $attempt) {
            if (! $attempt->isOpen() || $attempt->isPaused()) {
                return ['violation_count' => $attempt->violation_count, 'auto_submitted' => false];
            }

            $attempt->increment('violation_count');
            $this->log($attempt, 'violation', meta: ['count' => $attempt->violation_count]);

            if ($attempt->violation_count >= self::MAX_STRIKES) {
                $this->finalizeLocked($attempt, 'auto_submitted', 'auto_submitted', null, 'Left the exam tab '.self::MAX_STRIKES.' times.');

                return ['violation_count' => $attempt->violation_count, 'auto_submitted' => true];
            }

            return ['violation_count' => $attempt->violation_count, 'auto_submitted' => false];
        });
    }

    /**
     * The only way an attempt ends. Idempotent: a finished attempt is returned untouched.
     *
     * @param  string  $status  submitted | timed_out | auto_submitted
     */
    public function finalize(ExamAttempt $attempt, string $status, string $event, ?User $actor = null, ?string $reason = null): ExamAttempt
    {
        return $this->locked($attempt, fn (ExamAttempt $attempt) => $this->finalizeLocked($attempt, $status, $event, $actor, $reason));
    }

    /** Finalise every open, unpaused attempt whose deadline passed more than the grace ago. */
    public function sweepExpired(): int
    {
        ExamAttempt::where('status', 'in_progress')->whereNull('deadline_at')
            ->with('exam')->get()->each(fn (ExamAttempt $attempt) => $this->deadline($attempt));

        $count = 0;
        ExamAttempt::where('status', 'in_progress')
            ->whereNull('suspended_at')
            ->where('deadline_at', '<', now()->subSeconds(self::EXPIRY_GRACE_SECONDS))
            ->chunkById(100, function ($attempts) use (&$count) {
                foreach ($attempts as $attempt) {
                    $before = $attempt->status;
                    $after = $this->finalize($attempt, 'timed_out', 'timed_out', null, 'Deadline passed while the student was away.');
                    $count += (int) ($before !== $after->status);
                }
            });

        return $count;
    }

    /* ───────────────────────── admin recovery actions ───────────────────────── */

    public function suspend(ExamAttempt $attempt, User $actor, ?string $reason, bool $notify = true): ExamAttempt
    {
        return $this->locked($attempt, function (ExamAttempt $attempt) use ($actor, $reason, $notify) {
            $this->requireOpen($attempt);
            if ($attempt->isPaused()) {
                throw new ExamSessionException('This session is already paused.');
            }

            $attempt->forceFill(['suspended_at' => now(), 'resume_on_window' => false])->save();
            $this->log($attempt, 'suspended', $actor, $reason, ['remaining_seconds' => $attempt->remainingSeconds()]);
            $notify && $this->notifyStudent($attempt, 'Your exam is paused', 'The exam team has paused your exam. Your answers and remaining time are saved. Keep the exam page open — it will continue when they resume it.');

            return $attempt;
        });
    }

    public function resume(ExamAttempt $attempt, User $actor, ?string $reason, bool $notify = true): ExamAttempt
    {
        return $this->locked($attempt, function (ExamAttempt $attempt) use ($actor, $reason, $notify) {
            $this->requireOpen($attempt);
            if (! $attempt->isPaused()) {
                throw new ExamSessionException('This session is not paused.');
            }

            $paused = $this->unpause($attempt);
            $attempt->save();
            $this->log($attempt, 'resumed', $actor, $reason, ['paused_seconds' => $paused, 'remaining_seconds' => $attempt->remainingSeconds()]);
            $notify && $this->notifyStudent($attempt, 'Your exam has resumed', 'You can continue your exam now. Your answers and remaining time were kept.');

            return $attempt;
        });
    }

    public function addTime(ExamAttempt $attempt, int $minutes, User $actor, ?string $reason, bool $notify = true): ExamAttempt
    {
        $this->requireMinutes($minutes);

        return $this->locked($attempt, function (ExamAttempt $attempt) use ($minutes, $actor, $reason, $notify) {
            $this->requireOpen($attempt);

            // An expired-but-not-yet-swept deadline grows from now, so the time is real.
            $base = $attempt->isPaused() ? $attempt->deadline_at : $attempt->deadline_at->copy()->max(now());
            $attempt->deadline_at = $base->copy()->addMinutes($minutes);
            $attempt->extra_seconds += $minutes * 60;
            $attempt->save();

            $this->log($attempt, 'time_added', $actor, $reason, ['minutes' => $minutes, 'remaining_seconds' => $attempt->remainingSeconds()]);
            $notify && $this->notifyStudent($attempt, "{$minutes} minutes added to your exam", 'The exam team gave you extra time. Your timer has been updated.');

            return $attempt;
        });
    }

    /**
     * Reopen a finished attempt with its answers kept. The time closed is excluded from
     * time used; an unreleased result is removed (re-process results afterwards).
     */
    public function reopen(ExamAttempt $attempt, int $minutes, bool $clearViolations, User $actor, ?string $reason, bool $notify = true): ExamAttempt
    {
        $this->requireMinutes($minutes);

        return $this->locked($attempt, function (ExamAttempt $attempt) use ($minutes, $clearViolations, $actor, $reason, $notify) {
            if (! $attempt->isFinished()) {
                throw new ExamSessionException('Only a submitted or timed-out exam can be reopened.');
            }
            if ($attempt->exam->status !== 'published') {
                throw new ExamSessionException('Publish the exam before reopening a student\'s session.');
            }
            $removedResult = $this->dropUnreleasedResult($attempt, $actor);

            [$closedFor, $usedSoFar] = $this->closedGap($attempt);
            $previousStatus = $attempt->status;

            $attempt->forceFill([
                'status' => 'in_progress',
                'submitted_at' => null,
                'suspended_at' => null,
                'resume_on_window' => false,
                'session_token' => null,
                'deadline_at' => now()->addMinutes($minutes),
                'paused_seconds' => $attempt->paused_seconds + $closedFor,
                // Keep "time used" ≤ "time given" true after the reopen.
                'extra_seconds' => max($attempt->extra_seconds, $usedSoFar + $minutes * 60 - $attempt->exam->duration_minutes * 60),
                'violation_count' => $clearViolations ? 0 : $attempt->violation_count,
                'reopen_count' => $attempt->reopen_count + 1,
            ])->save();

            $this->log($attempt, 'reopened', $actor, $reason, [
                'minutes' => $minutes,
                'previous_status' => $previousStatus,
                'violations_cleared' => $clearViolations,
                'result_removed' => $removedResult,
            ]);
            $notify && $this->notifyStudent($attempt, 'Your exam has been reopened', "You can continue your exam where you left off. You have {$minutes} minutes and your earlier answers are saved.");

            return $attempt;
        });
    }

    /**
     * Start from scratch: snapshot everything into the audit event, then delete the attempt
     * (answers and any unreleased result cascade). The student starts again through the
     * normal Start button, inside the exam window or the personal window given here.
     */
    public function reset(ExamAttempt $attempt, ?array $window, User $actor, ?string $reason, bool $notify = true): void
    {
        $this->locked($attempt, function (ExamAttempt $attempt) use ($window, $actor, $reason, $notify) {
            $exam = $attempt->exam;
            $enrollment = $this->enrollment($attempt->user_id, $attempt->exam_id);
            if (! $enrollment) {
                throw new ExamSessionException('This student is no longer enrolled, so they could not start again.');
            }
            if ($attempt->result?->is_released) {
                throw new ExamSessionException('This result is already released. Use a score override instead.');
            }
            if ($exam->status !== 'published') {
                throw new ExamSessionException('Publish the exam before resetting a student\'s session.');
            }
            if ($window) {
                $this->validateWindow($window, $exam->duration_minutes * 60);
            } elseif ($this->windowState($exam, $enrollment) !== 'live') {
                throw new ExamSessionException('The exam window is not open. Give the student a new window so they can start again.');
            }

            $snapshot = [
                'status' => $attempt->status,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'time_used_seconds' => $attempt->timeUsedSeconds($attempt->submitted_at),
                'total_score' => $attempt->total_score,
                'violation_count' => $attempt->violation_count,
                'had_result' => (bool) $attempt->result,
                'answers' => $attempt->answers()->get(['question_id', 'selected_options', 'is_flagged'])
                    ->map(fn ($a) => ['question_id' => $a->question_id, 'selected' => $a->selected_options ?? [], 'flagged' => (bool) $a->is_flagged])
                    ->values()->all(),
            ];
            $this->log($attempt, 'reset', $actor, $reason, $snapshot);

            if ($window) {
                $this->applyWindow($enrollment, $window, $actor, $reason, $attempt->id);
            }

            $attempt->delete();

            $notify && $this->notifyStudent($attempt, 'You can retake your exam', $window
                ? 'The exam team has reset your exam. You can start it again from the beginning between '.$this->windowLabel($window[0], $window[1]).'.'
                : 'The exam team has reset your exam. You can start it again from the beginning now.');
        });
    }

    /**
     * Give a student who started a new window.
     *  - resume: pause now, keep answers + time left, resume on their first visit in the window.
     *  - fresh: reset (start from scratch) inside the window.
     */
    public function rescheduleAttempt(ExamAttempt $attempt, array $window, string $mode, User $actor, ?string $reason, bool $notify = true): void
    {
        if ($mode === 'fresh') {
            $this->reset($attempt, $window, $actor, $reason, $notify);

            return;
        }

        $this->locked($attempt, function (ExamAttempt $attempt) use ($window, $actor, $reason, $notify) {
            $enrollment = $this->enrollment($attempt->user_id, $attempt->exam_id);
            if (! $enrollment) {
                throw new ExamSessionException('This student is no longer enrolled.');
            }
            if ($attempt->exam->status !== 'published') {
                throw new ExamSessionException('Publish the exam before rescheduling a student.');
            }

            if ($attempt->isFinished()) {
                // Reopen first (answers kept), then pause until the window.
                $this->dropUnreleasedResult($attempt, $actor);
                $remaining = max(60, $this->suggestedReopenSeconds($attempt));
                [$closedFor, $usedSoFar] = $this->closedGap($attempt);
                $attempt->forceFill([
                    'status' => 'in_progress',
                    'submitted_at' => null,
                    'session_token' => null,
                    'deadline_at' => now()->addSeconds($remaining),
                    'paused_seconds' => $attempt->paused_seconds + $closedFor,
                    'extra_seconds' => max($attempt->extra_seconds, $usedSoFar + $remaining - $attempt->exam->duration_minutes * 60),
                    'reopen_count' => $attempt->reopen_count + 1,
                ]);
            }
            $attempt->suspended_at ??= now();

            $this->validateWindow($window, $attempt->remainingSeconds());
            $attempt->resume_on_window = true;
            $attempt->save();

            $this->applyWindow($enrollment, $window, $actor, $reason, $attempt->id, ['mode' => 'resume', 'remaining_seconds' => $attempt->remainingSeconds()]);
            $notify && $this->notifyStudent($attempt, 'Your exam has been rescheduled', 'You can continue your exam where you left off between '.$this->windowLabel($window[0], $window[1]).'. Your answers and remaining time are saved.');
        });
    }

    /** A personal window for an enrolled student who never started. */
    public function rescheduleEnrollment(ExamEnrollment $enrollment, array $window, User $actor, ?string $reason, bool $notify = true): void
    {
        DB::transaction(function () use ($enrollment, $window, $actor, $reason, $notify) {
            $enrollment = ExamEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if ($enrollment->status !== 'enrolled') {
                throw new ExamSessionException('This student is not enrolled in the exam.');
            }
            if ($enrollment->exam->status !== 'published') {
                throw new ExamSessionException('Publish the exam before rescheduling a student.');
            }
            if (ExamAttempt::where('user_id', $enrollment->user_id)->where('exam_id', $enrollment->exam_id)->exists()) {
                throw new ExamSessionException('This student has already started. Reschedule them from their session instead.');
            }

            $this->validateWindow($window, $enrollment->exam->duration_minutes * 60);
            $this->applyWindow($enrollment, $window, $actor, $reason, null, ['mode' => 'start']);

            $notify && $this->notify($enrollment->user_id, $enrollment->exam, 'Your exam has been rescheduled', 'You can take your exam between '.$this->windowLabel($window[0], $window[1]).'.');
        });
    }

    /** Remove a personal window; the student falls back to the exam's own window. */
    public function unschedule(ExamEnrollment $enrollment, User $actor, ?string $reason): void
    {
        DB::transaction(function () use ($enrollment, $actor, $reason) {
            $enrollment = ExamEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if (! $enrollment->hasPersonalWindow()) {
                throw new ExamSessionException('This student has no personal window.');
            }

            $attempt = ExamAttempt::where('user_id', $enrollment->user_id)->where('exam_id', $enrollment->exam_id)->first();
            ExamSessionEvent::create([
                'exam_id' => $enrollment->exam_id,
                'user_id' => $enrollment->user_id,
                'exam_attempt_id' => $attempt?->id,
                'actor_id' => $actor->id,
                'type' => 'unscheduled',
                'reason' => $reason,
                'meta' => ['window' => [$enrollment->window_starts_at?->toIso8601String(), $enrollment->window_ends_at?->toIso8601String()]],
            ]);
            $enrollment->forceFill(['window_starts_at' => null, 'window_ends_at' => null])->save();

            if ($attempt?->isPaused() && $attempt->resume_on_window) {
                // Back to a plain admin pause, so it never resumes on its own.
                $attempt->forceFill(['resume_on_window' => false])->save();
            }
        });
    }

    public function forceSubmit(ExamAttempt $attempt, User $actor, ?string $reason, bool $notify = true): ExamAttempt
    {
        return $this->locked($attempt, function (ExamAttempt $attempt) use ($actor, $reason, $notify) {
            $this->requireOpen($attempt);
            $this->finalizeLocked($attempt, 'auto_submitted', 'force_submitted', $actor, $reason);
            $notify && $this->notifyStudent($attempt, 'Your exam was submitted', 'The exam team submitted your exam with the answers you had saved. Results will be declared by the admin.');

            return $attempt;
        });
    }

    public function clearViolations(ExamAttempt $attempt, User $actor, ?string $reason): ExamAttempt
    {
        return $this->locked($attempt, function (ExamAttempt $attempt) use ($actor, $reason) {
            $this->requireOpen($attempt);
            $before = $attempt->violation_count;
            $attempt->forceFill(['violation_count' => 0])->save();
            $this->log($attempt, 'violations_cleared', $actor, $reason, ['previous' => $before]);

            return $attempt;
        });
    }

    /**
     * Exam-wide incident controls over every open attempt. Each attempt gets its own event,
     * tied together by a batch id. Returns how many sessions changed.
     *
     * @param  string  $action  suspend | resume | add_time
     */
    public function bulk(Exam $exam, string $action, ?int $minutes, User $actor, ?string $reason, bool $notify = true): int
    {
        $this->batch = (string) Str::uuid();
        $changed = 0;

        $query = ExamAttempt::where('exam_id', $exam->id)->where('status', 'in_progress');
        match ($action) {
            'suspend' => $query->whereNull('suspended_at'),
            'resume' => $query->whereNotNull('suspended_at')->where('resume_on_window', false),
            default => null,
        };

        foreach ($query->pluck('id') as $id) {
            $attempt = ExamAttempt::find($id);
            try {
                match ($action) {
                    'suspend' => $this->suspend($attempt, $actor, $reason, $notify),
                    'resume' => $this->resume($attempt, $actor, $reason, $notify),
                    'add_time' => $this->addTime($attempt, (int) $minutes, $actor, $reason, $notify),
                };
                $changed++;
            } catch (ExamSessionException) {
                // The session changed state meanwhile (e.g. the student just submitted); skip it.
            }
        }

        $this->batch = null;

        return $changed;
    }

    /* ───────────────────────── read models ───────────────────────── */

    /**
     * Connection state for the admin list.
     *
     * @return array{state: string, offline_seconds: int}
     */
    public function connection(ExamAttempt $attempt): array
    {
        if (! $attempt->isOpen()) {
            return ['state' => $attempt->status, 'offline_seconds' => 0];
        }
        if ($attempt->isPaused()) {
            return ['state' => $attempt->resume_on_window ? 'rescheduled' : 'paused', 'offline_seconds' => 0];
        }
        if ($attempt->deadline_at && $attempt->deadline_at->isPast()) {
            return ['state' => 'time_over', 'offline_seconds' => 0];
        }

        $seen = $attempt->last_seen_at ?? $attempt->started_at ?? now();
        $ago = (int) $seen->diffInSeconds(now(), true);

        return [
            'state' => match (true) {
                $ago <= self::ONLINE_SECONDS => 'online',
                $ago <= self::UNSTABLE_SECONDS => 'unstable',
                default => 'offline',
            },
            'offline_seconds' => $ago > self::ONLINE_SECONDS ? $ago : 0,
        ];
    }

    /**
     * Offline time not yet given back: gaps logged on reconnect since the last time an admin
     * added time, plus the current gap if the student is still away. Capped at the duration.
     */
    public function lostSeconds(ExamAttempt $attempt): int
    {
        $since = ExamSessionEvent::where('exam_attempt_id', $attempt->id)->whereIn('type', ['time_added', 'reopened'])->max('created_at');
        $gaps = ExamSessionEvent::where('exam_attempt_id', $attempt->id)->where('type', 'reconnected')
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->get(['meta'])->sum(fn ($event) => (int) ($event->meta['gap_seconds'] ?? 0));

        $current = $this->connection($attempt)['offline_seconds'];

        return (int) min($gaps + $current, $attempt->exam->duration_minutes * 60);
    }

    /** Default time for a reopen: what was left when the session dropped (≥ 1 minute). */
    public function suggestedReopenSeconds(ExamAttempt $attempt): int
    {
        $dropped = $attempt->last_seen_at ?? $attempt->submitted_at ?? now();
        $left = $attempt->deadline_at ? (int) $dropped->diffInSeconds($attempt->deadline_at, false) : 0;

        return (int) max(60, min($left, $attempt->exam->duration_minutes * 60));
    }

    /**
     * The single call-to-action for a student's enrolled exam (dashboard, exams list, detail).
     *
     * @return array{action: string, window_label: ?string}
     */
    public function studentAction(Exam $exam, ?ExamAttempt $attempt, ?ExamEnrollment $enrollment, ?Result $result): array
    {
        [$starts, $ends, $personal] = $this->window($exam, $enrollment);
        $label = $personal ? $this->windowLabel($starts, $ends) : null;

        if ($attempt && $attempt->isOpen()) {
            $state = $this->windowState($exam, $enrollment);
            $action = match (true) {
                $attempt->isPaused() && $attempt->resume_on_window && $state === 'upcoming' => 'rescheduled',
                $attempt->isPaused() && $attempt->resume_on_window && $state === 'closed' => 'closed',
                $attempt->isPaused() && ! $attempt->resume_on_window => 'paused',
                default => 'continue',
            };
        } elseif ($attempt) {
            $action = ($result && $result->is_released) ? 'result' : 'awaiting';
        } elseif ($exam->status !== 'published') {
            $action = 'closed';
        } else {
            $action = match ($this->windowState($exam, $enrollment)) {
                'live' => 'start',
                'upcoming' => $personal ? 'rescheduled' : 'upcoming',
                default => 'closed',
            };
        }

        return ['action' => $action, 'window_label' => $label];
    }

    /** Server-formatted window ("Wed, 12 Nov · 10:00 AM – 6:00 PM"); stored wall time, per §7. */
    public function windowLabel(?Carbon $starts, ?Carbon $ends): ?string
    {
        if (! $starts || ! $ends) {
            return null;
        }

        return $starts->isSameDay($ends)
            ? $starts->format('D, j M · g:i A').' – '.$ends->format('g:i A')
            : $starts->format('D, j M · g:i A').' – '.$ends->format('D, j M · g:i A');
    }

    /* ───────────────────────── internals ───────────────────────── */

    public function answeredCount(ExamAttempt $attempt): int
    {
        return $attempt->answers()->get(['selected_options'])->filter(fn ($a) => ! empty($a->selected_options))->count();
    }

    /** Run $fn with the attempt row locked and freshly loaded (with its exam). */
    private function locked(ExamAttempt $attempt, callable $fn)
    {
        return DB::transaction(function () use ($attempt, $fn) {
            $fresh = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->first();
            if (! $fresh) {
                throw new ExamSessionException('This session no longer exists — refresh the page.');
            }
            $fresh->setRelation('exam', $attempt->relationLoaded('exam') ? $attempt->exam : $fresh->exam()->first());

            return $fn($fresh);
        });
    }

    private function finalizeLocked(ExamAttempt $attempt, string $status, string $event, ?User $actor, ?string $reason): ExamAttempt
    {
        if (! $attempt->isOpen()) {
            return $attempt;
        }

        if ($attempt->isPaused()) {
            $this->unpause($attempt);
            $attempt->save();
        }

        $this->scoring->score($attempt, $status);
        $attempt->forceFill(['session_token' => null, 'resume_on_window' => false])->save();
        $this->log($attempt, $event, $actor, $reason, [
            'answered' => $attempt->total_attempted,
            'time_used_seconds' => $attempt->time_taken_seconds,
        ]);

        return $attempt;
    }

    /**
     * For reopening a finished attempt: how long it was closed, and the time used up to the
     * close. The session counts as closed from the deadline when that came first (the
     * sweeper's grace period is not time the student used).
     *
     * @return array{0: int, 1: int} [closedSeconds, usedSeconds]
     */
    private function closedGap(ExamAttempt $attempt): array
    {
        $closedAt = $attempt->submitted_at ?? now();
        if ($attempt->deadline_at && $attempt->deadline_at->lt($closedAt)) {
            $closedAt = $attempt->deadline_at->copy();
        }

        return [(int) $closedAt->diffInSeconds(now(), true), $attempt->timeUsedSeconds($closedAt)];
    }

    /** Clear a pause: the deadline moves by the paused time. Returns the seconds paused. */
    private function unpause(ExamAttempt $attempt): int
    {
        $paused = (int) $attempt->suspended_at->diffInSeconds(now(), true);
        $attempt->deadline_at = $attempt->deadline_at->copy()->addSeconds($paused);
        $attempt->paused_seconds += $paused;
        $attempt->suspended_at = null;
        $attempt->resume_on_window = false;

        return $paused;
    }

    private function dropUnreleasedResult(ExamAttempt $attempt, User $actor): bool
    {
        $result = Result::where('user_id', $attempt->user_id)->where('exam_id', $attempt->exam_id)->first();
        if (! $result) {
            return false;
        }
        if ($result->is_released) {
            throw new ExamSessionException('This result is already released. Use a score override instead.');
        }

        $this->log($attempt, 'result_removed', $actor, null, ['score' => (float) $result->total_score, 'rank' => $result->rank_national]);
        $result->delete();

        return true;
    }

    /** @param array{0: Carbon, 1: Carbon} $window */
    private function applyWindow(ExamEnrollment $enrollment, array $window, User $actor, ?string $reason, ?int $attemptId, array $meta = []): void
    {
        $enrollment->forceFill(['window_starts_at' => $window[0], 'window_ends_at' => $window[1]])->save();

        ExamSessionEvent::create([
            'exam_id' => $enrollment->exam_id,
            'user_id' => $enrollment->user_id,
            'exam_attempt_id' => $attemptId,
            'actor_id' => $actor->id,
            'type' => 'rescheduled',
            'reason' => $reason,
            'meta' => [...$meta, 'window' => [$window[0]->toIso8601String(), $window[1]->toIso8601String()]],
        ]);
    }

    /** @param array{0: Carbon, 1: Carbon} $window */
    private function validateWindow(array $window, int $neededSeconds): void
    {
        [$starts, $ends] = $window;
        if ($ends->lte($starts)) {
            throw new ExamSessionException('The window must end after it starts.');
        }
        if ($ends->lte(now())) {
            throw new ExamSessionException('The window must end in the future.');
        }
        if ($starts->diffInSeconds($ends, true) < $neededSeconds) {
            throw new ExamSessionException('The window is shorter than the time the student needs ('.(int) ceil($neededSeconds / 60).' min).');
        }
    }

    private function requireOpen(ExamAttempt $attempt): void
    {
        if (! $attempt->isOpen()) {
            throw new ExamSessionException('This session has already ended — refresh the page.');
        }
    }

    private function requireMinutes(int $minutes): void
    {
        if ($minutes < 1 || $minutes > self::MAX_GRANT_MINUTES) {
            throw new ExamSessionException('Give between 1 and '.self::MAX_GRANT_MINUTES.' minutes.');
        }
    }

    private function log(ExamAttempt $attempt, string $type, ?User $actor = null, ?string $reason = null, array $meta = []): void
    {
        ExamSessionEvent::create([
            'exam_id' => $attempt->exam_id,
            'user_id' => $attempt->user_id,
            'exam_attempt_id' => $attempt->exists ? $attempt->id : null,
            'actor_id' => $actor?->id,
            'type' => $type,
            'reason' => $reason,
            'meta' => ($this->batch ? [...$meta, 'batch' => $this->batch] : $meta) ?: null,
        ]);
    }

    private function notifyStudent(ExamAttempt $attempt, string $title, string $message): void
    {
        $this->notify($attempt->user_id, $attempt->exam, $title, $message);
    }

    private function notify(int $userId, Exam $exam, string $title, string $message): void
    {
        StudentNotification::create([
            'user_id' => $userId,
            'title' => $title.' — '.$exam->name,
            'message' => $message,
            'link' => route('student.dashboard'),
        ]);
    }
}
