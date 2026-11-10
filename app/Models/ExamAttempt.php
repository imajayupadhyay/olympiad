<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ExamAttempt extends Model
{
    /** Statuses of an attempt that has ended (scored). in_progress is the only open state. */
    public const FINISHED = ['submitted', 'timed_out', 'auto_submitted'];

    protected $fillable = [
        'user_id', 'exam_id', 'status',
        'started_at', 'submitted_at', 'time_taken_seconds',
        'total_score', 'total_attempted', 'total_correct', 'total_wrong', 'total_skipped',
        'ip_address',
        // Session recovery (ExamSessionService owns every write to these).
        'deadline_at', 'suspended_at', 'resume_on_window', 'last_seen_at', 'current_question_id', 'violation_count',
        'session_token', 'extra_seconds', 'paused_seconds', 'reopen_count',
    ];

    protected $hidden = ['session_token'];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'deadline_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resume_on_window' => 'boolean',
        'total_score' => 'float',
        'violation_count' => 'integer',
        'extra_seconds' => 'integer',
        'paused_seconds' => 'integer',
        'reopen_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    public function result()
    {
        return $this->hasOne(Result::class);
    }

    public function events()
    {
        return $this->hasMany(ExamSessionEvent::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'in_progress';
    }

    /** Paused by an admin (or waiting for a rescheduled window). Clock is frozen. */
    public function isPaused(): bool
    {
        return $this->isOpen() && $this->suspended_at !== null;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED, true);
    }

    /**
     * Seconds left on the personal deadline; frozen while paused. Needs deadline_at
     * (ExamSessionService::deadline() fills it for legacy rows).
     */
    public function remainingSeconds(): int
    {
        if (! $this->deadline_at) {
            return 0;
        }

        $at = $this->suspended_at ?? Carbon::now();

        return (int) max(0, $at->diffInSeconds($this->deadline_at, false));
    }

    /**
     * Active time used: wall time since start minus paused/closed time, capped at the
     * time the student was actually given (and at the smallint column's limit).
     */
    public function timeUsedSeconds(?Carbon $at = null): int
    {
        if (! $this->started_at) {
            return 0;
        }

        $at ??= $this->suspended_at ?? Carbon::now();
        $used = (int) $this->started_at->diffInSeconds($at, true) - (int) $this->paused_seconds;
        $allotted = (int) ($this->exam?->duration_minutes ?? 0) * 60 + (int) $this->extra_seconds;

        return (int) max(0, min($used, $allotted > 0 ? $allotted : $used, 65535));
    }
}
